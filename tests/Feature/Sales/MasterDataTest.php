<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Product;
use App\Modules\Sales\Models\ProductCategory;

/** Phase 2 — SRS MDM-001/002. */
class MasterDataTest extends SalesTestCase
{
    public function test_a_data_administrator_maintains_products_with_codes_unique_per_company(): void
    {
        [$a] = $this->tenant('Master A');
        [$b] = $this->tenant('Master B');
        $adminA = $this->member($a->id, 'data administrator');
        $adminB = $this->member($b->id, 'data administrator');

        $this->actingAs($adminA)->post('/sales/master/categories', ['name' => 'Baking', 'code' => 'BAK', 'is_active' => true])->assertRedirect();
        $cat = ProductCategory::withoutGlobalScopes()->where('company_id', $a->id)->where('code', 'BAK')->firstOrFail();

        $this->actingAs($adminA)->post('/sales/master/products', [
            'product_code' => 'P-100', 'product_name' => 'Pizza base', 'category_id' => $cat->id, 'is_active' => true, 'currency' => 'ZAR',
        ])->assertSessionHasNoErrors();

        // Same code again in the same company: refused.
        $this->actingAs($adminA)->post('/sales/master/products', ['product_code' => 'P-100', 'product_name' => 'Dup'])
            ->assertSessionHasErrors('product_code');

        // Same code in another company: allowed.
        $this->actingAs($adminB)->post('/sales/master/products', ['product_code' => 'P-100', 'product_name' => 'Other co'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Product::withoutGlobalScopes()->where('product_code', 'P-100')->count());
    }

    public function test_one_company_never_sees_another_companys_products(): void
    {
        [$a, $adminA] = $this->tenant('List A');
        [$b, $adminB] = $this->tenant('List B');
        $this->as($b->id);
        Product::create(['product_code' => 'SECRET-B', 'product_name' => 'Company B only']);

        $page = $this->actingAs($adminA)->get('/sales/master/products')->assertOk()->viewData('page');
        $this->assertStringNotContainsString('SECRET-B', json_encode($page['props']['rows']));

        $productB = Product::withoutGlobalScopes()->where('product_code', 'SECRET-B')->first();
        $this->actingAs($adminA)->put("/sales/master/products/{$productB->id}", ['product_code' => 'X', 'product_name' => 'Y'])->assertNotFound();
        $this->actingAs($adminA)->get('/sales/master/products/export')->assertOk()->assertDontSee('SECRET-B');
    }

    public function test_read_only_users_cannot_change_master_data(): void
    {
        [$a] = $this->tenant('Perm A');
        $reader = $this->member($a->id, 'read-only user');

        $this->actingAs($reader)->get('/sales/master/products')->assertOk();
        $this->actingAs($reader)->post('/sales/master/products', ['product_code' => 'NO', 'product_name' => 'No'])->assertForbidden();
        $this->actingAs($reader)->get('/sales/master/products/export')->assertForbidden();
    }

    public function test_deactivating_keeps_the_record(): void
    {
        [$a, $admin] = $this->tenant('Deact A');
        $this->as($a->id);
        $p = Product::create(['product_code' => 'KEEP', 'product_name' => 'Keep me']);

        $this->actingAs($admin)->delete("/sales/master/products/{$p->id}")->assertRedirect();
        $this->assertFalse((bool) $p->fresh()->is_active);
    }
}
