<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\User\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Target;
use App\Modules\Sales\Models\TargetApprovalRule;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Services\TargetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/** Targets, approvals inbox and the approvals matrix setup (SRS TGT-001). */
class TargetController extends Controller
{
    public function __construct(private TargetService $targets, private SalesQuery $query) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('dashboards.view') || $user->can('targets.create'), 403);

        $list = Target::with('creator:id,email')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('start_date')->paginate(25)->withQueryString();
        $list->getCollection()->transform(fn (Target $t) => $this->targets->row($t, $user) + [
            'id' => $t->id, 'target_value_max' => $t->target_value_max, 'formula' => $t->formula, 'weight' => $t->weight, 'period_type' => $t->period_type, 'notes' => $t->notes,
            'creator' => $t->creator?->email, 'can_approve' => $this->targets->canApprove($user, $t),
            'can_edit' => $user->can('targets.edit') && in_array($t->status, ['draft', 'rejected'], true),
            'can_submit' => $user->can('targets.submit') && in_array($t->status, ['draft', 'rejected'], true),
            'can_revise' => $user->can('targets.revise') && $t->status === 'approved',
        ]);

        return Inertia::render('Modules/Sales/Targets/Index', [
            'targets' => $list,
            'filters' => $request->only('status'),
            'metrics' => Target::METRICS, 'methods' => Target::METHODS, 'periods' => Target::PERIODS, 'dimensions' => Target::DIMENSIONS,
            'options' => $this->query->filterOptions([
                'customer_id' => $list->getCollection()->pluck('dimensions.customer_id')->filter()->all(),
                'product_id' => $list->getCollection()->pluck('dimensions.product_id')->filter()->all(),
            ]),
            'canCreate' => $user->can('targets.create'),
        ]);
    }

    public function show(Request $request, Target $target): Response
    {
        abort_unless($request->user()->can('dashboards.view') || $request->user()->can('targets.create'), 403);
        $target->load(['approvals.decider:id,email', 'revisions.reviser:id,email', 'creator:id,email', 'parent:id,uuid,name']);

        return Inertia::render('Modules/Sales/Targets/Show', [
            'target' => $target,
            'row' => $this->targets->row($target, $request->user()),
            'steps' => $this->targets->steps($target)->map(fn ($rules, $i) => ['step' => $i + 1, 'approvers' => $rules->map(fn ($r) => $this->describe($r))->all()]),
            'canApprove' => $this->targets->canApprove($request->user(), $target),
            'children' => $target->children()->get()->map(fn (Target $c) => $this->targets->row($c, $request->user())),
            'reconciliation' => $this->targets->reconciliation($target),
            'canAllocate' => $request->user()->can('targets.create') && $target->status !== 'archived',
            'metrics' => Target::METRICS, 'dimensions' => Target::DIMENSIONS, 'options' => $this->query->filterOptions(['customer_id' => [$target->dimensions['customer_id'] ?? null], 'product_id' => [$target->dimensions['product_id'] ?? null]])
                + ['distribution_centres' => \App\Modules\Sales\Support\MasterData::options('distribution-centres')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('targets.create'), 403);
        $t = Target::create($this->validated($request) + ['created_by' => $request->user()->id, 'status' => 'draft']);
        if ($request->boolean('submit_now') && $request->user()->can('targets.submit')) {
            $this->targets->submit($t, $request->user());
        }

        return back()->with('success', 'Target saved.');
    }

    public function update(Request $request, Target $target): RedirectResponse
    {
        abort_unless($request->user()->can('targets.edit'), 403);
        abort_unless(in_array($target->status, ['draft', 'rejected'], true), 422, 'Approved targets are changed through a revision.');
        $target->update($this->validated($request));

        return back()->with('success', 'Target updated.');
    }

    public function submit(Request $request, Target $target): RedirectResponse
    {
        abort_unless($request->user()->can('targets.submit'), 403);

        return $this->attempt(fn () => $this->targets->submit($target, $request->user()), 'Submitted for approval.');
    }

    public function decide(Request $request, Target $target): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'comment' => ['nullable', 'string', 'max:2000']]);
        if ($data['decision'] === 'reject' && empty($data['comment'])) {
            return back()->withErrors(['comment' => 'Say why the target is rejected.']);
        }

        return $this->attempt(fn () => $this->targets->decide($target, $request->user(), $data['decision'] === 'approve', $data['comment'] ?? null),
            $data['decision'] === 'approve' ? 'Approved.' : 'Rejected.');
    }

    public function revise(Request $request, Target $target): RedirectResponse
    {
        abort_unless($request->user()->can('targets.revise'), 403);
        $data = $request->validate(['target_value' => ['required', 'numeric', 'min:0'], 'target_value_max' => ['nullable', 'numeric'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->attempt(fn () => $this->targets->revise($target, $request->user(), (float) $data['target_value'], $data['reason'], isset($data['target_value_max']) ? (float) $data['target_value_max'] : null),
            'Revision submitted for approval; the previous value is kept in the history.');
    }

    public function allocate(Request $request, Target $target): RedirectResponse
    {
        abort_unless($request->user()->can('targets.create'), 403);
        $data = $request->validate([
            'dimension' => ['required', Rule::in(array_keys(TargetService::ALLOCATE_BY))],
            'method' => ['required', Rule::in(['equal', 'percentage', 'historical', 'manual'])],
            'members' => ['required', 'array', 'min:1', 'max:500'],
            'members.*' => ['integer'],
            'given' => ['nullable', 'array'],
            'given.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->attempt(fn () => $this->targets->allocate($target, $request->user(), $data['dimension'], $data['method'], $data['members'], $data['given'] ?? []),
            'Child targets created as drafts — submit them for approval.');
    }

    public function archive(Request $request, Target $target): RedirectResponse
    {
        abort_unless($request->user()->can('targets.edit'), 403);
        $target->update(['status' => 'archived']);

        return back()->with('success', 'Target archived.');
    }

    public function approvals(Request $request): Response
    {
        abort_unless($request->user()->can('targets.approve'), 403);

        return Inertia::render('Modules/Sales/Targets/Approvals', [
            'targets' => $this->targets->inbox($request->user())->map(fn (Target $t) => $this->targets->row($t, $request->user()) + [
                'creator' => $t->creator?->email, 'submitted_at' => $t->submitted_at?->toIso8601String(), 'step' => $t->current_step + 1,
                'steps' => max(1, $this->targets->steps($t)->count()), 'target_value' => $t->target_value,
            ]),
            'metrics' => Target::METRICS,
        ]);
    }

    // ── Approvals matrix (setup) ────────────────────────────────────────────────────────────────

    public function matrix(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return Inertia::render('Modules/Sales/Targets/Matrix', [
            'rules' => TargetApprovalRule::with('approver:id,email')->orderBy('min_value')->orderBy('step')->get(),
            'metrics' => Target::METRICS,
            'roles' => Role::whereHas('permissions', fn ($q) => $q->where('name', 'targets.approve'))->orderBy('name')->pluck('name'),
            'users' => User::where('company_id', \App\Modules\Sales\Support\SalesCompany::id())->where('status', 1)->get()
                ->filter(fn (User $u) => $u->can('targets.approve'))->map(fn ($u) => ['value' => $u->id, 'title' => $u->email])->values(),
        ]);
    }

    public function saveRule(Request $request, ?int $id = null): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'metric' => ['nullable', Rule::in(array_keys(Target::METRICS))],
            'min_value' => ['nullable', 'numeric', 'min:0'],
            'max_value' => ['nullable', 'numeric', 'gte:min_value'],
            'step' => ['required', 'integer', 'between:1,10'],
            'approver_type' => ['required', Rule::in(['role', 'user', 'creator_manager'])],
            'approver_role' => ['nullable', 'required_if:approver_type,role', 'string', Rule::exists('roles', 'name')],
            'approver_user_id' => ['nullable', 'required_if:approver_type,user', 'integer', Rule::exists('users', 'id')->where('company_id', \App\Modules\Sales\Support\SalesCompany::id())],
            'allow_creator' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $id ? TargetApprovalRule::findOrFail($id)->update($data) : TargetApprovalRule::create($data);

        return back()->with('success', 'Approval rule saved.');
    }

    public function deleteRule(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        TargetApprovalRule::findOrFail($id)->update(['is_active' => false]);

        return back()->with('success', 'Rule switched off.');
    }

    // ── Alert settings (§17.3) ──────────────────────────────────────────────────────────────────

    public function alerts(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return Inertia::render('Modules/Sales/Targets/Alerts', ['settings' => \App\Modules\Sales\Services\PerformanceAlerts::settingsFor(\App\Modules\Sales\Support\SalesCompany::id())]);
    }

    public function saveAlerts(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        $data = $request->validate([
            'target_achieved.enabled' => ['boolean'],
            'target_at_risk.enabled' => ['boolean'], 'target_at_risk.threshold' => ['required', 'numeric', 'between:1,150'],
            'unusual_movement.enabled' => ['boolean'], 'unusual_movement.threshold' => ['required', 'numeric', 'between:5,500'],
            'run_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);
        \Illuminate\Support\Facades\DB::table('sales_alert_settings')->updateOrInsert(['company_id' => \App\Modules\Sales\Support\SalesCompany::id()],
            ['settings' => json_encode($data), 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('success', 'Alert settings saved.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'metric' => ['required', Rule::in(array_keys(Target::METRICS))],
            'method' => ['required', Rule::in(array_keys(Target::METHODS))],
            'period_type' => ['required', Rule::in(Target::PERIODS)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'target_value_max' => ['nullable', 'required_if:method,range', 'numeric', 'gte:target_value'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'formula' => ['nullable', 'required_if:metric,formula', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        if (($data['metric'] ?? null) === 'formula') {
            try {
                \App\Modules\Sales\Support\Formula::variables($data['formula']);
            } catch (\RuntimeException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages(['formula' => $e->getMessage()]);
            }
        } else {
            $data['formula'] = null;
        }
        $data['weight'] = $data['weight'] ?? 1;
        $data['dimensions'] = array_intersect_key(array_filter($data['dimensions'] ?? []), Target::DIMENSIONS) ?: null;

        return $data;
    }

    private function describe(TargetApprovalRule $r): string
    {
        return match ($r->approver_type) {
            'role' => "any {$r->approver_role}",
            'user' => $r->approver?->email ?? 'a named user',
            'creator_manager' => "the creator's manager",
        } . ($r->allow_creator ? ' (creator may approve)' : '');
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['target' => $e->getMessage()]);
        }

        return back()->with('success', $ok);
    }
}
