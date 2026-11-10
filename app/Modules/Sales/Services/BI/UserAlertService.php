<?php

namespace App\Modules\Sales\Services\BI;

use App\Core\User\Models\User;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesNotifier;
use App\Modules\Sales\Services\SalesQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Alerts a person sets on any number (gap 3): "tell me when Shoprite cases drop 10% below the
 * week before". Checked at the person's chosen time; the figure comes from the same measure engine
 * as every dashboard, with the person's own data scopes.
 */
class UserAlertService
{
    public const PERIODS = ['yesterday' => 'Yesterday', 'last_7_days' => 'Last 7 days', 'mtd' => 'Month to date', 'ytd' => 'Year to date'];
    public const COMPARISONS = ['value' => 'The figure itself', 'vs_previous' => '% change vs the period before', 'vs_last_year' => '% change vs the same period last year', 'vs_target' => '% of target achieved'];

    public function __construct(private BIQueryService $bi, private SalesQuery $query, private SalesNotifier $notifier) {}

    public static function due(object $a, CarbonImmutable $now): bool
    {
        if (! $a->is_active || $a->run_time !== $now->format('H:i')) {
            return false;
        }
        if ($a->last_checked_at && CarbonImmutable::parse($a->last_checked_at)->gt($now->subMinutes(50))) {
            return false;
        }

        return $a->frequency !== 'weekly' || (int) $a->weekday === $now->dayOfWeekIso;
    }

    /** @return array{value: ?float, triggered: bool, from: string, to: string, text: string} */
    public function evaluate(object $a, ?User $user): array
    {
        $last = CarbonImmutable::parse(app(SalesAnalytics::class)->lastSaleDate() ?? now());
        [$from, $to] = $this->range($a->period, $last);
        $filters = $this->query->filters(new Request((array) json_decode($a->filters ?? '[]', true))) + ['from' => $from->toDateString(), 'to' => $to->toDateString()];
        $measure = $a->measure;
        $kpi = fn (array $measures, array $f) => $this->bi->run(['kind' => 'kpi', 'measures' => $measures, 'period' => 'dashboard'], $f, $user)['values'] ?? [];

        $value = match ($a->comparison) {
            'vs_last_year' => $measure === 'net_value' ? ($kpi(['net_value', 'growth_pct'], $filters)['growth_pct'] ?? null) : $this->changeVs($kpi, $measure, $filters, $from->subYear(), $to->subYear()),
            'vs_previous' => $this->changeVs($kpi, $measure, $filters, $from->subDays((int) $from->diffInDays($to) + 1), $from->subDay()),
            'vs_target' => $kpi([$measure === 'quantity' ? 'volume_achievement_pct' : 'achievement_pct', $measure], $filters)[$measure === 'quantity' ? 'volume_achievement_pct' : 'achievement_pct'] ?? null,
            default => $kpi([$measure], $filters)[$measure] ?? null,
        };
        $value = $value === null ? null : round((float) $value, 2);
        $triggered = $value !== null && ($a->operator === 'above' ? $value > (float) $a->threshold : $value < (float) $a->threshold);
        $label = $this->bi->catalogue()[$measure]['label'] ?? $measure;
        $shown = $a->comparison === 'value' ? $this->format($value, $measure) : ($value === null ? 'no figure' : ($value > 0 && $a->comparison !== 'vs_target' ? '+' : '') . $value . '%');
        $text = sprintf('%s, %s (%s to %s): %s — rule: %s %s %s.', $label, mb_strtolower(self::COMPARISONS[$a->comparison]), $from->toDateString(), $to->toDateString(),
            $shown, $a->operator === 'above' ? 'above' : 'below', $a->comparison === 'value' ? $this->format((float) $a->threshold, $measure) : $a->threshold . '%', $triggered ? '(triggered)' : '(not triggered)');

        return ['value' => $value, 'triggered' => $triggered, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'text' => $text];
    }

    /** Evaluate, record, and tell the person when it fires. */
    public function check(object $a, bool $notify = true): array
    {
        $user = User::find($a->user_id);
        $r = $this->evaluate($a, $user);
        DB::table('bi_alerts')->where('id', $a->id)->update(['last_checked_at' => now(), 'last_value' => $r['value'],
            'last_triggered_at' => $r['triggered'] ? now() : $a->last_triggered_at, 'updated_at' => now()]);
        if ($r['triggered'] && $notify && $user) {
            $this->notifier->toUsers([$user], "Alert: {$a->name}", $r['text'], url('/sales/alerts'), "bi-alert:{$a->id}:" . now()->toDateString(),
                (array) json_decode($a->channels, true) ?: ['in_app'], 60);
        }

        return $r;
    }

    private function changeVs(callable $kpi, string $measure, array $filters, CarbonImmutable $pf, CarbonImmutable $pt): ?float
    {
        $now = (float) ($kpi([$measure], $filters)[$measure] ?? 0);
        $before = (float) ($kpi([$measure], ['from' => $pf->toDateString(), 'to' => $pt->toDateString()] + $filters)[$measure] ?? 0);

        return $before > 0 ? ($now - $before) / $before * 100 : null;
    }

    private function range(string $period, CarbonImmutable $last): array
    {
        return match ($period) {
            'yesterday' => [$last, $last],
            'last_7_days' => [$last->subDays(6), $last],
            'ytd' => [$last->startOfYear(), $last],
            default => [$last->startOfMonth(), $last],
        };
    }

    private function format(?float $v, string $measure): string
    {
        if ($v === null) {
            return 'no figure';
        }
        $fmt = $this->bi->catalogue()[$measure]['format'] ?? 'number';

        return match ($fmt) {
            'currency' => 'R ' . number_format($v, 0, '.', ' '),
            'percent' => $v . '%',
            default => number_format($v, 0, '.', ' ') . ($measure === 'quantity' ? ' cases' : ''),
        };
    }
}
