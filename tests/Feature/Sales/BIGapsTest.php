<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\ProductCategory;
use App\Core\AI\Services\AIChatProviderChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Gap review 2026-11-10: per-visual filters, ask, versions, certification, comments, share links. */
class BIGapsTest extends SalesTestCase
{
    private function sale(int $co, int $cust, string $date, float $v, ?int $product = null): array
    {
        return ['company_id' => $co, 'transaction_date' => $date, 'customer_id' => $cust, 'product_id' => $product, 'net_value' => $v, 'gross_value' => $v, 'quantity' => 1,
            'row_key' => md5($co . $cust . $date . $v . microtime() . random_int(0, 1e9)), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
    }

    private function board(int $companyId, int $ownerId): string
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        DB::table('bi_dashboards')->insert(['uuid' => $uuid, 'company_id' => $companyId, 'owner_id' => $ownerId, 'name' => 'Board', 'visibility' => 'organisation',
            'widgets' => json_encode([['id' => 'w1', 'type' => 'kpi_card', 'title' => 'Sales', 'config' => ['kind' => 'kpi', 'measures' => ['net_value']], 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 4]]),
            'created_at' => now(), 'updated_at' => now()]);

        return $uuid;
    }

    public function test_a_visual_can_have_its_own_filters_and_drill_into_a_period(): void
    {
        [$co, $admin] = $this->tenant('Filter Co');
        $this->as($co->id);
        $a = Customer::create(['customer_code' => 'A', 'customer_name' => 'Alpha']);
        $b = Customer::create(['customer_code' => 'B', 'customer_name' => 'Bravo']);
        DB::table('sales_records')->insert([$this->sale($co->id, $a->id, '2026-03-05', 100), $this->sale($co->id, $b->id, '2026-03-06', 50), $this->sale($co->id, $a->id, '2026-07-01', 70)]);
        $q = fn ($cfg, $f = []) => $this->actingAs($admin)->postJson('/sales/bi/query', ['config' => $cfg, 'filters' => $f + ['from' => '2026-01-01', 'to' => '2026-12-31']])->assertOk()->json();

        $this->assertEquals(220, $q(['kind' => 'kpi', 'measures' => ['net_value']])['values']['net_value']);
        $this->assertEquals(170, $q(['kind' => 'kpi', 'measures' => ['net_value'], 'filters' => ['customer_id' => $a->id]])['values']['net_value'], 'Visual filter narrows the dashboard filter.');
        // Drill: the quarter 2026-Q1 → its months.
        $drill = $q(['kind' => 'time', 'measures' => ['net_value'], 'grain' => 'month', 'period' => 'dashboard', 'filters' => ['from' => '2026-01-01', 'to' => '2026-03-31']]);
        $this->assertSame([150.0], array_map('floatval', $drill['series'][0]['data']));
    }

    public function test_a_question_becomes_a_safe_chart_even_without_the_ai(): void
    {
        $this->app->instance(AIChatProviderChain::class, new class extends AIChatProviderChain { public function __construct() {} public function text(string $s, string $u, array $o = []): ?string { return null; } });
        [$co, $admin] = $this->tenant('Ask Co');
        $this->as($co->id);
        $pizza = ProductCategory::create(['name' => 'Pizza']);

        $r = $this->actingAs($admin)->postJson('/sales/bi/ask', ['question' => 'Top 5 customers by Pizza cases this month vs last year'])->assertOk()->json();
        $this->assertSame('keywords', $r['source']);
        $this->assertSame('customer', $r['config']['dimension']);
        $this->assertSame(['quantity', 'last_year_cases'], $r['config']['measures'], 'Cases are compared with last year\'s cases.');
        $this->assertSame('mtd', $r['config']['period']);
        $this->assertSame(5, $r['config']['top']);
        $this->assertSame($pizza->id, $r['config']['filters']['category_id']);
        // The answer is a normal, valid visual.
        $this->actingAs($admin)->postJson('/sales/bi/query', ['config' => $r['config']])->assertOk();
    }

    public function test_versions_restore_and_saving_removes_certification(): void
    {
        [$co, $admin] = $this->tenant('Version Co');
        $this->as($co->id);
        $uuid = $this->board($co->id, $admin->id);
        $this->actingAs($admin)->postJson("/sales/bi/{$uuid}/certify", ['certified' => true])->assertOk()->assertJson(['certified' => true]);
        $this->actingAs($admin)->putJson("/sales/bi/{$uuid}", ['widgets' => []])->assertOk();
        $this->assertFalse((bool) DB::table('bi_dashboards')->where('uuid', $uuid)->value('certified'), 'A changed dashboard is no longer certified.');

        $versions = $this->actingAs($admin)->getJson("/sales/bi/{$uuid}/versions")->assertOk()->json();
        $this->assertCount(1, $versions);
        $this->assertSame(1, $versions[0]['visuals']);
        $this->actingAs($admin)->postJson("/sales/bi/{$uuid}/versions/{$versions[0]['id']}/restore")->assertOk();
        $this->assertCount(1, json_decode(DB::table('bi_dashboards')->where('uuid', $uuid)->value('widgets'), true));
    }

    public function test_comments_notify_the_people_mentioned(): void
    {
        Notification::fake();
        [$co, $admin] = $this->tenant('Comment Co');
        $this->as($co->id);
        $thandi = $this->member($co->id, 'sales manager');
        DB::table('users')->where('id', $thandi->id)->update(['email' => 'thandi@comment.test']);
        $uuid = $this->board($co->id, $admin->id);

        $r = $this->actingAs($admin)->postJson("/sales/bi/{$uuid}/comments", ['body' => 'Why did this drop? @thandi@comment.test', 'widget_id' => 'w1'])->assertOk()->json();
        $this->assertCount(1, $r['notified']);
        Notification::assertSentTo($thandi->fresh(), \App\Modules\Sales\Notifications\SalesAlert::class);
        $this->assertCount(1, $this->actingAs($admin)->getJson("/sales/bi/{$uuid}/comments")->json());
    }

    public function test_a_share_link_shows_the_dashboard_without_login_and_can_be_withdrawn(): void
    {
        [$co, $admin] = $this->tenant('Share Co');
        [$other] = $this->tenant('Other Co');
        $this->as($co->id);
        $c = Customer::create(['customer_code' => 'A', 'customer_name' => 'Alpha']);
        $this->as($other->id);
        $x = Customer::create(['customer_code' => 'X', 'customer_name' => 'Elsewhere']);
        DB::table('sales_records')->insert([$this->sale($co->id, $c->id, now()->toDateString(), 400), $this->sale($other->id, $x->id, now()->toDateString(), 99999)]);
        $this->as($co->id);
        $uuid = $this->board($co->id, $admin->id);

        $url = $this->actingAs($admin)->postJson("/sales/bi/{$uuid}/shares", ['days' => 7])->assertOk()->json('url');
        $token = basename($url);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get("/share/{$token}")->assertOk()->assertInertia(fn ($p) => $p->component('Modules/Sales/BI/Shared'));
        $v = $this->postJson("/share/{$token}/query", ['config' => ['kind' => 'kpi', 'measures' => ['net_value'], 'period' => 'all']])->assertOk()->json('values.net_value');
        $this->assertEquals(400, $v, 'Only the sharing company is counted.');

        $id = DB::table('bi_shares')->where('token', $token)->value('id');
        $this->actingAs($admin)->deleteJson("/sales/bi/{$uuid}/shares/{$id}")->assertOk();
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get("/share/{$token}")->assertNotFound();
    }
}
