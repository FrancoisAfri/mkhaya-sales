<?php

namespace App\Modules\Tenancy\Services;

use Illuminate\Support\Facades\DB;

/**
 * Checks, against the live database, that one company's rows cannot reach another.
 *
 * The test suite proves the schema it migrates; production is a schema plus years of data and a
 * database role somebody else configured. This reads what is actually there: whether the role the
 * application connects as bypasses row level security (which switches every policy off at once),
 * which company tables have no policy, which rows belong to no company, and which rows point at a
 * parent that belongs to a different company.
 */
class IsolationAudit
{
    /** Company tables deliberately not row-level isolated, and why (mirrored by EveryCompanyTableIsIsolatedTest). */
    public const EXEMPT = [
        'users' => 'sign-in', 'company_user_pivots' => 'sign-in',
        'company_addons' => 'platform billing', 'company_modules' => 'platform billing', 'company_monthly_credits' => 'platform billing',
        'company_storage_credits' => 'platform billing', 'company_storage_stats' => 'platform billing', 'subscriptions' => 'platform billing',
        'subscription_invoices' => 'platform billing', 'payments' => 'platform billing', 'reseller_commissions' => 'platform billing',
        'token_requests' => 'platform billing',
        'reporting_logins' => 'reporting',
        'content_network_links' => 'cross-company network', 'content_network_pages' => 'cross-company network',
        'content_network_profiles' => 'cross-company network',
    ];

    /** Links that cross companies by design: a payroll bureau keeps its clients' leave set-up. */
    private const CROSS_BY_DESIGN = ['bureau_companies'];

    /** @return array{superuser: bool, bypassrls: bool, role: string} */
    public function role(): array
    {
        $r = DB::selectOne('SELECT current_user AS role, rolsuper AS su, rolbypassrls AS bypass FROM pg_roles WHERE rolname = current_user');

        return ['role' => (string) $r->role, 'superuser' => (bool) $r->su, 'bypassrls' => (bool) $r->bypass];
    }

    /** @return list<string> company tables with no enabled, forced, policied row level security */
    public function unprotectedTables(): array
    {
        return collect(DB::select("
            SELECT c.relname AS name FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
              AND EXISTS (SELECT 1 FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attname = 'company_id' AND NOT a.attisdropped)
              AND (NOT c.relrowsecurity OR NOT c.relforcerowsecurity
                   OR NOT EXISTS (SELECT 1 FROM pg_policies p WHERE p.schemaname = 'public' AND p.tablename = c.relname))
            ORDER BY 1"))->pluck('name')->diff(array_keys(self::EXEMPT))->values()->all();
    }

    /**
     * Rows with no company in isolated business tables: invisible to every tenant.
     *
     * @return array<string, int> table => rows
     */
    public function orphans(int $limit = 40): array
    {
        $tables = collect(DB::select("
            SELECT c.relname AS name FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relrowsecurity
              AND EXISTS (SELECT 1 FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attname = 'company_id' AND NOT a.attisdropped)
              AND NOT EXISTS (SELECT 1 FROM pg_policies p WHERE p.tablename = c.relname AND p.policyname = 'tenant_read')
            ORDER BY 1"))->pluck('name')->diff(array_keys(self::EXEMPT));

        $found = [];
        foreach ($tables as $t) {
            $n = (int) DB::selectOne("SELECT count(*) AS n FROM \"{$t}\" WHERE company_id IS NULL")->n;
            if ($n > 0) {
                $found[$t] = $n;
            }
        }
        arsort($found);

        return array_slice($found, 0, $limit, true);
    }

    /**
     * Rows whose parent (by foreign key) belongs to a different company.
     *
     * @return list<array{child: string, column: string, parent: string, rows: int}>
     */
    public function crossCompanyLinks(): array
    {
        $fks = DB::select("
            SELECT tc.relname AS child, a.attname AS col, pc.relname AS parent, pa.attname AS pcol
            FROM pg_constraint c
            JOIN pg_class tc ON tc.oid = c.conrelid JOIN pg_class pc ON pc.oid = c.confrelid
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
            JOIN pg_attribute pa ON pa.attrelid = c.confrelid AND pa.attnum = c.confkey[1]
            WHERE c.contype = 'f' AND array_length(c.conkey, 1) = 1 AND tc.relnamespace = 'public'::regnamespace
              AND a.attname <> 'company_id' AND pc.relname <> 'companies'
              AND EXISTS (SELECT 1 FROM pg_attribute x WHERE x.attrelid = c.conrelid AND x.attname = 'company_id' AND NOT x.attisdropped)
              AND EXISTS (SELECT 1 FROM pg_attribute x WHERE x.attrelid = c.confrelid AND x.attname = 'company_id' AND NOT x.attisdropped)");

        $found = [];
        foreach ($fks as $fk) {
            if (in_array($fk->parent, self::CROSS_BY_DESIGN, true) || isset(self::EXEMPT[$fk->parent]) || isset(self::EXEMPT[$fk->child])) {
                continue;
            }
            $n = (int) DB::selectOne("SELECT count(*) AS n FROM \"{$fk->child}\" c JOIN \"{$fk->parent}\" p ON p.\"{$fk->pcol}\" = c.\"{$fk->col}\"
                WHERE c.company_id IS NOT NULL AND p.company_id IS NOT NULL AND c.company_id <> p.company_id")->n;
            if ($n > 0) {
                $found[] = ['child' => $fk->child, 'column' => $fk->col, 'parent' => $fk->parent, 'rows' => $n];
            }
        }

        return $found;
    }
}
