<?php

namespace Tests\Feature\Sales;

use App\Core\AI\Services\AIChatProviderChain;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Services\AiQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** AI allowances 2026-11-10: when a company's or a person's tokens are used up, nothing is sent and they are told. */
class AiAllowanceTest extends SalesTestCase
{
    private function used(int $companyId, int $userId, int $tokens): void
    {
        DB::table('ai_usage_log')->insert(['company_id' => $companyId, 'user_id' => $userId, 'feature' => 'test', 'total_tokens' => $tokens, 'prompt_tokens' => $tokens,
            'completion_tokens' => 0, 'success' => true, 'created_at' => now()]);
    }

    public function test_a_company_out_of_tokens_sends_nothing_and_is_told(): void
    {
        Http::fake();
        [$co, $admin] = $this->tenant('Quota Co');
        Company::whereKey($co->id)->update(['ai_token_quota' => 20]);
        $this->as($co->id);
        $this->used($co->id, $admin->id, 20);
        $this->actingAs($admin);

        $this->assertSame('company', app(AiQuota::class)->blockReason($admin));
        $this->assertNull(app(AIChatProviderChain::class)->text('system', 'hello', ['module' => 'test']));
        Http::assertNothingSent();

        $r = $this->postJson('/ai-copilot/chat', ['message' => 'What were sales last month?'])->assertOk()->json();
        $this->assertTrue($r['quota_blocked']);
        $this->assertSame('company', $r['quota_reason']);
        Http::assertNothingSent();
    }

    public function test_a_person_out_of_their_own_tokens_is_blocked_and_colleagues_are_not(): void
    {
        Http::fake();
        [$co, $admin] = $this->tenant('Person Co');
        Company::whereKey($co->id)->update(['ai_token_quota' => 0, 'ai_user_token_quota' => 1000]);   // company unlimited; 1,000 each by default
        $this->as($co->id);
        $rep = $this->member($co->id, 'sales manager');
        $other = $this->member($co->id, 'sales manager');
        $this->used($co->id, $rep->id, 1000);

        $this->assertSame('user', app(AiQuota::class)->blockReason($rep->fresh()));
        $this->assertNull(app(AiQuota::class)->blockReason($other->fresh()), 'A colleague still has their allowance.');

        $this->actingAs($rep);
        $ask = $this->postJson('/sales/bi/ask', ['question' => 'Top 5 customers this month'])->assertOk()->json();
        $this->assertSame('user', $ask['ai_blocked']);
        $this->assertStringContainsString('monthly AI allowance', $ask['explanation']);
        Http::assertNothingSent();

        // The administrator raises this person's own ceiling; they can use AI again.
        $this->actingAs($admin)->put("/sales/ai-usage/users/{$rep->id}/allowance", ['tokens' => 5000])->assertSessionHasNoErrors();
        $this->assertNull(app(AiQuota::class)->blockReason($rep->fresh()));
        // 0 = no AI for this person; a non-admin cannot change allowances.
        $this->actingAs($admin)->put("/sales/ai-usage/users/{$other->id}/allowance", ['tokens' => 0]);
        $this->assertSame('user', app(AiQuota::class)->blockReason($other->fresh()));
        $this->actingAs($rep)->put("/sales/ai-usage/users/{$rep->id}/allowance", ['tokens' => 999999])->assertForbidden();
    }
}
