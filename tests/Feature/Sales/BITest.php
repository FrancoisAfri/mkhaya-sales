<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use Illuminate\Support\Facades\DB;

/** BI module — measure engine, dashboards, sharing, isolation (BI spec §14 acceptance). */
class BITest extends SalesTestCase
{
    private function sale(int $co, int $cust, string $date, float $v): array
    {
        return ['company_id' => $co, 'transaction_date' => $date, 'customer_id' => $cust, 'net_value' => $v, 'gross_value' => $v, 'quantity' => 1,
            'row_key' => md5($co . $cust . $date . $v), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
    }

    public function test_the_engine_agrees_with_reports_and_never_crosses_companies(): void
    {
        [$a, $adminA] = $this->tenant('BI A');
        [$b] = $this->tenant('BI B');
        $this->as($a->id);
        $ca = Customer::create(['customer_code' => 'A1', 'customer_name' => 'Alpha']);
        $this->as($b->id);
        $cb = Customer::create(['customer_code' => 'B1', 'customer_name' => 'Bravo']);
        DB::table('sales_records')->insert([$this->sale($a->id, $ca->id, '2026-11-02', 1000), $this->sale($a->id, $ca->id, '2025-11-02', 800), $this->sale($b->id, $cb->id, '2026-11-02', 99999)]);
        $filters = ['from' => '2026-11-01', 'to' => '2026-11-30'];

        $kpi = $this->actingAs($adminA)->postJson('/sales/bi/query', ['config' => ['kind' => 'kpi', 'measures' => ['net_value', 'growth_pct', 'last_year']], 'filters' => $filters])->assertOk()->json();
        $this->assertEquals(1000, $kpi['values']['net_value'], 'Company B must never be counted.');
        $this->assertEquals(25.0, $kpi['values']['growth_pct']);

        $cat = $this->actingAs($adminA)->postJson('/sales/bi/query', ['config' => ['kind' => 'category', 'dimension' => 'customer', 'measures' => ['net_value']], 'filters' => $filters])->json();
        $this->assertSame(['Alpha'], $cat['categories']);
        $report = $this->actingAs($adminA)->get('/sales/reports?report=by_customer&from=2026-11-01&to=2026-11-30')->viewData('page')['props']['result'];
        $this->assertEquals($cat['total'], $report['total'], 'BI and reports use the same numbers.');

        $this->actingAs($adminA)->postJson('/sales/bi/query', ['config' => ['kind' => 'category', 'dimension' => 'nonsense']])->assertStatus(422);
    }

    public function test_dashboards_land_by_role_and_are_shared_by_permission(): void
    {
        [$co, $admin] = $this->tenant('Dash Co');
        $reader = $this->member($co->id, 'read-only user');

        $this->actingAs($admin)->get('/')->assertOk()->assertInertia(fn ($p) => $p->component('Modules/Sales/BI/Dashboard')->where('landing', true));
        $this->actingAs($admin)->post('/sales/bi', ['name' => 'My board', 'template' => 'customer'])->assertRedirect();
        $uuid = DB::table('bi_dashboards')->where('company_id', $co->id)->where('name', 'My board')->value('uuid');

        $this->actingAs($reader)->get("/sales/bi/{$uuid}")->assertNotFound();            // private
        $this->actingAs($admin)->putJson("/sales/bi/{$uuid}", ['visibility' => 'shared', 'shared_roles' => ['read-only user']])->assertOk();
        $this->actingAs($reader)->get("/sales/bi/{$uuid}")->assertOk();                  // now shared with their role
        $this->actingAs($reader)->putJson("/sales/bi/{$uuid}", ['name' => 'Hijack'])->assertForbidden();
        $this->actingAs($reader)->post('/sales/bi', ['name' => 'Nope'])->assertForbidden(); // read-only cannot build
    }
}
