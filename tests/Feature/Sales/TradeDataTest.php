<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Jobs\ProcessImport;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Product;
use App\Modules\Sales\Models\ProductCategory;
use App\Modules\Sales\Services\ImportEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Gap 7: stock, promotions and price lists load through the Import Centre and are set against sales. */
class TradeDataTest extends SalesTestCase
{
    public function test_stock_promotions_and_prices_are_imported_and_analysed(): void
    {
        Notification::fake();
        [$co, $admin] = $this->tenant('Trade Co');
        $this->as($co->id);
        $pizza = ProductCategory::create(['name' => 'Pizza']);
        $p = Product::create(['product_code' => 'P1', 'product_name' => 'Margherita', 'category_id' => $pizza->id]);
        $c = Customer::create(['customer_code' => 'C1', 'customer_name' => 'Spar Rosebank']);
        $last = now()->toDateString();
        $rows = [];
        for ($d = 0; $d < 28; $d++) {   // 2 cases a day at R100 a case for 28 days
            $rows[] = ['company_id' => $co->id, 'transaction_date' => now()->subDays($d)->toDateString(), 'customer_id' => $c->id, 'product_id' => $p->id,
                'net_value' => 200, 'gross_value' => 200, 'quantity' => 2, 'row_key' => "r{$d}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('sales_records')->insert($rows);
        $engine = app(ImportEngine::class);
        $load = function (string $target, string $csv) use ($engine, $admin) {
            $b = $engine->receive($target, "{$target}.csv", $csv, ['uploaded_by' => $admin->id]);
            ProcessImport::run($b, 'stage', $admin->id, true);   // clean files commit on their own
            $b = $b->fresh();
            if ($b->status !== 'committed') {
                $engine->commit($b, $admin);
            }

            return $b;
        };

        $stock = $load('stock', "Stock date,Product code,Cases on hand\n{$last},P1,20\n{$last},NOPE,5\n");
        $this->assertSame(1, (int) $stock->invalid_rows, 'An unknown product is refused, not invented.');
        $promo = $load('promotions', "Promotion,Start,End,Category,Discount %\nPizza week," . now()->subDays(6)->toDateString() . ",{$last},Pizza,15\n");
        $price = $load('prices', "Product code,Price per case,Valid from\nP1,125," . now()->startOfYear()->toDateString() . "\n");

        $props = $this->actingAs($admin)->get('/sales/trade')->assertOk()->viewData('page')['props'];
        $this->assertEquals(10.0, $props['stock'][0]['cover_days'], '20 cases ÷ 2 a day = 10 days.');
        $this->assertSame('Pizza week', $props['promotions'][0]['name']);
        $this->assertEquals(0.0, $props['promotions'][0]['uplift'], 'Flat sales: no uplift.');
        $this->assertEquals(80.0, $props['prices'][0]['realisation'], 'R100 achieved against R125 list.');
    }
}
