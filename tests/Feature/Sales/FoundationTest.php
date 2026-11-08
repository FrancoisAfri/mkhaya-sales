<?php

namespace Tests\Feature\Sales;

use App\Core\User\Models\User;
use App\Modules\Tenancy\Models\Subscription;
use App\Modules\Tenancy\Services\CompanyProvisioner;
use App\Modules\Tenancy\Services\TenantContext;
use Database\Seeders\SalesAccessSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 1 — SRS ARC-001, IAM-001, SUB-001. */
class FoundationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SalesAccessSeeder::class);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->apply(null);
        parent::tearDown();
    }

    private function tenant(string $name): array
    {
        $r = app(CompanyProvisioner::class)->provision([
            'company_name' => $name,
            'email' => Str::slug($name).'-'.Str::random(6).'@test.local',
            'password' => 'Secret-Pass-2026!',
            'admin_name' => "{$name} Admin",
            'plan' => null,
            'trial_days' => 0,
        ]);

        return [$r['company'], $r['user']];
    }

    public function test_the_starting_roles_carry_the_spec_permissions(): void
    {
        $role = fn (string $n) => \Spatie\Permission\Models\Role::findByName($n, 'web');

        $this->assertTrue($role('data administrator')->hasPermissionTo('imports.historical'));
        $this->assertFalse($role('data administrator')->hasPermissionTo('targets.approve'));
        $this->assertTrue($role('sales manager')->hasPermissionTo('targets.approve'));
        $this->assertFalse($role('read-only user')->hasPermissionTo('reports.export'));
        $this->assertFalse($role('read-only user')->hasPermissionTo('products.manage'));
        $this->assertTrue($role('organisation administrator')->hasPermissionTo('integrations.manage'));
        $this->assertFalse(\Spatie\Permission\Models\Role::where('name', 'like', 'WMS%')->exists(), 'Hub leftovers remain.');
    }

    public function test_vector_gets_its_contract_terms(): void
    {
        $this->artisan('sales:provision-vector', ['--admin-email' => 'vector-'.Str::random(6).'@test.local', '--start' => '2026-11-08'])
            ->assertSuccessful();

        $company = \App\Modules\Tenancy\Models\Company::where('name', 'Vector Logistics')->firstOrFail();
        $sub = Subscription::where('company_id', $company->id)->firstOrFail();

        $this->assertSame('vector_sales', $sub->plan_code);
        $this->assertEquals(35000, (float) $sub->monthly_price);
        $this->assertSame('2026-11-08', $sub->contract_start->toDateString());
        $this->assertSame('2027-11-07', $sub->contract_end->toDateString());
        $this->assertEquals(1250, (float) $sub->change_request_hourly_rate);
        $this->assertSame('active', $company->status);
    }

    public function test_data_scopes_are_isolated_by_row_level_security(): void
    {
        [$a, $adminA] = $this->tenant('Scope Co A');
        [$b, $adminB] = $this->tenant('Scope Co B');

        DB::table('user_data_scopes')->insert([
            ['company_id' => $a->id, 'user_id' => $adminA->id, 'scope_type' => 'region', 'scope_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $b->id, 'user_id' => $adminB->id, 'scope_type' => 'region', 'scope_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // The app skips the RLS session variable under phpunit, so set it the way a request does.
        DB::statement("SELECT set_config('app.company_id', ?, true)", [(string) $a->id]);
        $visible = DB::table('user_data_scopes')->whereIn('company_id', [$a->id, $b->id])->pluck('company_id')->unique()->values()->all();
        $this->assertSame([$a->id], $visible, 'Company A could read company B\'s data scopes.');

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('user_data_scopes')->insert([
            'company_id' => $b->id, 'user_id' => $adminB->id, 'scope_type' => 'region', 'scope_id' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_tenant_admin_sees_only_their_own_users(): void
    {
        [, $adminA] = $this->tenant('Web Co A');
        [, $adminB] = $this->tenant('Web Co B');

        $response = $this->actingAs($adminA)->get('/settings/manage-users');
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props'] ?? []);
        $this->assertStringContainsString($adminA->email, $json);
        $this->assertStringNotContainsString($adminB->email, $json, 'Company B\'s user leaked into company A\'s user list.');
    }

    public function test_a_tenant_user_cannot_open_another_tenants_profile(): void
    {
        [, $adminA] = $this->tenant('Profile Co A');
        [, $adminB] = $this->tenant('Profile Co B');
        $uuidB = DB::table('hr_people')->where('user_id', $adminB->id)->value('uuid');

        $status = $this->actingAs($adminA)->get("/user/edit/{$uuidB}")->status();

        $this->assertContains($status, [403, 404], "Company A opened company B's profile (HTTP {$status}).");
    }
}
