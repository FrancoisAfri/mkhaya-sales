<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Product;
use App\Modules\Sales\Models\Target;
use App\Modules\Sales\Models\TargetApprovalRule;
use App\Modules\Sales\Services\TargetService;
use Illuminate\Support\Facades\DB;

/** Phases 6–7 — SRS BI-001, REP-001, TGT-001 and acceptance scenarios 10–17. */
class InsightsAndTargetsTest extends SalesTestCase
{
    private function seedSales(int $companyId): void
    {
        $this->as($companyId);
        $c1 = Customer::create(['customer_code' => 'C1', 'customer_name' => 'Spar']);
        $c2 = Customer::create(['customer_code' => 'C2', 'customer_name' => 'Checkers']);
        $p = Product::create(['product_code' => 'P1', 'product_name' => 'Pizza base']);
        $row = fn ($date, $c, $v) => ['company_id' => $companyId, 'transaction_date' => $date, 'customer_id' => $c->id, 'product_id' => $p->id,
            'quantity' => 1, 'gross_value' => $v, 'net_value' => $v, 'row_key' => md5($date . $c->id . $v . microtime()), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now()];
        DB::table('sales_records')->insert([
            $row('2025-11-03', $c1, 800), $row('2025-11-04', $c2, 400),          // last year
            $row('2026-11-02', $c1, 1000), $row('2026-11-03', $c2, 200),          // this year
            $row('2026-10-15', $c1, 5000),                                        // previous month
        ]);
    }

    public function test_dashboards_and_reports_agree_and_compare_with_last_year(): void
    {
        [$co, $admin] = $this->tenant('BI Co');
        $this->seedSales($co->id);
        $q = '?from=2026-11-01&to=2026-11-07';

        $page = $this->actingAs($admin)->get('/sales/dashboard' . $q)->assertOk()->viewData('page')['props'];
        $this->assertEquals(1200, $page['kpis']['total']);
        $this->assertEquals(1200, $page['kpis']['previous_year']);
        $this->assertEquals(0.0, $page['kpis']['yoy_growth']);
        $this->assertSame('Spar', $page['topCustomers'][0]['customer']);
        $this->assertSame('Checkers', $page['decliningCustomers'][0]['customer'], 'Checkers fell from 400 to 200.');

        $report = $this->actingAs($admin)->get('/sales/reports' . $q . '&report=customer_ranking')->assertOk()->viewData('page')['props']['result'];
        $this->assertEquals(1200, $report['total'], 'Report and dashboard must agree.');
        $this->assertEquals(50.0, $report['rows'][1]['growth'] * -1);

        foreach (['xlsx', 'csv', 'pdf'] as $format) {
            $this->actingAs($admin)->get("/sales/reports/export/{$format}{$q}&report=by_customer")->assertOk();
        }
        $this->assertSame(3, DB::table('report_exports')->where('company_id', $co->id)->count());

        $this->actingAs($admin)->get('/sales/dashboard/customers' . $q)->assertOk();
        $this->actingAs($admin)->get('/sales/dashboard/products' . $q)->assertOk();
        $this->actingAs($admin)->get('/sales/forecast')->assertOk();
    }

    public function test_a_read_only_user_cannot_export(): void
    {
        [$co] = $this->tenant('NoExport Co');
        $reader = $this->member($co->id, 'read-only user');
        $this->actingAs($reader)->get('/sales/reports')->assertOk();
        $this->actingAs($reader)->get('/sales/reports/export/csv')->assertForbidden();
    }

    public function test_the_approvals_matrix_routes_a_large_target_through_two_steps_and_never_the_creator(): void
    {
        [$co, $orgAdmin] = $this->tenant('Matrix Co');
        $manager = $this->member($co->id, 'sales manager');
        $exec = $this->member($co->id, 'executive');
        \Spatie\Permission\Models\Role::findByName('executive', 'web')->givePermissionTo('targets.approve');
        $this->as($co->id);

        // Matrix: anything is approved by a sales manager; above R1m the executive signs off next.
        $this->actingAs($orgAdmin)->post('/sales/targets/matrix', ['name' => 'Managers', 'step' => 1, 'approver_type' => 'role', 'approver_role' => 'sales manager', 'is_active' => true])->assertSessionHasNoErrors();
        $this->actingAs($orgAdmin)->post('/sales/targets/matrix', ['name' => 'Big targets', 'min_value' => 1000000, 'step' => 2, 'approver_type' => 'user', 'approver_user_id' => $exec->id, 'is_active' => true])->assertSessionHasNoErrors();

        $this->actingAs($manager)->post('/sales/targets', [
            'name' => 'November national', 'metric' => 'net_value', 'method' => 'fixed', 'period_type' => 'monthly',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'target_value' => 1500000, 'submit_now' => true,
        ])->assertSessionHasNoErrors();
        $target = Target::withoutGlobalScopes()->where('company_id', $co->id)->firstOrFail();
        $this->assertSame('submitted', $target->status);

        $service = app(TargetService::class);
        $this->assertFalse($service->canApprove($manager, $target), 'The creator must not approve their own target.');

        $otherManager = $this->member($co->id, 'sales manager');
        $this->actingAs($exec)->post("/sales/targets/{$target->uuid}/decide", ['decision' => 'approve'])->assertSessionHasErrors('target');
        $this->actingAs($otherManager)->post("/sales/targets/{$target->uuid}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $target->fresh()->status, 'Step 2 is still outstanding.');
        $this->actingAs($exec)->post("/sales/targets/{$target->uuid}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $target->fresh()->status);
        $this->assertSame(2, DB::table('target_approvals')->where('target_id', $target->id)->count());

        // A small target only needs step 1.
        $small = Target::create(['name' => 'Spar Nov', 'metric' => 'net_value', 'method' => 'fixed', 'period_type' => 'monthly', 'start_date' => '2026-11-01',
            'end_date' => '2026-11-30', 'target_value' => 50000, 'created_by' => $orgAdmin->id, 'status' => 'submitted']);
        $this->assertSame(1, $service->steps($small)->count());
    }

    public function test_a_revision_keeps_the_history_and_goes_back_for_approval(): void
    {
        [$co, $admin] = $this->tenant('Revise Co');
        $approver = $this->member($co->id, 'sales manager');
        $this->as($co->id);
        $t = Target::create(['name' => 'Q4', 'metric' => 'quantity', 'method' => 'fixed', 'period_type' => 'quarterly', 'start_date' => '2026-10-01',
            'end_date' => '2026-12-31', 'target_value' => 1000, 'created_by' => $admin->id, 'status' => 'approved', 'approved_at' => now()]);

        $this->actingAs($admin)->post("/sales/targets/{$t->uuid}/revise", ['target_value' => 1200, 'reason' => 'New listing at Shoprite'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame('submitted', $t->status);
        $this->assertSame(2, $t->version);
        $rev = DB::table('target_revisions')->where('target_id', $t->id)->first();
        $this->assertEquals(1000, (float) $rev->previous_value);
        $this->assertEquals(1200, (float) $rev->new_value);

        $this->actingAs($approver)->post("/sales/targets/{$t->uuid}/decide", ['decision' => 'reject'])->assertSessionHasErrors('comment');
    }

    public function test_target_performance_against_actual_sales(): void
    {
        [$co, $admin] = $this->tenant('Perf Co');
        $this->seedSales($co->id);
        $spar = Customer::where('customer_code', 'C1')->first();
        $t = Target::create(['name' => 'Spar Nov', 'metric' => 'net_value', 'method' => 'fixed', 'period_type' => 'monthly',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'target_value' => 4000, 'dimensions' => ['customer_id' => $spar->id],
            'created_by' => $admin->id, 'status' => 'approved']);
        $this->travelTo('2026-11-10 12:00');

        $p = app(TargetService::class)->evaluate($t, $admin);
        $this->assertEquals(1000, $p['actual']);
        $this->assertEquals(25.0, $p['achievement']);
        $this->assertEquals(-3000, $p['variance']);
        $this->assertEquals(3000, $p['remaining']);
        $this->assertSame(20, $p['days_left']);
        $this->assertEquals(150, $p['required_daily']);
        $this->assertSame('behind', $p['status']);

        $this->actingAs($admin)->get('/sales/dashboard/targets?from=2026-11-01&to=2026-11-30')->assertOk();
        $this->actingAs($admin)->get("/sales/targets/{$t->uuid}")->assertOk();
    }
}
