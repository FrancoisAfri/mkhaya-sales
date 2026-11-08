<?php

namespace Tests\Feature\Tenancy;

use App\Core\User\Models\User;
use App\Modules\Tenancy\Services\CurrentCompany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A platform administrator inside a module is never "every company at once".
 *
 * Row level security lets an empty session see every row, so an administrator who had not chosen a
 * company saw all tenants' tenders in one register, and what they created there was saved with no
 * company and disappeared from the register that wanted it. Inside a module they now work as their
 * own company; with none of their own, the session matches no company at all. Platform screens keep
 * the all-company view, because seeing every company is their job.
 */
class PlatformAdminInsideAModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Prove it through the session variable the middleware stamps, not only the app-level scope.
        config(['tenancy.stamp_rls_in_tests' => true]);
    }

    protected function tearDown(): void
    {
        DB::statement("SELECT set_config('app.company_id', '', false)");
        app(CurrentCompany::class)->clear();
        parent::tearDown();
    }

    private function admin(?int $companyId): User
    {
        $id = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(), 'email' => 'padmin-'.Str::random(8).'@example.test', 'password' => bcrypt('secret'),
            'company_id' => $companyId, 'is_platform_admin' => true, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::withoutGlobalScopes()->findOrFail($id);
    }

    private function dbCompany(): string
    {
        return (string) DB::selectOne("SELECT current_setting('app.company_id', true) AS c")->c;
    }

    public function test_inside_a_module_an_admin_works_as_their_own_company(): void
    {
        $own = (int) DB::table('companies')->min('id');

        $this->actingAs($this->admin($own))->get('/sheq/settings');

        $this->assertSame((string) $own, $this->dbCompany());
        $this->assertSame($own, app(CurrentCompany::class)->id());
    }

    public function test_an_admin_with_no_company_sees_no_tenant_inside_a_module(): void
    {
        $this->actingAs($this->admin(null))->get('/sheq/settings')->assertOk();

        $this->assertSame('0', $this->dbCompany(), 'An empty session would have shown every company at once.');
    }

    public function test_platform_screens_keep_the_all_company_view(): void
    {
        $own = (int) DB::table('companies')->min('id');

        $this->actingAs($this->admin($own))->get(route('tenancy.companies.index'));

        $this->assertSame('', $this->dbCompany());
    }
}
