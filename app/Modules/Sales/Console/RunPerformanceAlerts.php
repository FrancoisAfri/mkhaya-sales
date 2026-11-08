<?php

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Services\PerformanceAlerts;
use App\Modules\Tenancy\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Runs every minute; each company's alerts go out once a day at its configured time (default 07:00 SAST). */
class RunPerformanceAlerts extends Command
{
    protected $signature = 'sales:alerts {--company= : Run now for one company}';

    protected $description = 'Raise target and unusual-sales alerts';

    public function handle(TenantContext $tenants, PerformanceAlerts $alerts): int
    {
        $now = now()->setTimezone('Africa/Johannesburg');
        $companies = $this->option('company')
            ? [(int) $this->option('company')]
            : DB::table('sales_records')->distinct()->pluck('company_id')->all();

        foreach ($companies as $id) {
            if (! $this->option('company') && PerformanceAlerts::settingsFor($id)['run_time'] !== $now->format('H:i')) {
                continue;
            }
            $raised = $tenants->run($id, fn () => $alerts->run($id));
            $this->line("Company {$id}: " . (count($raised) ? implode('; ', $raised) : 'nothing to report'));
        }

        return self::SUCCESS;
    }
}
