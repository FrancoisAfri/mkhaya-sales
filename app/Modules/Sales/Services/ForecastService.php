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

    public function monthly(array $f, ?User $user, int $ahead = 3): array
    {
        $last = CarbonImmutable::parse($this->analytics->lastSaleDate() ?? now());
        $from = $last->subMonths(24)->startOfMonth();
        $hist = collect($this->analytics->grouped(array_merge($f, ['from' => $from->toDateString(), 'to' => $last->toDateString()]), $user, ['month'], 'net_value', 'none', 1000, 'chronological')['rows'])
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

        return [
            'history' => $hist->map(fn ($v, $k) => ['month' => $k, 'value' => round($v, 2)])->values()->all(),
            'months' => $months,
            'recent_growth' => $growth === null ? null : round($growth * 100, 1),
            'basis' => 'Estimates from sales loaded up to ' . $last->toDateString() . '. Not a guaranteed outcome.',
        ];
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
