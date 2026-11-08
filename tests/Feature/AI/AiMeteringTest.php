<?php

namespace Tests\Feature\AI;

use App\Core\AI\Services\AiMeter;
use App\Core\AI\Services\AIChatProviderChain;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Services\AiQuota;
use App\Modules\Tenancy\Services\CurrentCompany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every AI call is written down with its tokens: services that call the provider chain directly
 * (all of Content Marketing) were never metered, so the monthly AI quota never saw them.
 */
class AiMeteringTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement("SELECT set_config('app.company_id', '', false)");
        $this->company = Company::create(['name' => 'Meter Test '.Str::random(4), 'status' => 'active', 'plan' => 'standard']);
        app(CurrentCompany::class)->set($this->company->id);
        DB::statement("SELECT set_config('app.company_id', ?, false)", [(string) $this->company->id]);
        config(['ai.chat.chain' => ['fake'], 'ai.chat.providers' => ['fake' => ['driver' => 'openai', 'base_url' => 'https://fake-ai.test/v1', 'key' => 'k', 'model' => 'claude-haiku-4-5']]]);
    }

    protected function tearDown(): void
    {
        DB::statement("SELECT set_config('app.company_id', '', false)");
        app(CurrentCompany::class)->clear();
        parent::tearDown();
    }

    private function rows()
    {
        return DB::table('ai_usage_log')->where('company_id', $this->company->id)->orderBy('id')->get();
    }

    public function test_a_direct_chain_call_is_metered_with_the_providers_split_and_cost(): void
    {
        Http::fake(['fake-ai.test/*' => Http::response(['model' => 'claude-haiku-4-5', 'choices' => [['message' => ['content' => 'A written answer.']]],
            'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 800, 'total_tokens' => 2000]])]);

        $before = app(AiQuota::class)->usedThisMonth($this->company->id);
        $this->assertSame('A written answer.', app(AIChatProviderChain::class)->text('You are a writer.', 'Write something.'));

        $row = $this->rows()->last();
        $this->assertSame(1200, (int) $row->prompt_tokens);
        $this->assertSame(800, (int) $row->completion_tokens);
        $this->assertSame(2000, (int) $row->total_tokens);
        $this->assertFalse((bool) $row->tokens_estimated);
        $this->assertEqualsWithDelta(0.0052, (float) $row->cost_usd, 0.000001, '1,200 in at $1/M and 800 out at $5/M.');
        $this->assertSame($before + 2000, app(AiQuota::class)->usedThisMonth($this->company->id), 'The quota counts it.');
    }

    public function test_a_provider_that_reports_no_usage_is_estimated_and_marked(): void
    {
        Http::fake(['fake-ai.test/*' => Http::response(['choices' => [['message' => ['content' => str_repeat('word ', 200)]]]])]);

        app(AIChatProviderChain::class)->text('System prompt here.', str_repeat('a', 400));

        $row = $this->rows()->last();
        $this->assertTrue((bool) $row->tokens_estimated);
        $this->assertGreaterThanOrEqual(100, (int) $row->prompt_tokens, '~4 characters a token.');
        $this->assertSame(250, (int) $row->completion_tokens);
    }

    public function test_callers_that_meter_themselves_are_not_counted_twice(): void
    {
        Http::fake(['fake-ai.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10]])]);
        $n = $this->rows()->count();
        app(AIChatProviderChain::class)->complete([['role' => 'user', 'content' => 'hi']], ['meter' => false]);
        $this->assertSame($n, $this->rows()->count());
    }

    public function test_cost_is_priced_by_model_prefix_and_unknown_models_stay_unpriced(): void
    {
        $this->assertEqualsWithDelta(0.006, AiMeter::costUsd('claude-haiku-4-5-20251001', 1000, 1000), 0.0000001);
        $this->assertEqualsWithDelta(0.012, AiMeter::costUsd('claude-sonnet-5-5', 1000, 1000), 0.0000001);
        $this->assertNull(AiMeter::costUsd('llama-3.3-70b-versatile', 1000, 1000));
    }
}
