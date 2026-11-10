<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\BI\BIQueryService;
use App\Modules\Sales\Services\BI\SalesSummary;
use Illuminate\Support\Facades\DB;

/** Gap 5: the monthly summary answers whole months with exactly the same figures, and only while current. */
class SalesSummaryTest extends SalesTestCase
{
    public function test_summary_answers_match_the_invoice_lines_and_go_stale_on_change(): void
    {
        [$co, $admin] = $this->tenant('Summary Co');
        $this->as($co->id);
        $a = Customer::create(['customer_code' => 'A', 'customer_name' => 'Alpha']);
        $rows = [];
        foreach (['2026-09-03', '2026-09-20', '2026-10-01', '2026-10-15', '2026-10-31'] as $i => $d) {
            $rows[] = ['company_id' => $co->id, 'transaction_date' => $d, 'customer_id' => $a->id, 'net_value' => 100 + $i, 'gross_value' => 100 + $i, 'quantity' => 2,
                'row_key' => "k{$i}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('sales_records')->insert($rows);
        $cfg = ['kind' => 'kpi', 'measures' => ['net_value', 'quantity', 'transactions', 'active_customers']];
        $q = fn () => $this->actingAs($admin)->postJson('/sales/bi/query', ['config' => $cfg, 'filters' => ['from' => '2026-09-01', 'to' => '2026-10-31']])->json('values');

        $raw = $q();
        $this->assertSame(2, SalesSummary::rebuild($co->id), "Two months, one customer: two summary rows.");
        $this->assertTrue(SalesSummary::ready($co->id));
        BIQueryService::invalidate($co->id, false);   // new cache version, summary still valid
        $this->assertEquals($raw, $q(), 'Same figures from the summary.');
        $this->assertSame(2, DB::table('sales_monthly_summary')->where('company_id', $co->id)->count());

        $this->assertFalse(SalesSummary::covers(['from' => '2026-09-05', 'to' => '2026-10-31'], [], [], '2026-10-31'), 'Not from the 1st: invoice lines.');
        $this->assertFalse(SalesSummary::covers(['from' => '2026-09-01', 'to' => '2026-10-15'], [], [], '2026-10-31'), 'Ends mid-month before the last sale: invoice lines.');
        $this->assertFalse(SalesSummary::covers(['from' => '2026-09-01', 'to' => '2026-10-31'], ['day'], [], '2026-10-31'));

        BIQueryService::invalidate($co->id);          // sales changed
        $this->assertFalse(SalesSummary::ready($co->id));
    }
}
