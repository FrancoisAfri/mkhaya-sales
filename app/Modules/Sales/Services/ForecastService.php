<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use Carbon\CarbonImmutable;

/**
 * Transparent statistical forecasts (SRS FCT-001 §15.2): run-rate for the current month, a
 * weighted moving average of recent months, and last year's seasonality applied to this year's
 * trend. Each method is shown with how it was worked out — these are estimates, not promises.
 */
class ForecastService
{
    public function __construct(private SalesAnalytics $analytics) {}

    /**
     * @param string $measure net_value (rand) or quantity (cases)
     * @param string $method plain (last year × trend), ets (Holt-Winters), or best (whichever back-tests better)
     */
    public function monthly(array $f, ?User $user, int $ahead = 3, string $measure = 'net_value', string $method = 'plain'): array
    {
        $measure = $measure === 'quantity' ? 'quantity' : 'net_value';
        $last = CarbonImmutable::parse($this->analytics->lastSaleDate() ?? now());
        $from = $last->subMonths(36)->startOfMonth();   // three years: enough for the statistical method to learn the season
        $hist = collect($this->analytics->grouped(array_merge($f, ['from' => $from->toDateString(), 'to' => $last->toDateString()]), $user, ['month'], $measure, 'none', 1000, 'chronological')['rows'])
            ->mapWithKeys(fn ($r) => [$r['month'] => (float) $r['value']]);

        // Current month: actual so far, projected to month end at the current daily rate.
        $mtd = (float) ($hist[$last->format('Y-m')] ?? 0);
        $runRate = $last->day > 0 ? $mtd / $last->day * $last->daysInMonth : 0;

        $complete = $hist->except($last->format('Y-m'))->values();
        $recent = $complete->slice(-3)->values();
        $weights = [1, 2, 3];
        $wma = $recent->count() ? $recent->map(fn ($v, $i) => $v * $weights[$i + 3 - $recent->count()])->sum() / array_sum(array_slice($weights, 3 - $recent->count())) : 0;

        // Seasonal: same month last year × this year's growth over the last 3 months vs a year earlier.
        $growth = $this->recentGrowth($hist, $last);

        $months = [];
        for ($i = 0; $i <= $ahead; $i++) {
            $m = $last->startOfMonth()->addMonths($i);
            // A year after the current month, "last year" is this month — still partial, so use its run rate.
            $ly = $m->subYear()->format('Y-m') === $last->format('Y-m') ? $runRate : (float) ($hist[$m->subYear()->format('Y-m')] ?? 0);
            $seasonal = $ly > 0 && $growth !== null ? $ly * (1 + $growth) : null;
            // More than a year ahead there is no actual "same month last year": grow the forecast made
            // for that month a year earlier by the same recent growth.
            $yearEarlier = $i >= 12 ? ($months[$i - 12]['forecast'] ?? null) : null;
            if ($seasonal === null && $yearEarlier !== null) {
                $seasonal = $yearEarlier * (1 + ($growth ?? 0));
            }
            $point = $i === 0 ? $runRate : ($seasonal ?? $wma);
            $spread = $this->spread($complete);
            $months[] = [
                'month' => $m->format('Y-m'),
                'actual' => $i === 0 ? round($mtd, 2) : null,
                'forecast' => round($point, 2),
                'low' => round(max(0, $point * (1 - $spread)), 2),
                'high' => round($point * (1 + $spread), 2),
                'method' => $i === 0 ? 'Run rate (actual to date ÷ days elapsed × days in month)'
                    : ($yearEarlier !== null && $ly <= 0 ? 'Forecast for the same month a year earlier × recent growth'
                        : ($seasonal !== null ? 'Same month last year × recent growth' : 'Weighted moving average of the last 3 months')),
                'last_year' => $ly ?: null,
            ];
        }

        // The numbers behind every method, so the page can show each calculation worked through.
        $g3 = collect(range(3, 1))->map(fn ($i) => $last->startOfMonth()->subMonths($i))->map(fn ($m) => [
            'month' => $m->format('Y-m'), 'value' => round((float) ($hist[$m->format('Y-m')] ?? 0), 2),
            'last_year_month' => $m->subYear()->format('Y-m'), 'last_year' => round((float) ($hist[$m->subYear()->format('Y-m')] ?? 0), 2),
        ])->values()->all();
        $wmaMonths = $complete->count() ? $hist->except($last->format('Y-m'))->slice(-3)->map(fn ($v, $k) => ['month' => $k, 'value' => round($v, 2)])->values()->all() : [];

        // Statistical option (gap 11): Holt-Winters on the finished months, compared on the same six-month back-test.
        $plainAcc = $this->backtest($hist, $last);
        $ets = $this->etsBacktest($hist, $last);
        $useEts = $method === 'ets' || ($method === 'best' && $ets['mape'] !== null && $plainAcc['mape'] !== null && $ets['mape'] < $plainAcc['mape']);
        $etsFit = null;
        if ($useEts && ($etsFit = $this->holtWinters(array_values($complete->all()), $ahead + 1)) !== null) {
            $spread = $this->spread($complete);
            foreach ($months as $i => &$mm) {
                if ($i === 0) {
                    continue;   // the month in progress keeps its run rate: real sales so far beat any model
                }
                $point = max(0, $etsFit['forecast'][$i] ?? $mm['forecast']);
                $mm = array_merge($mm, ['forecast' => round($point, 2), 'low' => round(max(0, $point * (1 - $spread)), 2), 'high' => round($point * (1 + $spread), 2),
                    'method' => 'Holt-Winters seasonal smoothing (statistical)']);
            }
            unset($mm);
        }

        return [
            'measure' => $measure,
            'method' => $useEts && $etsFit ? 'ets' : 'plain',
            'methods' => ['plain' => $plainAcc, 'ets' => $ets + ['params' => $etsFit['params'] ?? $ets['params'] ?? null]],
            'history' => $hist->map(fn ($v, $k) => ['month' => $k, 'value' => round($v, 2)])->values()->all(),
            'months' => $months,
            'recent_growth' => $growth === null ? null : round($growth * 100, 1),
            'basis' => 'Estimates from sales loaded up to ' . $last->toDateString() . '. Not a guaranteed outcome.',
            'workings' => [
                'as_of' => $last->toDateString(), 'month_to_date' => round($mtd, 2), 'days_elapsed' => $last->day, 'days_in_month' => $last->daysInMonth,
                'run_rate' => round($runRate, 2), 'daily_rate' => $last->day ? round($mtd / $last->day, 2) : 0,
                'growth_months' => $g3, 'growth' => $growth === null ? null : round($growth * 100, 1),
                'wma_months' => $wmaMonths, 'wma' => round($wma, 2), 'spread' => round($this->spread($complete) * 100, 1),
            ],
            'accuracy' => $useEts && $etsFit ? $ets : $plainAcc,
        ];
    }

    /**
     * Additive Holt-Winters with a 12-month season. α, β, γ are chosen by a small grid search that
     * minimises the one-step-ahead squared error on the history. Needs at least 13 finished months.
     *
     * @param array<int, float> $y finished months, oldest first
     * @return array{forecast: array<int, float>, params: array{alpha: float, beta: float, gamma: float}}|null
     *         forecast[0] = the month after the last finished one (the month in progress), [1] the next, …
     */
    public function holtWinters(array $y, int $h): ?array
    {
        $m = 12;
        $n = count($y);
        if ($n < $m + 1) {   // one full season to learn from, plus at least one month to test it on
            return null;
        }
        $grid = [0.1, 0.3, 0.5, 0.7, 0.9];
        $best = null;
        foreach ($grid as $a) {
            foreach ([0.05, 0.1, 0.3] as $b) {
                foreach ($grid as $g) {
                    $fit = $this->hwRun($y, $m, $a, $b, $g, $h);
                    if ($best === null || $fit['sse'] < $best['sse']) {
                        $best = $fit + ['params' => ['alpha' => $a, 'beta' => $b, 'gamma' => $g]];
                    }
                }
            }
        }

        return ['forecast' => $best['forecast'], 'params' => $best['params']];
    }

    private function hwRun(array $y, int $m, float $a, float $b, float $g, int $h): array
    {
        // Start: level = first-year mean, trend = average year-on-year step per month, season = first year minus its mean.
        $level = array_sum(array_slice($y, 0, $m)) / $m;
        $trend = count($y) >= 2 * $m ? (array_sum(array_slice($y, $m, $m)) - array_sum(array_slice($y, 0, $m))) / ($m * $m) : 0.0;
        $season = array_map(fn ($v) => $v - $level, array_slice($y, 0, $m));
        $sse = 0.0;
        for ($t = $m; $t < count($y); $t++) {
            $s = $season[$t % $m];
            $pred = $level + $trend + $s;
            $sse += ($y[$t] - $pred) ** 2;
            $prevLevel = $level;
            $level = $a * ($y[$t] - $s) + (1 - $a) * ($level + $trend);
            $trend = $b * ($level - $prevLevel) + (1 - $b) * $trend;
            $season[$t % $m] = $g * ($y[$t] - $level) + (1 - $g) * $s;
        }
        $n = count($y);
        $forecast = [];
        for ($k = 1; $k <= $h; $k++) {
            $forecast[] = $level + $k * $trend + $season[($n + $k - 1) % $m];
        }

        return ['sse' => $sse, 'forecast' => $forecast];
    }

    /** The statistical method on the same six-month back-test as the plain one (forecast two months ahead, as in real use). */
    private function etsBacktest($hist, CarbonImmutable $last): array
    {
        $rows = [];
        $params = null;
        $keys = $hist->keys()->values();
        for ($i = 6; $i >= 1; $i--) {
            $m = $last->startOfMonth()->subMonths($i);
            $actual = (float) ($hist[$m->format('Y-m')] ?? 0);
            // Known when forecasting M as "next month": everything up to two months before it.
            $train = $hist->filter(fn ($v, $k) => $k <= $m->subMonths(2)->format('Y-m'))->values()->all();
            $fit = $actual > 0 ? $this->holtWinters(array_map('floatval', $train), 2) : null;
            if (! $fit) {
                continue;
            }
            $params = $fit['params'];
            $fc = max(0, $fit['forecast'][1]);
            $rows[] = ['month' => $m->format('Y-m'), 'forecast' => round($fc, 2), 'actual' => round($actual, 2), 'error_pct' => round(($fc - $actual) / $actual * 100, 1)];
        }
        $mape = count($rows) ? collect($rows)->avg(fn ($r) => abs($r['error_pct'])) : null;

        return ['months' => $rows, 'mape' => $mape === null ? null : round($mape, 1), 'accuracy' => $mape === null ? null : round(max(0, 100 - $mape), 1), 'params' => $params];
    }

    /**
     * How well the method would have done: for each of the last six finished months, forecast it
     * the way we forecast the future (same month last year × the growth of the three months
     * before it) and compare with what was actually sold.
     */
    private function backtest($hist, CarbonImmutable $last): array
    {
        $rows = [];
        for ($i = 6; $i >= 1; $i--) {
            $m = $last->startOfMonth()->subMonths($i);
            $actual = (float) ($hist[$m->format('Y-m')] ?? 0);
            $ly = (float) ($hist[$m->subYear()->format('Y-m')] ?? 0);
            $g = $this->recentGrowth($hist, $m->subDay());
            if ($actual <= 0 || $ly <= 0 || $g === null) {
                continue;
            }
            $fc = $ly * (1 + $g);
            $rows[] = ['month' => $m->format('Y-m'), 'forecast' => round($fc, 2), 'actual' => round($actual, 2),
                'error_pct' => round(($fc - $actual) / $actual * 100, 1)];
        }
        $mape = count($rows) ? collect($rows)->avg(fn ($r) => abs($r['error_pct'])) : null;

        return ['months' => $rows, 'mape' => $mape === null ? null : round($mape, 1), 'accuracy' => $mape === null ? null : round(max(0, 100 - $mape), 1)];
    }

    /** Approved company-wide targets per month (value or cases), spread by day where a target spans months. */
    public function targetsByMonth(array $months, string $measure): array
    {
        $months = array_values(array_unique($months));
        if (! $months) {
            return [];
        }
        $first = CarbonImmutable::parse(min($months) . '-01');
        $lastDay = CarbonImmutable::parse(max($months) . '-01')->endOfMonth();
        $targets = \Illuminate\Support\Facades\DB::table('targets')->where('company_id', \App\Modules\Sales\Support\SalesCompany::id())
            ->where('status', 'approved')->where('metric', $measure)->whereIn('method', ['fixed', 'minimum'])->whereNull('dimensions')
            ->where('start_date', '<=', $lastDay->toDateString())->where('end_date', '>=', $first->toDateString())->get();
        $out = [];
        foreach ($months as $m) {
            $ms = CarbonImmutable::parse("{$m}-01");
            $me = $ms->endOfMonth()->startOfDay();
            $sum = 0.0;
            foreach ($targets as $t) {
                $ts = CarbonImmutable::parse($t->start_date);
                $te = CarbonImmutable::parse($t->end_date);
                $overlap = (int) $ts->max($ms)->diffInDays($te->min($me)) + 1;
                if ($ts->lte($me) && $te->gte($ms) && $overlap > 0) {
                    $sum += (float) $t->target_value * $overlap / ((int) $ts->diffInDays($te) + 1);
                }
            }
            $out[$m] = $sum > 0 ? round($sum, 2) : null;
        }

        return $out;
    }

    private function recentGrowth($hist, CarbonImmutable $last): ?float
    {
        $now = $prev = 0.0;
        for ($i = 1; $i <= 3; $i++) {
            $m = $last->startOfMonth()->subMonths($i);
            $now += (float) ($hist[$m->format('Y-m')] ?? 0);
            $prev += (float) ($hist[$m->subYear()->format('Y-m')] ?? 0);
        }

        return $prev > 0 ? ($now - $prev) / $prev : null;
    }

    /** Typical month-to-month variation, used as a simple confidence band (capped 5–40%). */
    private function spread($values): float
    {
        $v = collect($values)->slice(-12)->values();
        if ($v->count() < 3) {
            return 0.25;
        }
        $changes = $v->slice(1)->values()->map(fn ($x, $i) => $v[$i] > 0 ? abs($x - $v[$i]) / $v[$i] : 0);

        return min(0.4, max(0.05, $changes->avg()));
    }
}
