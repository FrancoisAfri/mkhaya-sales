<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Row-level security for a tenant-owned table, the same policy every company table carries:
 * a request pinned to a company (app.company_id) sees and writes only that company's rows;
 * a session with no company set (console, platform admin) is not filtered.
 */
final class TenantRls
{
    public static function enable(string $table, string $column = 'company_id'): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $match = "(NULLIF(current_setting('app.company_id', true), '') IS NULL) OR ({$column} = NULLIF(current_setting('app.company_id', true), '')::bigint)";

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("CREATE POLICY tenant_isolation ON {$table} USING ({$match}) WITH CHECK ({$match})");
    }
}
