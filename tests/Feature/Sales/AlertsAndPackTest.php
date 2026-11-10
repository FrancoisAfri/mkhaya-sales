<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\BI\UserAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Gap review 2026-11-10: alerts on any number (3) and board packs (10). */
class AlertsAndPackTest extends SalesTestCase
{
    private function sale(int $co, int $cust, string $date, float $v, float $cases = 1): array
    {
        return ['company_id' => $co, 'transaction_date' => $date, 'customer_id' => $cust, 'net_value' => $v, 'gross_value' => $v, 'quantity' => $cases,
            'row_key' => md5($co . $cust . $date . $v . random_int(0, 1e9)), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
    }

    public function test_an_alert_fires_on_its_rule_at_its_time_and_tells_its_owner(): void
    {
        Notification::fake();
        [$co, $admin] = $this->tenant('Alert Co');
        $this->as($co->id);
        $c = Customer::create(['customer_code' => 'S', 'customer_name' => 'Shoprite']);
        // Last 7 days 60 cases; the 7 days before 100 cases → −40%.
        DB::table('sales_records')->insert([$this->sale($co->id, $c->id, '2026-11-07', 600, 60), $this->sale($co->id, $c->id, '2026-10-29', 1000, 100)]);

        $this->actingAs($admin)->post('/sales/alerts', ['name' => 'Shoprite cases falling', 'measure' => 'quantity', 'filters' => ['customer_id' => $c->id],
            'period' => 'last_7_days', 'comparison' => 'vs_previous', 'operator' => 'below', 'threshold' => -10, 'frequency' => 'daily', 'run_time' => '07:00',
            'channels' => ['in_app']])->assertSessionHasNoErrors();
        $a = DB::table('bi_alerts')->where('company_id', $co->id)->first();

        $this->assertTrue(UserAlertService::due($a, CarbonImmutable::parse('2026-11-08 07:00', 'Africa/Johannesburg')));
        $this->assertFalse(UserAlertService::due($a, CarbonImmutable::parse('2026-11-08 07:01', 'Africa/Johannesburg')));

        $r = app(UserAlertService::class)->check($a);
        $this->assertEquals(-40.0, $r['value']);
        $this->assertTrue($r['triggered']);
        Notification::assertSentTo($admin, \App\Modules\Sales\Notifications\SalesAlert::class);

        // Someone else cannot see or change it.
        $other = $this->member($co->id, 'sales manager');
        $this->actingAs($other)->post("/sales/alerts/{$a->uuid}/test")->assertNotFound();
    }

    public function test_a_board_pack_is_built_as_powerpoint_and_pdf(): void
    {
        [$co, $admin] = $this->tenant('Pack Co');
        $this->as($co->id);
        $uuid = (string) \Illuminate\Support\Str::uuid();
        DB::table('bi_dashboards')->insert(['uuid' => $uuid, 'company_id' => $co->id, 'owner_id' => $admin->id, 'name' => 'Board', 'visibility' => 'private', 'widgets' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $png = 'data:image/png;base64,' . base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $items = [['title' => 'Total sales', 'text' => "R 46k\n-13% vs last year"], ['title' => 'Monthly sales', 'image' => $png]];

        $pptx = $this->actingAs($admin)->post("/sales/bi/{$uuid}/board-pack", ['format' => 'pptx', 'period' => 'Nov 2026', 'items' => $items])->assertOk();
        $this->assertStringStartsWith('PK', file_get_contents($pptx->baseResponse->getFile()->getPathname()));
        $pdf = $this->actingAs($admin)->post("/sales/bi/{$uuid}/board-pack", ['format' => 'pdf', 'period' => 'Nov 2026', 'items' => $items])->assertOk();
        $this->assertStringStartsWith('%PDF', file_get_contents($pdf->baseResponse->getFile()->getPathname()));

        $this->actingAs($admin)->post("/sales/bi/{$uuid}/board-pack", ['format' => 'pdf', 'items' => [['title' => 'x', 'image' => 'javascript:alert(1)']]])->assertSessionHasErrors('items.0.image');
    }
}
