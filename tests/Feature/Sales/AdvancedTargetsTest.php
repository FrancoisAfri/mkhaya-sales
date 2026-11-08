<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Target;
use App\Modules\Sales\Services\TargetService;
use Illuminate\Support\Facades\DB;

/** Release 3 — formula, growth and composite targets (SRS §11.1, §11.4). */
class AdvancedTargetsTest extends SalesTestCase
{
    public function test_formula_growth_and_composite_targets(): void
    {
        [$co, $admin] = $this->tenant('Adv Co');
        $this->as($co->id);
        $c = Customer::create(['customer_code' => 'C1', 'customer_name' => 'A']);
        $sale = fn ($d, $v, $q) => ['company_id' => $co->id, 'transaction_date' => $d, 'customer_id' => $c->id, 'net_value' => $v, 'gross_value' => $v,
            'quantity' => $q, 'row_key' => md5($d . $v), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
        DB::table('sales_records')->insert([$sale('2025-11-05', 1000, 20), $sale('2026-11-05', 1100, 20)]);
        $this->travelTo('2026-11-20 10:00');
        $base = ['period_type' => 'monthly', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'approved', 'created_by' => $admin->id];
        $svc = app(TargetService::class);

        // Average selling price = net_value / quantity = 55; target 50 → achieved.
        $asp = Target::create($base + ['name' => 'ASP', 'metric' => 'formula', 'formula' => 'net_value / quantity', 'method' => 'fixed', 'target_value' => 50]);
        $this->assertEquals(55, $svc->evaluate($asp)['actual']);
        $this->assertSame('achieved', $svc->evaluate($asp)['status']);

        // +10% on last year: actual growth is exactly 10%.
        $growth = Target::create($base + ['name' => 'Growth', 'metric' => 'net_value', 'method' => 'growth', 'target_value' => 10]);
        $p = $svc->evaluate($growth);
        $this->assertEquals(10.0, $p['actual']);
        $this->assertSame('achieved', $p['status']);

        // Composite of the two, weights 3:1, both 110% achieved and 100% → (110*3 + 100*1)/4 = 107.5.
        $composite = Target::create($base + ['name' => 'Scorecard', 'metric' => 'net_value', 'method' => 'composite', 'target_value' => 100]);
        $asp->update(['parent_id' => $composite->id, 'weight' => 3]);
        $growth->update(['parent_id' => $composite->id, 'weight' => 1]);
        $this->assertEquals(107.5, $svc->evaluate($composite)['actual']);

        // A formula that tries anything else is refused.
        $this->actingAs($admin)->post('/sales/targets', ['name' => 'Bad', 'metric' => 'formula', 'formula' => 'exec(1)', 'method' => 'fixed', 'period_type' => 'monthly',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'target_value' => 1])->assertSessionHasErrors('formula');
        $this->actingAs($admin)->get("/sales/targets/{$composite->uuid}")->assertOk();
    }
}
