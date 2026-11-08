<?php

namespace Tests\Feature\Sales;

use App\Core\User\Models\User;
use App\Modules\Tenancy\Services\CompanyProvisioner;
use App\Modules\Tenancy\Services\CurrentCompany;
use App\Modules\Tenancy\Services\TenantContext;
use Database\Seeders\SalesAccessSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class SalesTestCase extends TestCase
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

    /** @return array{0: \App\Modules\Tenancy\Models\Company, 1: User} company and its organisation administrator */
    protected function tenant(string $name = 'Test Co'): array
    {
        $r = app(CompanyProvisioner::class)->provision([
            'company_name' => $name,
            'email' => Str::slug($name) . '-' . Str::random(6) . '@test.local',
            'password' => 'Secret-Pass-2026!',
            'admin_name' => "{$name} Admin",
            'plan' => null,
            'trial_days' => 0,
        ]);
        $r['user']->assignRole('organisation administrator');

        return [$r['company'], $r['user']];
    }

    /** A further user in the company holding one of the spec roles. */
    protected function member(int $companyId, string $role): User
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'email' => Str::slug($role) . '-' . Str::random(6) . '@test.local',
            'password' => bcrypt('Secret-Pass-2026!'),
            'status' => 1,
            'company_id' => $companyId,
            'password_changed_at' => now(),
        ]);
        DB::table('hr_people')->insert([
            'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'first_name' => ucfirst($role), 'surname' => 'Tester', 'email' => $user->email, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Work as this company outside a request (fixtures, services). */
    protected function as(int $companyId): void
    {
        app(CurrentCompany::class)->set($companyId);
    }
}
