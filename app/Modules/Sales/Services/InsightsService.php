<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use Carbon\CarbonImmutable;

/**
 * Anomaly detection and product lifecycle (SRS Release 3, §16.1). Plain, explainable rules on the
 * same scoped sales query as everything else — every flag says what it was compared with.
 */
class InsightsService
{
    public function __construct(private SalesAnalytics $analytics) {}

    /** Customers/products whose last 4 weeks differ sharply from their usual weekly level (prior 12 weeks). */
    public function anomalies(?User $user, float $threshold = 40.0): array
    {
        $last = $this->analytics->lastSaleDate();
        if (! $last) {
            return [];
        }
        $end = CarbonImmutable::parse($last);
        $recentFrom = $end->subDays(27);
        $baseTo = $recentFrom->subDay();
        $baseFrom = $baseTo->subDays(83);
        $out = [];

        foreach (['customer', 'product'] as $dim) {
            $recent = collect($this->analytics->grouped(['from' => $recentFrom->toDateString(), 'to' => $end->toDateString()], $user, [$dim], 'net_value', 'none', 5000)['rows'])->keyBy("{$dim}_id");
            $base = collect($this->analytics->grouped(['from' => $baseFrom->toDateString(), 'to' => $baseTo->toDateString()], $user, [$dim], 'net_value', 'none', 5000)['rows'])->keyBy("{$dim}_id");
            $total = max(1, $base->sum('value') / 3);

            foreach ($base->keys()->merge($recent->keys())->unique() as $id) {
                $usual = (float) ($base[$id]['value'] ?? 0) / 3;   // 12 weeks → a 4-week equivalent
                $now = (float) ($recent[$id]['value'] ?? 0);
                if ($usual < $total * 0.01 && $now < $total * 0.01) {
                    continue; // too small to matter
                }
                $change = $usual > 0 ? round(($now - $usual) / $usual * 100, 1) : null;
                if ($change === null || abs($change) >= $threshold) {
                    $out[] = [
                        'type' => $dim, 'id' => $id, 'name' => $recent[$id][$dim] ?? $base[$id][$dim],
                        'recent' => round($now, 2), 'usual' => round($usual, 2), 'change' => $change,
                        'impact' => round($now - $usual, 2),
                        'kind' => $change === null ? 'new' : ($change > 0 ? 'spike' : ($now == 0.0 ? 'stopped' : 'drop')),
                    ];
                }
            }
        }

        usort($out, fn ($a, $b) => abs($b['impact']) <=> abs($a['impact']));

        return ['period' => $recentFrom->toDateString() . ' to ' . $end->toDateString(), 'baseline' => $baseFrom->toDateString() . ' to ' . $baseTo->toDateString(),
            'threshold' => $threshold, 'items' => array_slice($out, 0, 40)];
    }

    /** new · growing · mature · declining · dormant, from first/last sale and the last 3 months vs a year earlier. */
    public function lifecycle(?User $user): array
    {
        $last = $this->analytics->lastSaleDate();
        if (! $last) {
            return [];
        }
        $end = CarbonImmutable::parse($last);
        $first = app(SalesQuery::class)->base([], $user)->groupBy('s.product_id')
            ->selectRaw('s.product_id, min(s.transaction_date) as first_sale, max(s.transaction_date) as last_sale')->get()->keyBy('product_id');
        $rows = collect($this->analytics->grouped(['from' => $end->subMonths(3)->addDay()->toDateString(), 'to' => $end->toDateString()], $user, ['product'], 'net_value', 'previous_year', 5000)['rows']);

        $classified = $first->map(function ($f, $id) use ($rows, $end) {
            $r = $rows->firstWhere('product_id', $id);
            $stage = match (true) {
                CarbonImmutable::parse($f->last_sale)->lt($end->subDays(60)) => 'dormant',
                CarbonImmutable::parse($f->first_sale)->gt($end->subDays(90)) => 'new',
                ($r['growth'] ?? 0) > 10 => 'growing',
                ($r['growth'] ?? 0) < -10 => 'declining',
                default => 'mature',
            };

            return ['product_id' => $id, 'product' => $r['product'] ?? \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->value('product_name'),
                'stage' => $stage, 'first_sale' => $f->first_sale, 'last_sale' => $f->last_sale,
                'last_3_months' => $r['value'] ?? 0, 'growth' => $r['growth'] ?? null];
        })->values();

        return ['counts' => $classified->countBy('stage'), 'products' => $classified->sortBy(fn ($p) => array_search($p['stage'], ['declining', 'dormant', 'new', 'growing', 'mature']))->values()];
    }
}
