<?php

namespace App\Modules\Sales\Services\BI;

use App\Core\User\Models\User;
use App\Modules\Sales\Services\ForecastService;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Support\Formula;
use App\Modules\Sales\Support\SalesCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The semantic measure engine behind every BI visual (BI spec §7, §9, §10).
 *
 * A visual sends a validated configuration — measures, dimension, series, period, grain, top-N,
 * comparison — and receives ready-to-draw data. All arithmetic happens here, over SalesQuery, so
 * a figure is identical in every chart, table, KPI and report; the browser never computes an
 * authoritative number and never receives raw transactions. Results are cached per company,
 * configuration and data version (bumped whenever imports or targets change).
 */
class BIQueryService
{
    /** Base measures — SQL aggregates. Keys match the Formula variables so custom measures can use them. */
    public const BASE = [
        'net_value' => ['label' => 'Net sales', 'sql' => 'coalesce(sum(s.net_value),0)', 'format' => 'currency'],
        'gross_value' => ['label' => 'Gross sales', 'sql' => 'coalesce(sum(s.gross_value),0)', 'format' => 'currency'],
        'quantity' => ['label' => 'Volume (cases)', 'sql' => 'coalesce(sum(s.quantity),0)', 'format' => 'number'],
        'discount' => ['label' => 'Discount', 'sql' => 'coalesce(sum(s.discount),0)', 'format' => 'currency'],
        'transactions' => ['label' => 'Sales lines', 'sql' => 'count(*)', 'format' => 'number'],
        'active_customers' => ['label' => 'Active customers', 'sql' => 'count(distinct s.customer_id)', 'format' => 'number'],
        'active_products' => ['label' => 'Products sold', 'sql' => 'count(distinct s.product_id)', 'format' => 'number'],
    ];

    /** Derived measures — computed from base measures after aggregation. */
    public const DERIVED = [
        'avg_selling_price' => ['label' => 'Average selling price', 'formula' => 'net_value / quantity', 'format' => 'currency'],
        'avg_customer_sales' => ['label' => 'Average sales per customer', 'formula' => 'net_value / active_customers', 'format' => 'currency'],
        'discount_rate' => ['label' => 'Discount %', 'formula' => 'discount / gross_value * 100', 'format' => 'percent'],
    ];

    /** Comparison measures — need a second (previous-year) query or targets. */
    public const COMPARE = [
        'last_year' => ['label' => 'Same period last year', 'format' => 'currency'],
        'growth_pct' => ['label' => 'Growth % vs last year', 'format' => 'percent'],
        'target' => ['label' => 'Target', 'format' => 'currency'],
        'achievement_pct' => ['label' => 'Target achievement %', 'format' => 'percent'],
        'variance' => ['label' => 'Variance to target', 'format' => 'currency'],
        'month_target' => ['label' => 'Full-month target', 'format' => 'currency'],
        'volume_target' => ['label' => 'Volume target (cases)', 'format' => 'number'],
        'volume_achievement_pct' => ['label' => 'Volume target achievement %', 'format' => 'percent'],
    ];

    public const GRAINS = ['day', 'week', 'month', 'quarter', 'year'];
    public const PERIODS = ['dashboard', 'mtd', 'last_month', 'qtd', 'ytd', 'last_12_months', 'last_24_months', 'all'];
    public const KINDS = ['category', 'time', 'kpi', 'matrix', 'scatter', 'waterfall', 'pareto', 'histogram', 'boxplot',
        'calendar', 'cohort', 'sankey', 'treemap', 'forecast', 'table', 'narrative', 'decomposition', 'geo', 'influencers', 'network'];

    /** Rough centres of the South African provinces — used when a province has no geocoded customers. */
    public const PROVINCES = [
        'Gauteng' => [-26.27, 28.11], 'Western Cape' => [-33.23, 21.86], 'KwaZulu-Natal' => [-28.53, 30.90],
        'Eastern Cape' => [-32.30, 26.42], 'Free State' => [-28.45, 26.80], 'Limpopo' => [-23.40, 29.42],
        'Mpumalanga' => [-25.57, 30.53], 'North West' => [-26.66, 25.28], 'Northern Cape' => [-29.05, 21.86],
    ];

    /** Dimensions the key-influencer scan looks through for what explains a change. */
    public const INFLUENCER_DIMENSIONS = ['region', 'province', 'customer_group', 'category', 'brand', 'sales_team', 'salesperson', 'distribution_centre', 'business_unit'];

    private ?string $lastSale = null;

    public function __construct(private SalesQuery $query, private SalesAnalytics $analytics) {}

    /** Every measure a visual may ask for, with label and format. */
    public function catalogue(): array
    {
        $custom = DB::table('bi_measures')->where('company_id', SalesCompany::id())->get()
            ->mapWithKeys(fn ($m) => ["custom:{$m->key}" => ['label' => $m->label, 'format' => $m->format]])->all();

        return collect(self::BASE + self::DERIVED + self::COMPARE)->map(fn ($m) => ['label' => $m['label'], 'format' => $m['format']])->all() + $custom;
    }

    public function run(array $cfg, array $filters, ?User $user): array
    {
        $cfg = $this->validate($cfg);
        $key = 'bi:' . SalesCompany::id() . ':' . $this->version() . ':' . ($user?->id ?? 0) . ':' . md5(json_encode([$cfg, $filters]));

        return Cache::remember($key, now()->addMinutes(10), function () use ($cfg, $filters, $user) {
            $f = $this->period($cfg, $filters);

            return ['kind' => $cfg['kind'], 'period' => ['from' => $f['from'], 'to' => $f['to']]] + match ($cfg['kind']) {
                'kpi' => $this->kpi($cfg, $f, $user),
                'time' => $this->series($cfg, $f, $user, $cfg['grain']),
                'category', 'table', 'pareto', 'decomposition' => $this->category($cfg, $f, $user),
                'matrix' => $this->matrix($cfg, $f, $user),
                'scatter' => $this->scatter($cfg, $f, $user),
                'waterfall' => $this->waterfall($cfg, $f, $user),
                'histogram', 'boxplot' => $this->distribution($cfg, $f, $user),
                'calendar' => $this->series($cfg, $f, $user, 'day'),
                'cohort' => $this->cohort($f, $user),
                'sankey', 'treemap' => $this->hierarchy($cfg, $f, $user),
                'forecast' => $this->forecast($f, $user),
                'narrative' => $this->narrative($f, $user),
                'geo' => $this->geo($cfg, $f, $user),
                'influencers' => $this->influencers($f, $user),
                'network' => $this->network($cfg, $f, $user),
            };
        });
    }

    /** Bump when sales or targets change so cached visuals refresh (§10). */
    /** @param bool $salesChanged false when only targets or measures changed (the monthly summary stays valid) */
    public static function invalidate(?int $companyId = null, bool $salesChanged = true): void
    {
        $c = $companyId ?? SalesCompany::id();
        Cache::forever("bi-version:{$c}", (int) Cache::get("bi-version:{$c}", 0) + 1);
        if ($salesChanged && $c) {
            SalesSummary::markStale((int) $c);
        }
    }

    private function version(): int
    {
        return (int) Cache::get('bi-version:' . SalesCompany::id(), 0);
    }

    // ── Shapes ──────────────────────────────────────────────────────────────────────────────────

    private function kpi(array $cfg, array $f, ?User $user): array
    {
        $values = $this->values($cfg['measures'], $f, $user, []);
        $row = $values[''] ?? [];
        // Sparkline: last 12 months of the first measure, whatever the KPI's own period.
        $spark = $this->series(['measures' => [$cfg['measures'][0]], 'series' => null, 'top' => 0, 'sort' => 'chrono'] + $cfg,
            ['from' => CarbonImmutable::parse($f['to'])->subMonths(11)->startOfMonth()->toDateString()] + $f, $user, 'month');

        return ['values' => $row, 'formats' => $this->formats($cfg['measures']), 'labels' => $this->labels($cfg['measures']), 'spark' => $spark['series'][0]['data'] ?? []];
    }

    private function series(array $cfg, array $f, ?User $user, string $grain): array
    {
        $dims = [$grain];
        if (! empty($cfg['series'])) {
            $dims[] = $cfg['series'];
        }
        $rows = $this->values($cfg['measures'], $f, $user, $dims);
        $buckets = collect($rows)->map(fn ($r) => ['k' => $r['_k0'], 'l' => $r['_l0']])->unique('k')->sortBy('k')->values();

        $series = [];
        if (! empty($cfg['series'])) {
            $measure = $cfg['measures'][0];
            $names = collect($rows)->groupBy('_k1')->map(fn ($g) => ['name' => $g->first()['_l1'], 'total' => $g->sum($measure)])
                ->sortByDesc('total')->take($cfg['top'] ?: 8);
            foreach ($names as $k => $n) {
                $series[] = ['key' => (string) $k, 'name' => $n['name'], 'measure' => $measure,
                    'data' => $buckets->map(fn ($b) => round((float) ($rows[$b['k'] . '|' . $k][$measure] ?? 0), 2))->all()];
            }
        } else {
            foreach ($cfg['measures'] as $m) {
                $series[] = ['key' => $m, 'name' => $this->labels([$m])[$m], 'measure' => $m,
                    'data' => $buckets->map(fn ($b) => isset($rows[(string) $b['k']][$m]) ? round((float) $rows[(string) $b['k']][$m], 2) : null)->all()];
            }
        }

        return ['categories' => $buckets->pluck('l')->all(), 'keys' => $buckets->pluck('k')->all(), 'series' => $series,
            'formats' => $this->formats($cfg['measures']), 'anomalies' => $this->anomalies($series[0]['data'] ?? [])];
    }

    private function category(array $cfg, array $f, ?User $user): array
    {
        $dims = [$cfg['dimension']];
        $rows = collect($this->values($cfg['measures'], $f, $user, $dims));
        $m = $cfg['measures'][0];
        $rows = $cfg['sort'] === 'asc' ? $rows->sortBy($m) : ($cfg['sort'] === 'chrono' ? $rows->sortBy('_l0') : $rows->sortByDesc($m));
        $total = $rows->sum($m);
        $top = $cfg['top'] ?: 15;
        $shown = $rows->take($top)->values();
        if ($cfg['other'] && $rows->count() > $top) {
            $rest = $rows->slice($top);
            $other = ['_k0' => 'other', '_l0' => 'Other (' . $rest->count() . ')'];
            foreach ($cfg['measures'] as $mm) {
                $other[$mm] = isset(self::BASE[$mm]) && ! str_starts_with($mm, 'active') ? $rest->sum($mm) : null;
            }
            $shown->push($other);
        }

        $out = ['categories' => $shown->pluck('_l0')->all(), 'keys' => $shown->pluck('_k0')->all(),
            'series' => collect($cfg['measures'])->map(fn ($mm) => ['key' => $mm, 'name' => $this->labels([$mm])[$mm], 'measure' => $mm,
                'data' => $shown->map(fn ($r) => $r[$mm] === null ? null : round((float) $r[$mm], 2))->all()])->all(),
            'formats' => $this->formats($cfg['measures']), 'total' => round((float) $total, 2), 'count' => $rows->count(), 'dimension' => $cfg['dimension']];

        if ($cfg['kind'] === 'pareto') {
            $running = 0;
            $out['cumulative'] = $shown->map(function ($r) use (&$running, $total, $m) {
                $running += (float) $r[$m];

                return $total ? round($running / $total * 100, 1) : 0;
            })->all();
        }

        return $out;
    }

    private function matrix(array $cfg, array $f, ?User $user): array
    {
        $m = $cfg['measures'][0];
        $rows = collect($this->values([$m], $f, $user, [$cfg['dimension'], $cfg['series'] ?: 'month']));
        $rowKeys = $rows->groupBy('_k0')->map(fn ($g) => ['l' => $g->first()['_l0'], 't' => $g->sum($m)])->sortByDesc('t')->take($cfg['top'] ?: 20);
        $colKeys = $rows->groupBy('_k1')->map(fn ($g) => ['l' => $g->first()['_l1'], 't' => $g->sum($m)]);
        $colKeys = in_array($cfg['series'] ?: 'month', self::GRAINS, true) ? $colKeys->sortKeys() : $colKeys->sortByDesc('t')->take(12);
        $cells = [];
        foreach ($rowKeys->keys()->values() as $ri => $rk) {
            foreach ($colKeys->keys()->values() as $ci => $ck) {
                $cells[] = [$ci, $ri, round((float) ($rows->first(fn ($r) => (string) $r['_k0'] === (string) $rk && (string) $r['_k1'] === (string) $ck)[$m] ?? 0), 2)];
            }
        }

        return ['rows' => $rowKeys->pluck('l')->values()->all(), 'columns' => $colKeys->pluck('l')->values()->all(), 'cells' => $cells,
            'max' => collect($cells)->max(2) ?? 0, 'formats' => $this->formats([$m])];
    }

    /** One point per customer/product: x = sales, y = growth %, size = quantity (quadrant analysis). */
    private function scatter(array $cfg, array $f, ?User $user): array
    {
        $dim = in_array($cfg['dimension'], ['customer', 'product', 'brand', 'category', 'region', 'salesperson'], true) ? $cfg['dimension'] : 'customer';
        $rows = collect($this->values(['net_value', 'quantity', 'last_year'], $f, $user, [$dim]))->sortByDesc('net_value')->take($cfg['top'] ?: 200);
        $points = $rows->map(fn ($r) => ['name' => $r['_l0'], 'key' => $r['_k0'], 'value' => [round($r['net_value'], 2),
            $r['last_year'] > 0 ? round(($r['net_value'] - $r['last_year']) / $r['last_year'] * 100, 1) : 0, round($r['quantity'], 2)]])->values();

        return ['points' => $points->all(), 'x_mid' => round($points->avg('value.0') ?? 0, 2), 'y_mid' => 0, 'dimension' => $dim];
    }

    /** How the total moved from the previous period to this one, by the biggest contributors (§E). */
    private function waterfall(array $cfg, array $f, ?User $user): array
    {
        $dim = $cfg['dimension'] ?: 'customer';
        $rows = collect($this->values(['net_value', 'last_year'], $f, $user, [$dim]));
        $start = $rows->sum('last_year');
        $end = $rows->sum('net_value');
        $changes = $rows->map(fn ($r) => ['name' => $r['_l0'], 'value' => round($r['net_value'] - $r['last_year'], 2)])->sortByDesc(fn ($c) => abs($c['value']));
        $top = $changes->take($cfg['top'] ?: 8)->values();
        $other = round($changes->slice($cfg['top'] ?: 8)->sum('value'), 2);
        if ($other != 0.0) {
            $top->push(['name' => 'All other', 'value' => $other]);
        }

        return ['start' => ['name' => 'Same period last year', 'value' => round($start, 2)], 'steps' => $top->all(), 'end' => ['name' => 'This period', 'value' => round($end, 2)]];
    }

    /** Distribution of per-customer (or per-product) totals: histogram bins, or box plot per group. */
    private function distribution(array $cfg, array $f, ?User $user): array
    {
        $entity = in_array($cfg['dimension'], ['customer', 'product'], true) ? $cfg['dimension'] : 'customer';
        if ($cfg['kind'] === 'boxplot') {
            $group = $cfg['series'] ?: 'region';
            $rows = collect($this->values(['net_value'], $f, $user, [$group, $entity]));
            $groups = $rows->groupBy('_k0')->map(fn ($g) => ['name' => $g->first()['_l0'], 'box' => $this->box($g->pluck('net_value')->sort()->values()->all())])
                ->sortByDesc(fn ($g) => $g['box'][2])->take(12)->values();

            return ['groups' => $groups->pluck('name')->all(), 'boxes' => $groups->pluck('box')->all(), 'entity' => $entity];
        }
        $vals = collect($this->values(['net_value'], $f, $user, [$entity]))->pluck('net_value')->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0)->values();
        if ($vals->isEmpty()) {
            return ['bins' => [], 'counts' => []];
        }
        $min = $vals->min();
        $max = $vals->max();
        $n = 12;
        $width = ($max - $min) / $n ?: 1;
        $counts = array_fill(0, $n, 0);
        foreach ($vals as $v) {
            $counts[min($n - 1, (int) floor(($v - $min) / $width))]++;
        }

        return ['bins' => array_map(fn ($i) => round($min + $i * $width), range(0, $n - 1)), 'width' => round($width), 'counts' => $counts,
            'mean' => round($vals->avg(), 2), 'median' => round($this->box($vals->sort()->values()->all())[2], 2), 'entity' => $entity];
    }

    /** Customers by month of first purchase × months since, counting those still buying (§L cohort). */
    private function cohort(array $f, ?User $user): array
    {
        $from = CarbonImmutable::parse($f['to'])->subMonths(11)->startOfMonth();
        $base = $this->query->base(['to' => $f['to']] + array_intersect_key($f, array_flip(SalesQuery::FILTERS)), $user);
        $first = (clone $base)->groupBy('s.customer_id')->selectRaw("s.customer_id, date_trunc('month', min(s.transaction_date))::date as cohort");
        $activity = DB::query()->fromSub((clone $base)->where('s.transaction_date', '>=', $from->toDateString())
            ->selectRaw("distinct s.customer_id, date_trunc('month', s.transaction_date)::date as m"), 'a')
            ->joinSub($first, 'c', 'c.customer_id', '=', 'a.customer_id')
            ->where('c.cohort', '>=', $from->toDateString())
            ->selectRaw("to_char(c.cohort, 'YYYY-MM') as cohort, ((extract(year from a.m) - extract(year from c.cohort)) * 12 + extract(month from a.m) - extract(month from c.cohort))::int as age, count(*) as customers")
            ->groupBy('cohort', 'age')->get();
        $cohorts = $activity->pluck('cohort')->unique()->sort()->values();
        $cells = [];
        foreach ($cohorts as $ri => $c) {
            $size = (int) ($activity->first(fn ($a) => $a->cohort === $c && $a->age === 0)->customers ?? 0);
            foreach ($activity->where('cohort', $c) as $a) {
                $cells[] = [$a->age, $ri, $size ? round($a->customers / $size * 100, 1) : 0, (int) $a->customers];
            }
        }

        return ['rows' => $cohorts->all(), 'columns' => range(0, 11), 'cells' => $cells];
    }

    /** Two-level breakdown for Sankey and treemap/sunburst (e.g. category → product). */
    private function hierarchy(array $cfg, array $f, ?User $user): array
    {
        $parent = $cfg['dimension'] ?: 'category';
        $child = $cfg['series'] ?: 'product';
        $m = $cfg['measures'][0];
        $rows = collect($this->values([$m], $f, $user, [$parent, $child]));
        $parents = $rows->groupBy('_k0')->map(fn ($g) => ['name' => $g->first()['_l0'], 'value' => round($g->sum($m), 2),
            'children' => $g->sortByDesc($m)->take($cfg['top'] ?: 10)->map(fn ($r) => ['name' => $r['_l1'], 'value' => round($r[$m], 2)])->values()->all()])
            ->sortByDesc('value')->take(12)->values();

        return ['tree' => $parents->all(), 'formats' => $this->formats([$m])];
    }

    private function forecast(array $f, ?User $user): array
    {
        $fc = app(ForecastService::class)->monthly(array_intersect_key($f, array_flip(SalesQuery::FILTERS)), $user, 6);
        $targets = $this->targetBuckets(CarbonImmutable::parse($fc['history'][0]['month'] ?? $f['from'])->startOfMonth()->toDateString(),
            CarbonImmutable::parse(end($fc['months'])['month'] . '-01')->endOfMonth()->toDateString(), 'month');

        return $fc + ['targets' => $targets];
    }

    /** A short, factual summary built from the numbers (the AI narrative is separate and labelled). */
    private function narrative(array $f, ?User $user): array
    {
        $k = $this->analytics->kpis(['from' => $f['from'], 'to' => $f['to']] + array_intersect_key($f, array_flip(SalesQuery::FILTERS)), $user);
        $top = $this->analytics->grouped($f, $user, ['customer'], 'net_value', 'previous_year', 1)['rows'][0] ?? null;
        $fall = $this->analytics->grouped($f, $user, ['customer'], 'net_value', 'previous_year', 1, 'decline')['rows'][0] ?? null;
        $rand = fn ($v) => 'R ' . number_format((float) $v, 0, '.', ' ');
        $lines = [sprintf('Net sales for %s to %s were %s, %s the same period last year (%s).', $f['from'], $f['to'], $rand($k['total']),
            $k['yoy_growth'] === null ? 'with no comparable sales' : ($k['yoy_growth'] >= 0 ? "up {$k['yoy_growth']}% on" : 'down ' . abs($k['yoy_growth']) . '% on'), $rand($k['previous_year']))];
        if ($top) {
            $lines[] = "The largest customer was {$top['customer']} at {$rand($top['value'])} ({$top['share']}% of sales).";
        }
        if ($fall && ($fall['change'] ?? 0) < 0) {
            $lines[] = "The biggest fall was {$fall['customer']}, down {$rand(abs($fall['change']))} on last year.";
        }
        $lines[] = "At the current daily rate the month should close near {$rand($k['month_end_forecast'])}.";

        return ['lines' => $lines];
    }

    /**
     * Customer locations for the maps: one point per geocoded customer (largest first) and one
     * bubble per province, sized by sales and coloured by growth on last year.
     */
    private function geo(array $cfg, array $f, ?User $user): array
    {
        $cur = $this->query->base($f, $user)->join('customers as gc', 'gc.id', '=', 's.customer_id');
        $ly = $this->query->base(['from' => CarbonImmutable::parse($f['from'])->subYear()->toDateString(), 'to' => CarbonImmutable::parse($f['to'])->subYear()->toDateString()] + $f, $user)
            ->join('customers as gc', 'gc.id', '=', 's.customer_id');

        $lyByCustomer = (clone $ly)->whereNotNull('gc.latitude')->groupBy('s.customer_id')->selectRaw('s.customer_id, sum(s.net_value) as v')->pluck('v', 'customer_id');
        $points = (clone $cur)->whereNotNull('gc.latitude')->whereNotNull('gc.longitude')
            ->groupBy('s.customer_id', 'gc.customer_name', 'gc.latitude', 'gc.longitude', 'gc.province', 'gc.city')
            ->selectRaw('s.customer_id, gc.customer_name, gc.latitude, gc.longitude, gc.province, gc.city, sum(s.net_value) as v')
            ->orderByDesc('v')->limit(min(3000, max(100, (int) ($cfg['top'] ?: 0) * 30 ?: 2000)))->get()
            ->map(function ($r) use ($lyByCustomer) {
                $prev = (float) ($lyByCustomer[$r->customer_id] ?? 0);

                return ['key' => $r->customer_id, 'name' => $r->customer_name, 'lat' => (float) $r->latitude, 'lng' => (float) $r->longitude,
                    'province' => $r->province, 'city' => $r->city, 'value' => round((float) $r->v, 2),
                    'growth' => $prev > 0 ? round(((float) $r->v - $prev) / $prev * 100, 1) : null];
            })->values();

        $lyByProvince = (clone $ly)->groupByRaw('gc.province')->selectRaw("coalesce(gc.province, '(none)') as p, sum(s.net_value) as v")->pluck('v', 'p');
        $regions = (clone $cur)->groupByRaw('gc.province')
            ->selectRaw("coalesce(gc.province, '(none)') as p, sum(s.net_value) as v, count(distinct s.customer_id) as n, avg(gc.latitude) as lat, avg(gc.longitude) as lng")
            ->get()->map(function ($r) use ($lyByProvince) {
                $prev = (float) ($lyByProvince[$r->p] ?? 0);
                $centre = self::PROVINCES[$r->p] ?? null;

                return ['key' => $r->p, 'name' => $r->p === '(none)' ? '(no province)' : $r->p, 'value' => round((float) $r->v, 2), 'last_year' => round($prev, 2),
                    'growth' => $prev > 0 ? round(((float) $r->v - $prev) / $prev * 100, 1) : null, 'customers' => (int) $r->n,
                    'lat' => $centre[0] ?? ($r->lat !== null ? (float) $r->lat : null), 'lng' => $centre[1] ?? ($r->lng !== null ? (float) $r->lng : null)];
            })->sortByDesc('value')->values();

        $customers = (int) (clone $cur)->distinct()->count('s.customer_id');

        return ['points' => $points->all(), 'regions' => $regions->all(), 'customers' => $customers,
            'located' => (int) (clone $cur)->whereNotNull('gc.latitude')->distinct()->count('s.customer_id'), 'dimension' => 'customer'];
    }

    /**
     * Key influencers (BI spec §F): which members of which dimensions explain the change on last
     * year. Each member's change is set against the total change, and each dimension is scored by
     * how much of the movement its three biggest members explain.
     */
    private function influencers(array $f, ?User $user): array
    {
        $tot = $this->values(['net_value', 'last_year'], $f, $user, [])[''] ?? ['net_value' => 0, 'last_year' => 0];
        $totalChange = $tot['net_value'] - $tot['last_year'];
        $overallGrowth = $tot['last_year'] > 0 ? round($totalChange / $tot['last_year'] * 100, 1) : null;
        $members = [];
        $factors = [];
        foreach (self::INFLUENCER_DIMENSIONS as $dim) {
            $rows = collect($this->values(['net_value', 'last_year'], $f, $user, [$dim]))
                ->map(fn ($r) => ['dimension' => $dim, 'dimension_label' => SalesAnalytics::DIMENSIONS[$dim], 'key' => $r['_k0'] ?? null, 'name' => $r['_l0'] ?? '—',
                    'value' => round($r['net_value'], 2), 'last_year' => round($r['last_year'], 2), 'change' => round($r['net_value'] - $r['last_year'], 2),
                    'growth' => $r['last_year'] > 0 ? round(($r['net_value'] - $r['last_year']) / $r['last_year'] * 100, 1) : null,
                    'share' => $tot['net_value'] > 0 ? round($r['net_value'] / $tot['net_value'] * 100, 1) : 0]);
            // A dimension where everything sits in one "(none)" bucket explains nothing.
            if ($rows->count() < 2) {
                continue;
            }
            $abs = $rows->sum(fn ($r) => abs($r['change'])) ?: 1;
            $factors[] = ['dimension' => $dim, 'label' => SalesAnalytics::DIMENSIONS[$dim], 'members' => $rows->count(),
                'score' => round($rows->sortByDesc(fn ($r) => abs($r['change']))->take(3)->sum(fn ($r) => abs($r['change'])) / $abs * 100, 1)];
            array_push($members, ...$rows->values()->all());
        }
        $members = collect($members);
        // Share of change = this member's part of all the falls (or all the gains) in its own dimension.
        $falls = $members->where('change', '<', 0)->groupBy('dimension')->map(fn ($g) => abs($g->sum('change')) ?: 1);
        $gains = $members->where('change', '>', 0)->groupBy('dimension')->map(fn ($g) => $g->sum('change') ?: 1);
        $shape = fn ($r, $totals) => $r + ['share_of_change' => round(abs($r['change']) / ($totals[$r['dimension']] ?? 1) * 100, 1),
            'vs_overall' => $r['growth'] !== null && $overallGrowth !== null ? round($r['growth'] - $overallGrowth, 1) : null];

        return [
            'overall' => ['value' => round($tot['net_value'], 2), 'last_year' => round($tot['last_year'], 2), 'change' => round($totalChange, 2), 'growth' => $overallGrowth],
            'decline' => $members->where('change', '<', 0)->sortBy('change')->take(8)->map(fn ($r) => $shape($r, $falls))->values()->all(),
            'growth' => $members->where('change', '>', 0)->sortByDesc('change')->take(6)->map(fn ($r) => $shape($r, $gains))->values()->all(),
            'factors' => collect($factors)->sortByDesc('score')->values()->all(),
        ];
    }

    /** Who buys what: a two-sided network (customers ↔ categories by default) for the network and chord visuals. */
    private function network(array $cfg, array $f, ?User $user): array
    {
        $a = $cfg['dimension'] ?: 'customer';
        $b = $cfg['series'] ?: 'category';
        if ($a === $b || in_array($a, self::GRAINS, true) || in_array($b, self::GRAINS, true)) {
            throw ValidationException::withMessages(['config' => 'A network needs two different non-date dimensions.']);
        }
        $m = $cfg['measures'][0];
        $rows = collect($this->values([$m], $f, $user, [$a, $b]))->filter(fn ($r) => $r[$m] > 0);
        $left = $rows->groupBy('_k0')->map(fn ($g) => ['name' => $g->first()['_l0'], 'value' => $g->sum($m)])->sortByDesc('value')->take($cfg['top'] ?: 15);
        $right = $rows->whereIn('_k0', $left->keys()->all())->groupBy('_k1')->map(fn ($g) => ['name' => $g->first()['_l1'], 'value' => $g->sum($m)])->sortByDesc('value')->take(12);
        $nodes = [];
        foreach ($left as $k => $n) {
            $nodes[] = ['id' => "a:{$k}", 'key' => $k, 'name' => $n['name'], 'value' => round($n['value'], 2), 'category' => 0];
        }
        foreach ($right as $k => $n) {
            $nodes[] = ['id' => "b:{$k}", 'key' => $k, 'name' => $n['name'], 'value' => round($n['value'], 2), 'category' => 1];
        }
        $links = $rows->filter(fn ($r) => $left->has($r['_k0']) && $right->has($r['_k1']))
            ->map(fn ($r) => ['source' => "a:{$r['_k0']}", 'target' => "b:{$r['_k1']}", 'value' => round($r[$m], 2)])->values()->all();

        return ['nodes' => $nodes, 'links' => $links, 'sides' => [SalesAnalytics::DIMENSIONS[$a], SalesAnalytics::DIMENSIONS[$b]],
            'dimensions' => [$a, $b], 'formats' => $this->formats([$m])];
    }

    // ── Engine ──────────────────────────────────────────────────────────────────────────────────

    /**
     * Aggregate the requested measures over the dimensions. Returns rows keyed "k0|k1" with
     * _k0/_l0 (key/label per dimension) and one value per measure, comparisons included.
     */
    private function values(array $measures, array $f, ?User $user, array $dims): array
    {
        $base = array_values(array_unique(array_merge(
            array_filter($measures, fn ($m) => isset(self::BASE[$m])),
            ...array_map(fn ($m) => $this->baseNeeded($m), $measures)
        )));
        $rows = $this->aggregate($base, $f, $user, $dims);

        $wantsLy = (bool) array_intersect($measures, ['last_year', 'growth_pct']);
        $ly = $wantsLy ? $this->aggregate(['net_value'], ['from' => CarbonImmutable::parse($f['from'])->subYear()->toDateString(),
            'to' => CarbonImmutable::parse($f['to'])->subYear()->toDateString()] + $f, $user, $dims, 1) : [];
        $wantsTarget = (bool) array_intersect($measures, ['target', 'achievement_pct', 'variance']);
        $targets = $wantsTarget && isset($dims[0]) && in_array($dims[0], self::GRAINS, true) ? $this->targetBuckets($f['from'], $f['to'], $dims[0]) : [];
        // No approved target = no target (shown as "—"), never a target of R 0.
        $orgTarget = $wantsTarget && ! $dims ? (array_sum($this->targetBuckets($f['from'], $f['to'], 'year')) ?: null) : null;
        // Volume targets are company-wide case targets (metric "quantity"), kept apart from value targets.
        $wantsVolume = (bool) array_intersect($measures, ['volume_target', 'volume_achievement_pct']);
        $volTargets = $wantsVolume && isset($dims[0]) && in_array($dims[0], self::GRAINS, true) ? $this->targetBuckets($f['from'], $f['to'], $dims[0], 'quantity') : [];
        $orgVolume = $wantsVolume && ! $dims ? array_sum($this->targetBuckets($f['from'], $f['to'], 'year', 'quantity')) : null;

        foreach (array_unique(array_merge(array_keys($rows), array_keys($ly))) as $key) {
            $r = ($rows[$key] ?? []) + (isset($ly[$key]) ? array_intersect_key($ly[$key], array_flip(['_k0', '_l0', '_k1', '_l1'])) : []);
            foreach ($base as $b) {
                $r[$b] = (float) ($r[$b] ?? 0);
            }
            $r['last_year'] = (float) ($ly[$key]['net_value'] ?? 0);
            $r['growth_pct'] = $r['last_year'] > 0 ? round(($r['net_value'] - $r['last_year']) / $r['last_year'] * 100, 1) : null;
            $target = $dims ? ($targets[(string) ($r['_k0'] ?? '')] ?? null) : $orgTarget;
            $r['target'] = $target;
            $r['achievement_pct'] = $target ? round($r['net_value'] / $target * 100, 1) : null;
            $r['variance'] = $target !== null ? round($r['net_value'] - $target, 2) : null;
            // The whole month's target (not pro-rated to date), for run rate and "time gone" cards.
            if (in_array('month_target', $measures, true) && ! $dims) {
                $m = CarbonImmutable::parse($f['to']);
                $r['month_target'] = array_sum($this->targetBuckets($m->startOfMonth()->toDateString(), $m->endOfMonth()->toDateString(), 'year')) ?: null;
            }
            if ($wantsVolume) {
                $vt = $dims ? ($volTargets[(string) ($r['_k0'] ?? '')] ?? null) : ($orgVolume ?: null);
                $r['volume_target'] = $vt;
                $r['volume_achievement_pct'] = $vt ? round(($r['quantity'] ?? 0) / $vt * 100, 1) : null;
            }
            foreach ($measures as $m) {
                if (isset(self::DERIVED[$m])) {
                    $r[$m] = round(Formula::evaluate(self::DERIVED[$m]['formula'], $r), 4);
                } elseif (str_starts_with($m, 'custom:')) {
                    $r[$m] = round(Formula::evaluate($this->customFormula(substr($m, 7)), $r), 4);
                }
            }
            $rows[$key] = $r;
        }

        return $rows;
    }

    private function aggregate(array $base, array $f, ?User $user, array $dims, int $shiftYears = 0): array
    {
        // Whole months → the monthly summary (gap 5); anything finer → the invoice lines.
        $summary = SalesSummary::ready((int) SalesCompany::id()) && SalesSummary::covers($f, $dims, $base, $this->lastSale ??= $this->analytics->lastSaleDate());
        $q = $this->query->base($f, $user, $summary ? 'sales_monthly_summary' : 'sales_records');
        foreach ($dims as $i => $d) {
            [$expr, $label] = $this->analytics->dimension($q, $d, 0, $shiftYears);
            $q->selectRaw("{$expr} as _k{$i}, {$label} as _l{$i}")->groupByRaw("{$expr}, {$label}");
        }
        foreach ($base as $b) {
            $q->selectRaw(($summary && $b === 'transactions' ? 'coalesce(sum(s.lines),0)' : self::BASE[$b]['sql']) . " as {$b}");
        }
        if (! $dims) {
            $q->selectRaw("'' as _k0");
        }
        // A safety ceiling: no visual draws more than this many groups.
        $q->limit(20000);

        $out = [];
        foreach ($q->get() as $r) {
            $r = (array) $r;
            $key = implode('|', array_map(fn ($i) => (string) ($r["_k{$i}"] ?? ''), $dims ? array_keys($dims) : [0]));
            $out[$key] = $r;
        }

        return $out;
    }

    /** Approved organisation-level net sales targets spread over buckets by day (§11.8 target lines). */
    private function targetBuckets(string $from, string $to, string $grain, string $metric = 'net_value'): array
    {
        $buckets = [];
        $targets = DB::table('targets')->where('company_id', SalesCompany::id())->where('status', 'approved')
            ->where('metric', $metric)->whereIn('method', ['fixed', 'minimum'])->whereNull('dimensions')
            ->where('start_date', '<=', $to)->where('end_date', '>=', $from)->get();
        foreach ($targets as $t) {
            $s = CarbonImmutable::parse($t->start_date);
            $e = CarbonImmutable::parse($t->end_date);
            $perDay = (float) $t->target_value / ((int) $s->diffInDays($e) + 1);
            for ($d = max($s, CarbonImmutable::parse($from)); $d->lte(min($e, CarbonImmutable::parse($to))); $d = $d->addDay()) {
                $k = match ($grain) {
                    'day' => $d->toDateString(), 'week' => $d->startOfWeek()->toDateString(), 'month' => $d->startOfMonth()->toDateString(),
                    'quarter' => $d->firstOfQuarter()->toDateString(), default => $d->startOfYear()->toDateString(),
                };
                $buckets[$k] = ($buckets[$k] ?? 0) + $perDay;
            }
        }

        return array_map(fn ($v) => round($v, 2), $buckets);
    }

    private function period(array $cfg, array $filters): array
    {
        $last = CarbonImmutable::parse($this->analytics->lastSaleDate() ?? now());
        $f = array_intersect_key(array_merge($filters, $cfg['filters'] ?? []), array_flip(array_merge(SalesQuery::FILTERS, SalesQuery::TEXT_FILTERS, ['from', 'to'])));
        [$from, $to] = match ($cfg['period']) {
            'mtd' => [$last->startOfMonth(), $last],
            'last_month' => [$last->subMonthNoOverflow()->startOfMonth(), $last->subMonthNoOverflow()->endOfMonth()],
            'qtd' => [$last->firstOfQuarter(), $last],
            'ytd' => [$last->startOfYear(), $last],
            'last_12_months' => [$last->subMonths(12)->addDay(), $last],
            'last_24_months' => [$last->subMonths(24)->addDay(), $last],
            'all' => [CarbonImmutable::parse('2000-01-01'), $last],
            default => [CarbonImmutable::parse($f['from'] ?? $last->startOfMonth()), CarbonImmutable::parse($f['to'] ?? $last)],
        };

        return ['from' => $from->toDateString(), 'to' => $to->toDateString()] + $f;
    }

    private function validate(array $cfg): array
    {
        $dims = array_keys(SalesAnalytics::DIMENSIONS);
        $measures = array_keys($this->catalogue());
        $cfg = array_merge(['kind' => 'category', 'measures' => ['net_value'], 'dimension' => 'customer', 'series' => null, 'grain' => 'month',
            'top' => 0, 'sort' => 'desc', 'other' => false, 'period' => 'dashboard', 'filters' => []], array_intersect_key($cfg,
            array_flip(['kind', 'measures', 'dimension', 'series', 'grain', 'top', 'sort', 'other', 'period', 'filters'])));
        $cfg['measures'] = array_values(array_intersect((array) $cfg['measures'], $measures)) ?: ['net_value'];
        $bad = ! in_array($cfg['kind'], self::KINDS, true)
            || ($cfg['dimension'] && ! in_array($cfg['dimension'], $dims, true))
            || ($cfg['series'] && ! in_array($cfg['series'], $dims, true))
            || ! in_array($cfg['grain'], self::GRAINS, true)
            || ! in_array($cfg['period'], self::PERIODS, true);
        if ($bad) {
            throw ValidationException::withMessages(['config' => 'This visual configuration is not valid.']);
        }
        $cfg['top'] = max(0, min(100, (int) $cfg['top']));
        $cfg['filters'] = is_array($cfg['filters']) ? $cfg['filters'] : [];

        return $cfg;
    }

    private function baseNeeded(string $m): array
    {
        if (isset(self::DERIVED[$m])) {
            return Formula::variables(self::DERIVED[$m]['formula']);
        }
        if (str_starts_with($m, 'custom:')) {
            return Formula::variables($this->customFormula(substr($m, 7)));
        }

        if (in_array($m, ['volume_target', 'volume_achievement_pct'], true)) {
            return ['quantity'];
        }

        return isset(self::COMPARE[$m]) ? ['net_value'] : [];
    }

    private function customFormula(string $key): string
    {
        return (string) DB::table('bi_measures')->where('company_id', SalesCompany::id())->where('key', $key)->value('formula') ?: '0';
    }

    private function formats(array $measures): array
    {
        $cat = $this->catalogue();

        return collect($measures)->mapWithKeys(fn ($m) => [$m => $cat[$m]['format'] ?? 'number'])->all();
    }

    private function labels(array $measures): array
    {
        $cat = $this->catalogue();

        return collect($measures)->mapWithKeys(fn ($m) => [$m => $cat[$m]['label'] ?? $m])->all();
    }

    /** Points more than two standard deviations from the series mean (§B anomaly markers). */
    private function anomalies(array $data): array
    {
        $vals = array_values(array_filter($data, fn ($v) => $v !== null));
        if (count($vals) < 6) {
            return [];
        }
        $mean = array_sum($vals) / count($vals);
        $sd = sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $vals)) / count($vals));

        return $sd > 0 ? array_keys(array_filter($data, fn ($v) => $v !== null && abs($v - $mean) > 2 * $sd)) : [];
    }

    private function box(array $sorted): array
    {
        $n = count($sorted);
        if (! $n) {
            return [0, 0, 0, 0, 0];
        }
        $q = fn ($p) => $sorted[(int) floor(($n - 1) * $p)];

        return array_map(fn ($v) => round((float) $v, 2), [$sorted[0], $q(0.25), $q(0.5), $q(0.75), $sorted[$n - 1]]);
    }
}
