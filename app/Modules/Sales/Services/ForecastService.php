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

    /** @param string $measure net_value (rand) or quantity (cases) */
    public function monthly(array $f, ?User $user, int $ahead = 3, string $measure = 'net_value'): array
    {
        $measure = $measure === 'quantity' ? 'quantity' : 'net_value';
        $last = CarbonImmutable::parse($this->analytics->lastSaleDate() ?? now());
        $from = $last->subMonths(24)->startOfMonth();
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

        return [
            'measure' => $measure,
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
            'accuracy' => $this->backtest($hist, $last),
        ];
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
