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
        $q = $this->query->base($f, $user);

        $actual = $asOf->lt($start) ? 0.0 : (float) match ($t->metric) {
            'active_customers' => $q->distinct()->count('s.customer_id'),
            'transactions' => $q->count(),
            default => $q->sum("s.{$t->metric}"),
        };

        $target = (float) $t->target_value;
        $today = CarbonImmutable::today();
        $totalDays = (int) $start->diffInDays($end) + 1;
        $elapsed = $asOf->lt($start) ? 0 : (int) $start->diffInDays($asOf) + 1;
        // Selling days still to come after today (today counts as done).
        $remainingDays = $today->gte($end) ? 0 : (int) $today->diffInDays($end) + ($today->lt($start) ? 1 : 0);
        $remaining = max(0, $target - $actual);
        $forecast = $elapsed > 0 && $t->metric !== 'active_customers' ? $actual / $elapsed * $totalDays : $actual;
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
