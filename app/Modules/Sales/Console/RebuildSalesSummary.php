<?php

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Services\BI\SalesSummary;
use App\Modules\Tenancy\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Rebuilds the monthly sales summary for companies whose sales changed (every 5 minutes), or one / all on request. */
class RebuildSalesSummary extends Command
{
    protected $signature = 'sales:summarise {--company= : One company} {--all : Every company, even if current}';

    protected $description = 'Rebuild the pre-summarised monthly sales used by large dashboards';

    public function handle(TenantContext $tenants): int
    {
        $ids = $this->option('company') ? [(int) $this->option('company')] : DB::table('sales_records')->distinct()->pluck('company_id')->all();
        foreach ($ids as $id) {
            if (! $this->option('all') && ! $this->option('company') && SalesSummary::ready($id)) {
                continue;
            }
            $t = microtime(true);
            $n = $tenants->run($id, fn () => SalesSummary::rebuild($id));
            $this->line(sprintf('Company %d: %d summary rows in %.1fs', $id, $n, microtime(true) - $t));
        }

        return self::SUCCESS;
    }
}
