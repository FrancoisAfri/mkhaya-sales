<?php

namespace App\Modules\Sales\Services\BI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pre-summarised sales by month (gap 5). One row per month × customer × product × region × team ×
 * rep × business unit × DC: the BI engine reads it instead of every invoice line whenever a visual
 * asks for whole months, which is what keeps tens of millions of rows interactive. It is only used
 * while it is known to be current — any change to sales marks it stale until it is rebuilt.
 */
class SalesSummary
{
    private const DIMS = ['customer_id', 'product_id', 'region_id', 'business_unit_id', 'sales_team_id', 'salesperson_id', 'distribution_centre_id', 'layout_id'];

    public static function ready(int $companyId): bool
    {
        return (bool) Cache::get("sales-summary-ready:{$companyId}");
    }

    public static function markStale(int $companyId): void
    {
        Cache::forget("sales-summary-ready:{$companyId}");
    }

    /** Rebuild one company's summary in a single statement. Returns the number of summary rows. */
    public static function rebuild(int $companyId): int
    {
        $dims = implode(', ', self::DIMS);
        $n = DB::transaction(function () use ($companyId, $dims) {
            DB::table('sales_monthly_summary')->where('company_id', $companyId)->delete();

            return DB::affectingStatement("insert into sales_monthly_summary (company_id, transaction_date, {$dims}, net_value, gross_value, quantity, discount, lines, status)
                select company_id, date_trunc('month', transaction_date)::date, {$dims}, coalesce(sum(net_value),0), coalesce(sum(gross_value),0),
                       coalesce(sum(quantity),0), coalesce(sum(discount),0), count(*), 'active'
                from sales_records where company_id = ? and status = 'active'
                group by company_id, date_trunc('month', transaction_date), {$dims}", [$companyId]);
        });
        Cache::forever("sales-summary-ready:{$companyId}", now()->toIso8601String());

        return $n;
    }

    /**
     * Can this request be answered from whole months? Only when the range starts on the 1st,
     * ends on a month end or at/after the latest sale, and nothing needs days or weeks.
     */
    public static function covers(array $f, array $dims, array $base, ?string $lastSale): bool
    {
        if (empty($f['from']) || empty($f['to']) || ! empty($f['batch']) || array_intersect($dims, ['day', 'week'])) {
            return false;
        }
        $from = \Carbon\CarbonImmutable::parse($f['from']);
        $to = \Carbon\CarbonImmutable::parse($f['to']);
        $toOk = $to->isSameDay($to->endOfMonth()) || ($lastSale && $to->gte(\Carbon\CarbonImmutable::parse($lastSale)));

        return $from->day === 1 && $toOk;
    }
}
