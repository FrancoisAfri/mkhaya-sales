<?php

namespace App\Console\Commands\Tenancy;

use App\Modules\Tenancy\Services\IsolationAudit;
use Illuminate\Console\Command;

/** Report, from the live database, anything that would let one company's data reach another. */
class AuditIsolation extends Command
{
    protected $signature = 'tenancy:audit {--skip-links : Skip the cross-company link scan (slow on large databases)}';

    protected $description = 'Check that company data is isolated: database role, row level security, orphaned and cross-company rows';

    public function handle(IsolationAudit $audit): int
    {
        $failed = false;

        $role = $audit->role();
        if ($role['superuser'] || $role['bypassrls']) {
            $this->error("CRITICAL  The application connects as '{$role['role']}', which ".($role['superuser'] ? 'is a superuser' : 'has BYPASSRLS')
                .'. Row level security does not apply to it at all, so every company can see every other. Connect as an ordinary role.');
            $failed = true;
        } else {
            $this->info("ok        Database role '{$role['role']}' is subject to row level security.");
        }

        $tables = $audit->unprotectedTables();
        if ($tables) {
            $this->error('FAIL      Company tables without row level security: '.implode(', ', $tables));
            $failed = true;
        } else {
            $this->info('ok        Every company table is isolated (or exempt for a stated reason).');
        }

        $orphans = $audit->orphans();
        if ($orphans) {
            $this->warn('WARN      Rows with no company (no tenant can see them; adopt them with tenancy:adopt-orphans):');
            $this->table(['Table', 'Rows'], collect($orphans)->map(fn ($n, $t) => [$t, $n])->values()->all());
        } else {
            $this->info('ok        No orphaned rows in isolated tables.');
        }

        if (! $this->option('skip-links')) {
            $links = $audit->crossCompanyLinks();
            if ($links) {
                $this->warn('WARN      Rows linked to a record in a different company:');
                $this->table(['Table', 'Column', 'Points at', 'Rows'], array_map(fn ($l) => array_values($l), $links));
            } else {
                $this->info('ok        No row points at another company\'s record.');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
