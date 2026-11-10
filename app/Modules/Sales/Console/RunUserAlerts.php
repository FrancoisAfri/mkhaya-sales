<?php

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Services\BI\UserAlertService;
use App\Modules\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Runs every minute; checks each person's alert at its own time (Africa/Johannesburg). */
class RunUserAlerts extends Command
{
    protected $signature = 'sales:user-alerts {--id= : Check one alert now}';

    protected $description = 'Check the alerts people set on their numbers';

    public function handle(TenantContext $tenants, UserAlertService $alerts): int
    {
        $now = CarbonImmutable::now('Africa/Johannesburg');
        foreach (DB::table('bi_alerts')->where('is_active', true)->when($this->option('id'), fn ($q, $id) => $q->where('id', (int) $id))->get() as $a) {
            if (! $this->option('id') && ! UserAlertService::due($a, $now)) {
                continue;
            }
            $r = $tenants->run((int) $a->company_id, fn () => $alerts->check($a));
            $this->line("Alert {$a->id} ({$a->name}): {$r['text']}");
        }

        return self::SUCCESS;
    }
}
