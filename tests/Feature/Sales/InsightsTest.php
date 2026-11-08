<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Product;
use App\Modules\Sales\Services\InsightsService;
use Illuminate\Support\Facades\DB;

/** Release 3 — anomaly detection and product lifecycle. */
class InsightsTest extends SalesTestCase
{
    public function test_a_customer_that_stopped_buying_is_flagged_and_products_are_classified(): void
    {
        [$co, $admin] = $this->tenant('Insight Co');
        $this->as($co->id);
        $steady = Customer::create(['customer_code' => 'S', 'customer_name' => 'Steady']);
        $gone = Customer::create(['customer_code' => 'G', 'customer_name' => 'Gone quiet']);
        $old = Product::create(['product_code' => 'OLD', 'product_name' => 'Old product']);
        $new = Product::create(['product_code' => 'NEW', 'product_name' => 'New product']);
        $mid = Product::create(['product_code' => 'MID', 'product_name' => 'Other product']);
        $rows = [];
        for ($d = 0; $d < 120; $d++) {
            $date = now()->subDays($d)->toDateString();
            $rows[] = ['company_id' => $co->id, 'transaction_date' => $date, 'customer_id' => $steady->id, 'product_id' => $d < 70 ? $new->id : $old->id,
                'net_value' => 100, 'gross_value' => 100, 'row_key' => "s{$d}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
            if ($d >= 28) {
                $rows[] = ['company_id' => $co->id, 'transaction_date' => $date, 'customer_id' => $gone->id, 'product_id' => $mid->id,
                    'net_value' => 100, 'gross_value' => 100, 'row_key' => "g{$d}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('sales_records')->insert($rows);

        $a = collect(app(InsightsService::class)->anomalies($admin)['items']);
        $this->assertSame('stopped', $a->firstWhere('name', 'Gone quiet')['kind']);
        $this->assertNull($a->firstWhere('name', 'Steady'), 'A steady customer is not unusual.');

        $stages = collect(app(InsightsService::class)->lifecycle($admin)['products'])->pluck('stage', 'product');
        $this->assertSame('new', $stages['New product']);
        $this->assertSame('dormant', $stages['Old product']);

        $this->actingAs($admin)->get('/sales/insights')->assertOk();
    }
}
