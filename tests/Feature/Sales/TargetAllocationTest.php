<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Region;
use App\Modules\Sales\Models\Target;
use Illuminate\Support\Facades\DB;

/** Release 2 — target allocation (SRS §11.5). */
class TargetAllocationTest extends SalesTestCase
{
    public function test_a_target_is_split_by_last_years_mix_and_children_add_up(): void
    {
        [$co, $admin] = $this->tenant('Alloc Co');
        $this->as($co->id);
        $gp = Region::create(['name' => 'Gauteng']);
        $wc = Region::create(['name' => 'Western Cape']);
        $c = Customer::create(['customer_code' => 'C1', 'customer_name' => 'A']);
        foreach ([[$gp, 750], [$wc, 250]] as [$r, $v]) {
            DB::table('sales_records')->insert(['company_id' => $co->id, 'transaction_date' => '2025-11-10', 'customer_id' => $c->id, 'region_id' => $r->id,
                'net_value' => $v, 'gross_value' => $v, 'row_key' => md5($r->id . $v), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $parent = Target::create(['name' => 'Nov', 'metric' => 'net_value', 'method' => 'fixed', 'period_type' => 'monthly', 'start_date' => '2026-11-01',
            'end_date' => '2026-11-30', 'target_value' => 100000.01, 'status' => 'approved', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post("/sales/targets/{$parent->uuid}/allocate", ['dimension' => 'region_id', 'method' => 'historical', 'members' => [$gp->id, $wc->id]])
            ->assertSessionHasNoErrors();
        $kids = Target::where('parent_id', $parent->id)->orderByDesc('target_value')->get();
        $this->assertCount(2, $kids);
        $this->assertEqualsWithDelta(75000, $kids[0]->target_value, 0.02);
        $this->assertEqualsWithDelta(100000.01, $kids->sum('target_value'), 0.001, 'Children must add up exactly to the parent.');
        $this->assertSame($gp->id, $kids[0]->dimensions['region_id']);
        $this->assertSame('draft', $kids[0]->status);

        // Percentages that do not add to 100 are refused; equal re-allocation replaces the drafts.
        $this->actingAs($admin)->post("/sales/targets/{$parent->uuid}/allocate", ['dimension' => 'region_id', 'method' => 'percentage', 'members' => [$gp->id, $wc->id], 'given' => [$gp->id => 60, $wc->id => 30]])
            ->assertSessionHasErrors('target');
        $this->actingAs($admin)->post("/sales/targets/{$parent->uuid}/allocate", ['dimension' => 'region_id', 'method' => 'equal', 'members' => [$gp->id, $wc->id]]);
        $this->assertSame(2, Target::where('parent_id', $parent->id)->count());
        $this->assertTrue(app(\App\Modules\Sales\Services\TargetService::class)->reconciliation($parent)['balanced']);
        $this->actingAs($admin)->get("/sales/targets/{$parent->uuid}")->assertOk();
    }
}
