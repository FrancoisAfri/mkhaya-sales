<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use App\Modules\Sales\Models\Target;
use App\Modules\Sales\Models\TargetApproval;
use App\Modules\Sales\Models\TargetApprovalRule;
use App\Modules\Sales\Models\TargetRevision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Target calculations and the approval workflow (SRS TGT-001 §11.4–11.8).
 * Actuals come from SalesQuery with the target's dimensions as filters, so a target and a report
 * over the same slice always show the same actual figure.
 */
class TargetService
{
    public function __construct(private SalesQuery $query) {}

    // ── Performance ─────────────────────────────────────────────────────────────────────────────

    public function evaluate(Target $t, ?User $user = null): array
    {
        $start = CarbonImmutable::parse($t->start_date);
        $end = CarbonImmutable::parse($t->end_date);
        $asOf = CarbonImmutable::parse(min($end->toDateString(), now()->toDateString()));
        $f = array_filter(($t->dimensions ?? []) + ['from' => $start->toDateString(), 'to' => $asOf->toDateString()]);
        $notStarted = $asOf->lt($start);

        if ($t->method === 'composite') {
            return $this->evaluateComposite($t, $user, $notStarted);
        }
        if ($t->method === 'growth') {
            return $this->evaluateGrowth($t, $user, $f, $start, $asOf, $notStarted);
        }

        $actual = $notStarted ? 0.0 : $this->measure($t, $f, $user);

        $target = (float) $t->target_value;
        $today = CarbonImmutable::today();
        $totalDays = (int) $start->diffInDays($end) + 1;
        $elapsed = $asOf->lt($start) ? 0 : (int) $start->diffInDays($asOf) + 1;
        // Selling days still to come after today (today counts as done).
        $remainingDays = $today->gte($end) ? 0 : (int) $today->diffInDays($end) + ($today->lt($start) ? 1 : 0);
        $remaining = max(0, $target - $actual);
        $cumulative = ! in_array($t->metric, ['active_customers', 'formula'], true);
        $forecast = $elapsed > 0 && $cumulative ? $actual / $elapsed * $totalDays : $actual;
        $achievement = $target > 0 ? round($actual / $target * 100, 1) : null;
        $forecastPct = $target > 0 ? $forecast / $target * 100 : 0;

        $status = match ($t->method) {
            'maximum' => $actual > $target ? 'breached' : ($forecast > $target ? 'at_risk' : 'on_track'),
            'range' => $actual > (float) $t->target_value_max ? 'breached' : ($actual >= $target ? 'achieved' : ($forecastPct >= 100 ? 'on_track' : 'at_risk')),
            default => $actual >= $target ? 'achieved' : ($forecastPct >= 100 ? 'on_track' : ($forecastPct >= 90 ? 'at_risk' : 'behind')),
        };
        if ($asOf->lt($start)) {
            $status = 'not_started';
        }

        return [
            'target' => $target,
            'actual' => round($actual, 2),
            'achievement' => $achievement,
            'variance' => round($actual - $target, 2),
            'remaining' => round($remaining, 2),
            'days_left' => $remainingDays,
            'required_daily' => $remainingDays > 0 ? round($remaining / $remainingDays, 2) : null,
            'forecast' => round($forecast, 2),
            'forecast_achievement' => $target > 0 ? round($forecastPct, 1) : null,
            'status' => $status,
        ];
    }

    /** Approved targets overlapping the filter period, with their performance. */
    public function performanceList(array $f, ?User $user): array
    {
        return $this->overlapping($f)->map(fn (Target $t) => $this->row($t, $user))->values()->all();
    }

    public function summary(array $f, ?User $user): array
    {
        $rows = collect($this->performanceList($f, $user));
        // The headline target is the organisation-level net sales target(s) — no dimension restriction.
        $org = $rows->filter(fn ($r) => $r['metric'] === 'net_value' && empty(array_filter($r['dimensions'] ?? [])));

        return [
            'count' => $rows->count(),
            'achieved' => $rows->where('performance.status', 'achieved')->count(),
            'at_risk' => $rows->whereIn('performance.status', ['at_risk', 'behind', 'breached'])->count(),
            'org_target' => $org->sum('performance.target') ?: null,
            'org_actual' => $org->sum('performance.actual'),
            'org_achievement' => $org->sum('performance.target') > 0 ? round($org->sum('performance.actual') / $org->sum('performance.target') * 100, 1) : null,
            'org_remaining' => $org->sum('performance.remaining'),
            'org_forecast' => $org->sum('performance.forecast'),
        ];
    }

    public function row(Target $t, ?User $user): array
    {
        return [
            'uuid' => $t->uuid, 'name' => $t->name, 'metric' => $t->metric, 'method' => $t->method,
            'start_date' => $t->start_date->toDateString(), 'end_date' => $t->end_date->toDateString(),
            'dimensions' => $t->dimensions, 'status' => $t->status, 'lifecycle' => $t->lifecycle(), 'version' => $t->version,
            'performance' => $this->evaluate($t, $user),
        ];
    }

    private function overlapping(array $f): Collection
    {
        $from = $f['from'] ?? now()->startOfMonth()->toDateString();
        $to = $f['to'] ?? now()->toDateString();

        return Target::where('status', 'approved')->where('start_date', '<=', $to)->where('end_date', '>=', $from)->orderBy('end_date')->get();
    }

    // ── Approvals matrix ────────────────────────────────────────────────────────────────────────

    /** The ordered steps that apply to this target (step → rules). Empty matrix = one default step. */
    public function steps(Target $t): Collection
    {
        $rules = TargetApprovalRule::where('is_active', true)->orderBy('step')->get()->filter(fn ($r) => $r->applies($t));

        return $rules->groupBy('step')->sortKeys()->values();
    }

    public function canApprove(User $user, Target $t): bool
    {
        if ($t->status !== 'submitted' || ! $user->can('targets.approve')) {
            return false;
        }
        $steps = $this->steps($t);
        if ($steps->isEmpty()) {
            return $user->id !== $t->created_by; // default rule: anyone with the permission except the creator
        }
        $rules = $steps[$t->current_step] ?? collect();

        return $rules->contains(function (TargetApprovalRule $r) use ($user, $t) {
            if ($user->id === $t->created_by && ! $r->allow_creator) {
                return false;
            }

            return match ($r->approver_type) {
                'user' => $r->approver_user_id === $user->id,
                'role' => $user->hasRole($r->approver_role),
                'creator_manager' => $this->managerUserId($t->created_by) === $user->id,
                default => false,
            };
        });
    }

    public function submit(Target $t, User $by): void
    {
        if (! in_array($t->status, ['draft', 'rejected'], true)) {
            throw new RuntimeException('Only a draft or rejected target can be submitted.');
        }
        $t->update(['status' => 'submitted', 'submitted_at' => now(), 'current_step' => 0]);
    }

    public function decide(Target $t, User $by, bool $approve, ?string $comment = null): void
    {
        if (! $this->canApprove($by, $t)) {
            throw new RuntimeException('You are not an approver for this target at its current step.');
        }
        DB::transaction(function () use ($t, $by, $approve, $comment) {
            $steps = $this->steps($t);
            TargetApproval::create([
                'target_id' => $t->id, 'version' => $t->version, 'step' => $t->current_step + 1,
                'rule_id' => $steps[$t->current_step][0]->id ?? null,
                'decided_by' => $by->id, 'decision' => $approve ? 'approved' : 'rejected', 'comment' => $comment, 'decided_at' => now(),
            ]);
            if (! $approve) {
                $t->update(['status' => 'rejected']);
            } elseif ($t->current_step + 1 >= max(1, $steps->count())) {
                $t->update(['status' => 'approved', 'approved_at' => now(), 'current_step' => $t->current_step + 1]);
            } else {
                $t->update(['current_step' => $t->current_step + 1]);
            }
        });
    }

    /** A new value for an approved target: the old one is kept and the change goes back for approval. */
    public function revise(Target $t, User $by, float $newValue, string $reason, ?float $newMax = null): void
    {
        if (! in_array($t->status, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Only an approved (or rejected) target is revised; edit a draft directly.');
        }
        DB::transaction(function () use ($t, $by, $newValue, $reason, $newMax) {
            TargetRevision::create([
                'target_id' => $t->id, 'from_version' => $t->version,
                'previous_value' => $t->target_value, 'new_value' => $newValue,
                'previous' => $t->only(['name', 'metric', 'method', 'start_date', 'end_date', 'target_value', 'target_value_max', 'dimensions', 'status', 'approved_at']),
                'reason' => $reason, 'revised_by' => $by->id, 'created_at' => now(),
            ]);
            $t->update([
                'target_value' => $newValue, 'target_value_max' => $newMax ?? $t->target_value_max,
                'version' => $t->version + 1, 'status' => 'submitted', 'submitted_at' => now(), 'current_step' => 0, 'approved_at' => null,
            ]);
        });
    }

    /** The value of a target's measure (or formula) over a filter. */
    public function measure(Target $t, array $f, ?User $user): float
    {
        $one = function (string $m) use ($f, $user) {
            $q = $this->query->base($f, $user);

            return (float) match ($m) {
                'active_customers' => $q->distinct()->count('s.customer_id'),
                'transactions' => $q->count(),
                default => $q->sum("s.{$m}"),
            };
        };
        if ($t->metric !== 'formula') {
            return $one($t->metric);
        }
        $vars = [];
        foreach (\App\Modules\Sales\Support\Formula::variables((string) $t->formula) as $v) {
            $vars[$v] = $one($v);
        }

        return round(\App\Modules\Sales\Support\Formula::evaluate((string) $t->formula, $vars), 4);
    }

    /** Growth target: the target is a % change on the same span last year (§11.1 growth targets). */
    private function evaluateGrowth(Target $t, ?User $user, array $f, CarbonImmutable $start, CarbonImmutable $asOf, bool $notStarted): array
    {
        $current = $notStarted ? 0.0 : $this->measure($t, $f, $user);
        $last = $notStarted ? 0.0 : $this->measure($t, array_merge($f, ['from' => $start->subYear()->toDateString(), 'to' => $asOf->subYear()->toDateString()]), $user);
        $growth = $last > 0 ? round(($current - $last) / $last * 100, 1) : null;
        $target = (float) $t->target_value;
        $status = $notStarted ? 'not_started' : ($growth === null ? 'behind' : ($growth >= $target ? 'achieved' : ($growth >= $target * 0.9 ? 'at_risk' : 'behind')));

        return ['target' => $target, 'actual' => $growth ?? 0.0, 'achievement' => $target > 0 && $growth !== null ? round($growth / $target * 100, 1) : null,
            'variance' => round(($growth ?? 0) - $target, 1), 'remaining' => round(max(0, $target - ($growth ?? 0)), 1), 'days_left' => max(0, (int) CarbonImmutable::today()->diffInDays($t->end_date, false)),
            'required_daily' => null, 'forecast' => $growth ?? 0.0, 'forecast_achievement' => $target > 0 && $growth !== null ? round($growth / $target * 100, 1) : null,
            'status' => $status, 'unit' => '%', 'current_value' => round($current, 2), 'last_year_value' => round($last, 2)];
    }

    /** Composite target: the weighted achievement of its child targets, scored out of 100 (§11.4). */
    private function evaluateComposite(Target $t, ?User $user, bool $notStarted): array
    {
        $children = Target::where('parent_id', $t->id)->where('status', '!=', 'archived')->get();
        $weight = $children->sum('weight') ?: 1;
        $score = $children->sum(fn (Target $c) => min(150, (float) ($this->evaluate($c, $user)['achievement'] ?? 0)) * (float) $c->weight) / $weight;
        $forecast = $children->sum(fn (Target $c) => min(150, (float) ($this->evaluate($c, $user)['forecast_achievement'] ?? 0)) * (float) $c->weight) / $weight;
        $target = (float) $t->target_value ?: 100.0;

        return ['target' => $target, 'actual' => round($score, 1), 'achievement' => round($score / $target * 100, 1), 'variance' => round($score - $target, 1),
            'remaining' => round(max(0, $target - $score), 1), 'days_left' => max(0, (int) CarbonImmutable::today()->diffInDays($t->end_date, false)), 'required_daily' => null,
            'forecast' => round($forecast, 1), 'forecast_achievement' => round($forecast / $target * 100, 1), 'unit' => 'score', 'components' => $children->count(),
            'status' => $notStarted ? 'not_started' : ($score >= $target ? 'achieved' : ($forecast >= $target ? 'on_track' : ($forecast >= $target * 0.9 ? 'at_risk' : 'behind')))];
    }

    // ── Allocation (§11.5) ──────────────────────────────────────────────────────────────────────

    /** dimension key → [SalesAnalytics dimension, master-data option key] */
    public const ALLOCATE_BY = [
        'region_id' => 'region', 'customer_id' => 'customer', 'customer_group_id' => 'customer_group', 'product_id' => 'product',
        'category_id' => 'category', 'brand_id' => 'brand', 'sales_team_id' => 'sales_team', 'salesperson_id' => 'salesperson',
        'business_unit_id' => 'business_unit', 'distribution_centre_id' => 'distribution_centre',
    ];

    /**
     * Split a target into child drafts, one per member of a dimension. Shares come from the method:
     * equal, percentage (given), historical (same period last year's sales mix within the parent's
     * slice) or manual (given values). Children always add up exactly to the parent — rounding goes
     * to the largest child. Each child is approved on its own through the matrix.
     *
     * @param  array<int, float>  $given  member id → percentage or value (percentage / manual)
     * @return Collection<int, Target>
     */
    public function allocate(Target $parent, User $by, string $dimension, string $method, array $members, array $given = []): Collection
    {
        if (! isset(self::ALLOCATE_BY[$dimension])) {
            throw new RuntimeException('Targets cannot be allocated by that dimension.');
        }
        if (isset(($parent->dimensions ?? [])[$dimension])) {
            throw new RuntimeException('The target is already limited to one of those.');
        }
        $members = array_values(array_unique(array_map('intval', $members)));
        if (! $members) {
            throw new RuntimeException('Choose at least one to allocate to.');
        }

        $weights = match ($method) {
            'equal' => array_fill_keys($members, 1.0),
            'percentage', 'manual' => array_map('floatval', array_intersect_key($given, array_flip($members))) + array_fill_keys($members, 0.0),
            'historical' => $this->historicalWeights($parent, $by, $dimension, $members),
            default => throw new RuntimeException('Unknown allocation method.'),
        };
        if ($method === 'percentage' && abs(array_sum($weights) - 100) > 0.01) {
            throw new RuntimeException('Percentages must add up to 100 (they add up to ' . round(array_sum($weights), 2) . ').');
        }
        if ($method === 'manual' && abs(array_sum($weights) - $parent->target_value) > 0.01) {
            throw new RuntimeException('Values must add up to the parent target of ' . number_format($parent->target_value, 2) . '.');
        }
        $total = array_sum($weights);
        if ($total <= 0) {
            throw new RuntimeException($method === 'historical' ? 'There were no sales for these last year to weight by — use equal or percentage.' : 'Nothing to allocate.');
        }

        $values = [];
        foreach ($weights as $id => $w) {
            $values[$id] = round($parent->target_value * $w / $total, $parent->metric === 'quantity' || $parent->metric === 'active_customers' ? 0 : 2);
        }
        $largest = array_search(max($values), $values, true);
        $values[$largest] = round($values[$largest] + ($parent->target_value - array_sum($values)), 2);
        if ($parent->method === 'growth' || $parent->metric === 'formula') {
            // A rate is not divided: every child carries the same growth % or ratio.
            $values = array_fill_keys(array_keys($values), $parent->target_value);
        }

        $labels = collect(\App\Modules\Sales\Support\MasterData::labels(str_replace('_', '-', \Illuminate\Support\Str::plural(self::ALLOCATE_BY[$dimension])), array_keys($values)))->pluck('title', 'value');

        return DB::transaction(function () use ($parent, $by, $dimension, $method, $values, $labels) {
            Target::where('parent_id', $parent->id)->whereIn('status', ['draft', 'rejected'])->delete(); // re-allocating replaces unapproved children

            return collect($values)->map(fn ($v, $id) => Target::create([
                'parent_id' => $parent->id, 'allocation_method' => $method,
                'name' => $parent->name . ' — ' . ($labels[$id] ?? "#{$id}"),
                'metric' => $parent->metric, 'formula' => $parent->formula,
                'method' => $parent->method === 'composite' ? 'fixed' : $parent->method, 'period_type' => $parent->period_type,
                'start_date' => $parent->start_date, 'end_date' => $parent->end_date, 'target_value' => $v,
                'dimensions' => array_merge($parent->dimensions ?? [], [$dimension => $id]),
                'status' => 'draft', 'created_by' => $by->id,
            ]))->values();
        });
    }

    /** Children against the parent: do they still add up? */
    public function reconciliation(Target $parent): ?array
    {
        $children = Target::where('parent_id', $parent->id)->where('status', '!=', 'archived')->get();
        if ($children->isEmpty()) {
            return null;
        }
        if ($parent->method === 'growth' || $parent->metric === 'formula' || $parent->method === 'composite') {
            return ['children' => $children->count(), 'allocated' => $parent->target_value, 'parent' => $parent->target_value, 'difference' => 0.0, 'balanced' => true];
        }
        $sum = round($children->sum('target_value'), 2);

        return ['children' => $children->count(), 'allocated' => $sum, 'parent' => $parent->target_value,
            'difference' => round($parent->target_value - $sum, 2), 'balanced' => abs($parent->target_value - $sum) < 0.01];
    }

    private function historicalWeights(Target $parent, User $by, string $dimension, array $members): array
    {
        $dim = self::ALLOCATE_BY[$dimension];
        $f = array_merge($parent->dimensions ?? [], [
            'from' => CarbonImmutable::parse($parent->start_date)->subYear()->toDateString(),
            'to' => CarbonImmutable::parse($parent->end_date)->subYear()->toDateString(),
        ]);
        $measure = in_array($parent->metric, ['net_value', 'gross_value', 'quantity'], true) ? $parent->metric : 'net_value';
        $rows = collect(app(SalesAnalytics::class)->grouped($f, $by, [$dim], $measure, 'none', 10000)['rows'])->keyBy("{$dim}_id");

        return collect($members)->mapWithKeys(fn ($id) => [$id => max(0.0, (float) ($rows[$id]['value'] ?? 0))])->all();
    }

    /** Targets waiting for this user's decision. */
    public function inbox(User $user): Collection
    {
        return Target::where('status', 'submitted')->with('creator:id,email')->get()->filter(fn ($t) => $this->canApprove($user, $t))->values();
    }

    private function managerUserId(int $userId): ?int
    {
        $managerPersonId = DB::table('hr_people')->where('user_id', $userId)->value('manager_id');

        return $managerPersonId ? DB::table('hr_people')->where('id', $managerPersonId)->value('user_id') : null;
    }
}
