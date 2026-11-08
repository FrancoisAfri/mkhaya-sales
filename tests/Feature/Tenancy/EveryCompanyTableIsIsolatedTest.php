<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Services\IsolationAudit;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every table that carries a company_id is isolated by row level security, enabled and forced, with
 * a policy — or is named below with the reason it is not.
 *
 * Isolation was checked module by module (finance, manufacturing, project management…), so a table
 * in a module nobody wrote that check for could ship without it, and 40 did: asset work orders,
 * SHEQ injury cases, the activity log that holds every model's old and new values. One check over
 * the whole schema means a new table is isolated, or somebody has written down why not.
 */
class EveryCompanyTableIsIsolatedTest extends TestCase
{
    public function test_every_company_table_is_isolated_or_exempt_for_a_reason(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Row level security is PostgreSQL.');
        }

        $unprotected = app(IsolationAudit::class)->unprotectedTables();

        $this->assertSame([], $unprotected,
            "These company tables have no row level security. Isolate them in a migration, or add them to IsolationAudit::EXEMPT with the reason:\n  "
            .implode("\n  ", $unprotected));
    }

    public function test_the_audit_command_passes_on_the_migrated_schema(): void
    {
        $this->artisan('tenancy:audit', ['--skip-links' => true])->assertSuccessful();
    }
}
