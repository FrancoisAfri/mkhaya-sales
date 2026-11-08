<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Target;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Daily performance alerts (SRS §17.1): target achieved, target at risk or behind, unusual sales
 * movement. Each is switched on/off with its threshold per company (§17.3); the notifier never
 * repeats the same unresolved issue within its window.
 */
class PerformanceAlerts
{
    public const DEFAULTS = [
        'target_achieved' => ['enabled' => true],
        'target_at_risk' => ['enabled' => true, 'threshold' => 90],   // forecast achievement % below this
        'unusual_movement' => ['enabled' => true, 'threshold' => 40], // % away from the usual same weekday
        'run_time' => '07:00',
    ];

    public function __construct(private TargetService $targets, private SalesAnalytics $analytics, private SalesNotifier $notifier) {}

    public static function settingsFor(int $companyId): array
    {
        $saved = json_decode((string) DB::table('sales_alert_settings')->where('company_id', $companyId)->value('settings'), true) ?: [];

        return array_replace_recursive(self::DEFAULTS, $saved);
    }

    /** @return array<int, string> alert titles raised (for logging and tests) */
    public function run(int $companyId): array
    {
        $s = self::settingsFor($companyId);
        $raised = [];
        $today = now()->toDateString();

        foreach (Target::where('status', 'approved')->where('start_date', '<=', $today)->where('end_date', '>=', $today)->get() as $t) {
            $p = $this->targets->evaluate($t);
            $url = url("/sales/targets/{$t->uuid}");
            if ($s['target_achieved']['enabled'] && $p['status'] === 'achieved') {
                $raised[] = $this->raise($companyId, 'dashboards.view', "Target achieved: {$t->name}", "Actual {$p['actual']} against {$p['target']} ({$p['achievement']}%).", $url, "achieved:{$t->id}:v{$t->version}", 365);
            } elseif ($s['target_at_risk']['enabled'] && in_array($p['status'], ['at_risk', 'behind', 'breached'], true)
                && ($p['forecast_achievement'] ?? 100) < (float) $s['target_at_risk']['threshold']) {
                $raised[] = $this->raise($companyId, 'targets.edit', "Target at risk: {$t->name}",
                    "Forecast {$p['forecast_achievement']}% of target; {$p['remaining']} remaining, {$p['required_daily']} needed per day for {$p['days_left']} days.",
                    $url, "at-risk:{$t->id}:v{$t->version}", 7);
            }
        }

        if ($s['unusual_movement']['enabled'] && ($last = $this->analytics->lastSaleDate())) {
            $day = CarbonImmutable::parse($last);
            $value = $this->analytics->sum([], null, $day, $day);
            $usual = collect(range(1, 4))->map(fn ($w) => $this->analytics->sum([], null, $day->subWeeks($w), $day->subWeeks($w)))->filter()->avg();
            if ($usual > 0) {
                $move = round(($value - $usual) / $usual * 100, 1);
                if (abs($move) >= (float) $s['unusual_movement']['threshold']) {
                    $raised[] = $this->raise($companyId, 'dashboards.view', 'Unusual sales on ' . $day->format('D j M'),
                        'Net sales R ' . number_format($value, 0, '.', ' ') . ' are ' . ($move > 0 ? '+' : '') . "{$move}% against the usual " . $day->format('l') . ' (R ' . number_format($usual, 0, '.', ' ') . ').',
                        url('/sales/dashboard?from=' . $day->toDateString() . '&to=' . $day->toDateString()), "movement:{$companyId}:{$day->toDateString()}", 1);
                }
            }
        }

        return array_values(array_filter($raised));
    }

    private function raise(int $companyId, string $permission, string $title, string $body, string $url, string $key, int $days): ?string
    {
        return $this->notifier->alert($companyId, $permission, $title, $body, $url, $key, $days) ? $title : null;
    }
}
