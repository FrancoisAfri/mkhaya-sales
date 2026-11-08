<?php

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Services\ReportScheduler;
use App\Modules\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Runs every minute; sends each report schedule at its own time (Africa/Johannesburg). */
class RunReportSchedules extends Command
{
    protected $signature = 'sales:report-schedules {--id= : Send one schedule now, whatever its time}';

    protected $description = 'Email scheduled sales reports';

    public function handle(TenantContext $tenants, ReportScheduler $scheduler): int
    {
        $now = CarbonImmutable::now('Africa/Johannesburg');
        // Read across companies (no tenant set yet), then send each inside its own company.
        $schedules = DB::table('report_schedules')->where('is_active', true)
            ->when($this->option('id'), fn ($q, $id) => $q->where('id', (int) $id))->get();

        foreach ($schedules as $s) {
            if (! $this->option('id') && ! ReportScheduler::due($s, $now)) {
                continue;
            }
            $r = $tenants->run((int) $s->company_id, fn () => $scheduler->send($s, $now));
            $this->line("Schedule {$s->id} ({$s->name}): " . ($r['ok'] ? "sent {$r['file']} to {$r['recipients']}" : "failed — {$r['error']}"));
        }

        return self::SUCCESS;
    }
}
