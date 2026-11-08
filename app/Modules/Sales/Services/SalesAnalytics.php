<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Figures for dashboards, reports, targets, forecasts and the AI assistant (SRS BI-001, REP-001).
 * Every number starts from SalesQuery, so the same filters, scopes and "live rows only" rule apply
 * everywhere and dashboards never disagree with reports (§32 rule 12).
 */
class SalesAnalytics
{
    /** Grouping dimensions: key → [select expression, label expression, join]. */
    public const DIMENSIONS = [
        'customer' => 'Customer', 'product' => 'Product', 'category' => 'Product category', 'brand' => 'Brand',
        'customer_group' => 'Customer group', 'region' => 'Region', 'sales_team' => 'Sales team',
        'salesperson' => 'Salesperson', 'business_unit' => 'Business unit', 'distribution_centre' => 'Distribution centre',
        'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year',
    ];

    public const MEASURES = ['net_value' => 'Net sales', 'gross_value' => 'Gross sales', 'quantity' => 'Quantity', 'discount' => 'Discount', 'transactions' => 'Transactions'];

    public function __construct(private SalesQuery $query) {}

    public function period(array $f): array
    {
        $to = CarbonImmutable::parse($f['to'] ?? $this->lastSaleDate() ?? now());
        $from = CarbonImmutable::parse($f['from'] ?? $to->startOfMonth());

        return [$from, $to];
    }

    public function lastSaleDate(): ?string
    {
        return DB::table('sales_records')->where('company_id', app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id())
            ->where('status', 'active')->max('transaction_date');
    }

    public function sum(array $f, ?User $user, CarbonImmutable $from, CarbonImmutable $to, string $measure = 'net_value'): float
    {
        $q = $this->query->base(array_merge($f, ['from' => $from->toDateString(), 'to' => $to->toDateString()]), $user);

        return (float) ($measure === 'transactions' ? $q->count() : $q->sum("s.{$measure}"));
    }

    /** Executive KPI set (§13.1). */
    public function kpis(array $f, ?User $user): array
    {
        [$from, $to] = $this->period($f);
        $pyFrom = $from->subYear();
        $pyTo = $to->subYear();
        $current = $this->sum($f, $user, $from, $to);
        $previous = $this->sum($f, $user, $pyFrom, $pyTo);
        $mtd = $this->sum($f, $user, $to->startOfMonth(), $to);
        $ytd = $this->sum($f, $user, $to->startOfYear(), $to);
        $pytd = $this->sum($f, $user, $to->subYear()->startOfYear(), $to->subYear());

        $daysIn = $to->daysInMonth;
        $elapsed = $to->day;
        $runRate = $elapsed > 0 ? $mtd / $elapsed * $daysIn : 0;

        $lastImport = DB::table('import_batches')->where('company_id', app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id())
            ->where('target', 'sales')->where('status', 'committed')->max('completed_at');

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'total' => $current, 'previous_year' => $previous, 'yoy_growth' => $this->growth($current, $previous),
            'quantity' => $this->sum($f, $user, $from, $to, 'quantity'),
            'mtd' => $mtd, 'ytd' => $ytd, 'previous_ytd' => $pytd, 'ytd_growth' => $this->growth($ytd, $pytd),
            'month_end_forecast' => round($runRate, 2),
            'customers' => (clone $this->query->base(array_merge($f, ['from' => $from->toDateString(), 'to' => $to->toDateString()]), $user))->distinct()->count('s.customer_id'),
            'last_sale_date' => $this->lastSaleDate(),
            'last_import_at' => $lastImport,
        ];
    }

    public function growth(float $current, float $previous): ?float
    {
        return $previous == 0.0 ? null : round(($current - $previous) / abs($previous) * 100, 1);
    }

    /** Series by day/week/month for the period, with the same period last year (§13.2). */
    public function trend(array $f, ?User $user, string $grain = 'auto'): array
    {
        [$from, $to] = $this->period($f);
        if ($grain === 'auto') {
            $days = $from->diffInDays($to);
            $grain = $days <= 45 ? 'day' : ($days <= 200 ? 'week' : 'month');
        }
        $series = function (CarbonImmutable $a, CarbonImmutable $b, int $shiftYears) use ($f, $user, $grain) {
            return $this->query->base(array_merge($f, ['from' => $a->toDateString(), 'to' => $b->toDateString()]), $user)
                ->selectRaw("date_trunc('{$grain}', s.transaction_date + interval '{$shiftYears} year')::date as bucket, sum(s.net_value) as value, sum(s.quantity) as quantity")
                ->groupBy('bucket')->orderBy('bucket')->get()->keyBy('bucket');
        };
        $cur = $series($from, $to, 0);
        $prev = $series($from->subYear(), $to->subYear(), 1);
        $buckets = $cur->keys()->merge($prev->keys())->unique()->sort()->values();

        return [
            'grain' => $grain,
            'labels' => $buckets->all(),
            'current' => $buckets->map(fn ($b) => round((float) ($cur[$b]->value ?? 0), 2))->all(),
            'previous' => $buckets->map(fn ($b) => round((float) ($prev[$b]->value ?? 0), 2))->all(),
        ];
    }

    /**
     * Grouped figures with comparison — the engine behind rankings, standard reports and the
     * report builder (§14.1–14.2). $compare: none | previous_year | previous_period.
     */
    public function grouped(array $f, ?User $user, array $dims, string $measure = 'net_value', string $compare = 'previous_year', int $limit = 500, string $order = 'desc'): array
    {
        [$from, $to] = $this->period($f);
        // Large tenants: rank and cut in the database, never pull every customer × product group into PHP.
        $sqlLimit = in_array($order, ['desc', 'asc'], true) ? $limit : max($limit * 20, 5000);
        $build = function (CarbonImmutable $a, CarbonImmutable $b, int $shiftDays = 0, int $shiftYears = 0, ?int $cap = null) use ($f, $user, $dims, $measure, $order) {
            $q = $this->query->base(array_merge($f, ['from' => $a->toDateString(), 'to' => $b->toDateString()]), $user);
            $keys = [];
            foreach ($dims as $i => $d) {
                [$expr, $label] = $this->dimension($q, $d, $shiftDays, $shiftYears);
                $q->selectRaw("{$expr} as k{$i}, {$label} as l{$i}");
                $q->groupByRaw("{$expr}, {$label}");
                $keys[] = "k{$i}";
            }
            $q->selectRaw($measure === 'transactions' ? 'count(*) as value' : "coalesce(sum(s.{$measure}),0) as value");
            if ($cap) {
                $q->orderByRaw('value ' . ($order === 'asc' ? 'asc' : 'desc'))->limit($cap);
            }

            return $q->get()->keyBy(fn ($r) => implode('|', array_map(fn ($k) => (string) $r->{$k}, $keys)));
        };

        $current = $build($from, $to, 0, 0, $sqlLimit);
        $previous = collect();
        if ($compare === 'previous_year') {
            $previous = $build($from->subYear(), $to->subYear(), 0, 1, $sqlLimit * 2);
        } elseif ($compare === 'previous_period') {
            $len = (int) $from->diffInDays($to) + 1;
            $previous = $build($from->subDays($len), $from->subDay(), $len, 0, $sqlLimit * 2);
        }
        if (in_array($order, ['desc', 'asc'], true)) {
            $previous = $previous->only($current->keys()->all()); // rank by this period
        }

        $q = $this->query->base(array_merge($f, ['from' => $from->toDateString(), 'to' => $to->toDateString()]), $user);
        $total = (float) ($measure === 'transactions' ? $q->count() : $q->sum("s.{$measure}"));
        $rows = $current->keys()->merge($previous->keys())->unique()->map(function ($key) use ($current, $previous, $dims, $total, $compare) {
            $c = $current[$key] ?? null;
            $p = $previous[$key] ?? null;
            $src = $c ?? $p;
            $row = ['key' => $key];
            foreach ($dims as $i => $d) {
                $row[$d] = $src->{"l{$i}"};
                $row["{$d}_id"] = $src->{"k{$i}"};
            }
            $row['value'] = round((float) ($c->value ?? 0), 2);
            $row['share'] = $total ? round($row['value'] / $total * 100, 1) : 0;
            if ($compare !== 'none') {
                $row['previous'] = round((float) ($p->value ?? 0), 2);
                $row['change'] = round($row['value'] - $row['previous'], 2);
                $row['growth'] = $this->growth($row['value'], $row['previous']);
            }

            return $row;
        });
        $rows = match ($order) {
            'chronological' => $rows->sortBy(fn ($r) => (string) $r["{$dims[0]}_id"]),
            'growth' => $rows->sortByDesc(fn ($r) => $r['growth'] ?? -INF),
            'decline' => $rows->sortBy(fn ($r) => $r['change'] ?? 0),
            'asc' => $rows->sortBy('value'),
            default => $rows->sortByDesc('value'),
        };
        $rows = $rows->values();

        return ['rows' => $rows->take($limit)->values()->all(), 'total' => round($total, 2), 'count' => $rows->count(), 'truncated' => $rows->count() >= $sqlLimit,
            'from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    /** @return array{0: string, 1: string} SQL key expression and label expression */
    private function dimension($q, string $d, int $shiftDays, int $shiftYears): array
    {
        $date = "(s.transaction_date + interval '{$shiftDays} day' + interval '{$shiftYears} year')";
        $join = function (string $table, string $alias, string $on) use ($q) {
            $joins = collect($q->joins ?? [])->pluck('table')->all();
            if (! in_array("{$table} as {$alias}", $joins, true)) {
                $q->leftJoin("{$table} as {$alias}", "{$alias}.id", '=', $on);
            }
        };

        switch ($d) {
            case 'customer': $join('customers', 'dc', 's.customer_id'); return ['s.customer_id', "coalesce(dc.customer_name, '(unknown)')"];
            case 'product': $join('products', 'dp', 's.product_id'); return ['s.product_id', "coalesce(dp.product_name, '(unknown)')"];
            case 'category': $join('products', 'dp', 's.product_id'); $join('product_categories', 'dcat', 'dp.category_id'); return ['dcat.id', "coalesce(dcat.name, '(no category)')"];
            case 'brand': $join('products', 'dp', 's.product_id'); $join('product_brands', 'db', 'dp.brand_id'); return ['db.id', "coalesce(db.name, '(no brand)')"];
            case 'customer_group': $join('customers', 'dc', 's.customer_id'); $join('customer_groups', 'dg', 'dc.customer_group_id'); return ['dg.id', "coalesce(dg.name, '(no group)')"];
            case 'region': $join('regions', 'dr', 's.region_id'); return ['s.region_id', "coalesce(dr.name, '(no region)')"];
            case 'sales_team': $join('sales_teams', 'dt', 's.sales_team_id'); return ['s.sales_team_id', "coalesce(dt.name, '(no team)')"];
            case 'salesperson': $join('salespeople', 'dsp', 's.salesperson_id'); return ['s.salesperson_id', "coalesce(dsp.name, '(no salesperson)')"];
            case 'business_unit': $join('business_units', 'dbu', 's.business_unit_id'); return ['s.business_unit_id', "coalesce(dbu.name, '(no business unit)')"];
            case 'distribution_centre': $join('distribution_centres', 'ddc', 's.distribution_centre_id'); return ['s.distribution_centre_id', "coalesce(ddc.name, '(no DC)')"];
            case 'day': return ["{$date}::date", "to_char({$date}, 'YYYY-MM-DD')"];
            case 'week': return ["date_trunc('week', {$date})::date", "to_char(date_trunc('week', {$date}), 'IYYY-\"W\"IW')"];
            case 'month': return ["date_trunc('month', {$date})::date", "to_char({$date}, 'YYYY-MM')"];
            case 'quarter': return ["date_trunc('quarter', {$date})::date", "to_char({$date}, 'YYYY-\"Q\"Q')"];
            case 'year': return ["date_trunc('year', {$date})::date", "to_char({$date}, 'YYYY')"];
        }
        abort(422, "Unknown dimension {$d}.");
    }

    /** Share of sales held by the largest customers (§13.3). */
    public function concentration(array $f, ?User $user): array
    {
        $rows = collect($this->grouped($f, $user, ['customer'], 'net_value', 'none', 10)['rows']);

        [$from, $to] = $this->period($f);
        $all = $this->query->base(array_merge($f, ['from' => $from->toDateString(), 'to' => $to->toDateString()]), $user);
        $total = (float) (clone $all)->sum('s.net_value') ?: 1;

        return [
            'top5_share' => round($rows->take(5)->sum('value') / $total * 100, 1),
            'top10_share' => round($rows->take(10)->sum('value') / $total * 100, 1),
            'customers' => (clone $all)->distinct()->count('s.customer_id'),
        ];
    }
}
