<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Target;
use Illuminate\Support\Facades\DB;

/** Dashboard review 2026-11-09: cases and value, volume targets, full-month target, target periods, forecast horizon. */
class MeetingChangesTest extends SalesTestCase
{
    private function sale(int $co, int $cust, string $date, float $v, float $cases): array
    {
        return ['company_id' => $co, 'transaction_date' => $date, 'customer_id' => $cust, 'net_value' => $v, 'gross_value' => $v, 'quantity' => $cases,
            'row_key' => md5($co . $cust . $date . $v . microtime()), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
    }

    public function test_volume_and_full_month_targets_and_the_breakdown(): void
    {
        [$co, $admin] = $this->tenant('Meet Co');
        $this->as($co->id);
        $c = Customer::create(['customer_code' => 'A', 'customer_name' => 'Alpha']);
        DB::table('sales_records')->insert([$this->sale($co->id, $c->id, '2026-11-03', 3000, 30), $this->sale($co->id, $c->id, '2026-10-10', 9000, 90)]);
        foreach ([['net_value', 30000], ['quantity', 300]] as [$metric, $value]) {
            Target::create(['name' => "Nov {$metric}", 'metric' => $metric, 'method' => 'fixed', 'period_type' => 'monthly', 'start_date' => '2026-11-01',
                'end_date' => '2026-11-30', 'target_value' => $value, 'status' => 'approved', 'created_by' => $admin->id]);
        }
        $q = fn ($cfg) => $this->actingAs($admin)->postJson('/sales/bi/query', ['config' => ['kind' => 'kpi'] + $cfg, 'filters' => ['from' => '2026-11-01', 'to' => '2026-11-03']])->assertOk()->json('values');

        $v = $q(['measures' => ['quantity', 'volume_target', 'volume_achievement_pct']]);
        $this->assertEquals(30, $v['quantity']);
        $this->assertEquals(30, $v['volume_target'], '300 cases over 30 days, 3 days in = 30 cases to date.');
        $this->assertEquals(100.0, $v['volume_achievement_pct']);
        $this->assertEquals(30000, $q(['measures' => ['net_value', 'month_target']])['month_target'], 'The run rate uses the whole month, not the part to date.');

        $t = Target::where('metric', 'net_value')->first();
        $ytd = $this->actingAs($admin)->get("/sales/targets/{$t->uuid}?by=week")->assertOk()->viewData('page')['props'];
        $this->assertSame('week', $ytd['breakdownBy']);
        $this->assertEquals(30000, round(collect($ytd['breakdown'])->sum('target')), 'Weekly slices add up to the target.');
        $this->assertEquals(3000, collect($ytd['breakdown'])->sum('actual'));
    }

    public function test_the_forecast_period_can_be_chosen(): void
    {
        [$co, $admin] = $this->tenant('Fc Co');
        $this->as($co->id);
        $c = Customer::create(['customer_code' => 'A', 'customer_name' => 'Alpha']);
        DB::table('sales_records')->insert([$this->sale($co->id, $c->id, now()->subMonths(13)->toDateString(), 1000, 10), $this->sale($co->id, $c->id, now()->toDateString(), 500, 5)]);

        foreach ([1 => 2, 18 => 19, 99 => 37] as $asked => $rows) {
            $props = $this->actingAs($admin)->get("/sales/forecast?months={$asked}")->assertOk()->viewData('page')['props'];
            $this->assertCount($rows, $props['forecast']['months'], 'This month plus the months asked for (custom is capped at 36).');
        }

        // Volume in cases, with the workings shown on the page adding up.
        $props = $this->actingAs($admin)->get('/sales/forecast?months=3&measure=cases')->assertOk()->viewData('page')['props'];
        $this->assertSame('cases', $props['measure']);
        $this->assertSame('quantity', $props['forecast']['measure']);
        $w = $props['forecast']['workings'];
        $this->assertEquals(5, $w['month_to_date'], 'Five cases sold this month.');
        $this->assertEqualsWithDelta($w['month_to_date'] / $w['days_elapsed'] * $w['days_in_month'], $w['run_rate'], 0.01);
        $this->assertEqualsWithDelta($w['run_rate'], $props['forecast']['months'][0]['forecast'], 0.01);
        $this->assertArrayHasKey('accuracy', $props['forecast']);
    }
}
