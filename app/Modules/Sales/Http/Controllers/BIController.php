<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\BI\BIQueryService;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Services\TargetService;
use App\Modules\Sales\Support\DashboardTemplates;
use App\Modules\Sales\Support\Formula;
use App\Modules\Sales\Support\SalesCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** BI dashboards, the dashboard builder and the visual data endpoint (BI spec §4–§7). */
class BIController extends Controller
{
    public function __construct(private BIQueryService $bi, private SalesQuery $query) {}

    /** The landing page after login: the user's own default, else their role's dashboard (§5). */
    public function home(Request $request): Response|RedirectResponse
    {
        // A platform administrator works inside no company until they pick one: send them to the
        // company list (where "View as company" opens that company's dashboard), not the old /home.
        if (! SalesCompany::id()) {
            return $request->user()->is_platform_admin || $request->user()->is_master_admin
                ? redirect('/platform/companies')->with('info', 'Choose a company and use "View as company" to open its sales dashboard.')
                : redirect()->route('dashboard.legacy');
        }
        if (! $request->user()->can('dashboards.view')) {
            return redirect()->route('dashboard.legacy');
        }
        $roles = $request->user()->getRoleNames()->all();
        $key = DashboardTemplates::forRoles($roles);
        $dash = $this->visible($request)->where('role_landing', $key)->first() ?? $this->seedTemplate($key, $request, true);

        return $this->render($request, $dash, true);
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('dashboards.view'), 403);

        return Inertia::render('Modules/Sales/BI/Index', [
            'dashboards' => $this->visible($request)->orderBy('name')->get(['uuid', 'name', 'description', 'visibility', 'owner_id', 'template_key', 'role_landing', 'updated_at']),
            'templates' => collect(DashboardTemplates::all())->map(fn ($t, $k) => ['key' => $k, 'name' => $t['name'], 'widgets' => count($t['widgets'])])->values(),
            'canEdit' => $request->user()->can('reports.export') || $request->user()->can('settings.manage'),
        ]);
    }

    public function show(Request $request, string $uuid): Response
    {
        return $this->render($request, $this->visible($request)->where('uuid', $uuid)->firstOrFail(), false);
    }

    /** A new dashboard, blank or copied from a template (§4.6). */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canBuild($request), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'template' => ['nullable', Rule::in(array_keys(DashboardTemplates::all()))]]);
        $uuid = (string) Str::uuid();
        DB::table('bi_dashboards')->insert([
            'uuid' => $uuid, 'company_id' => SalesCompany::id(), 'owner_id' => $request->user()->id, 'name' => $data['name'],
            'visibility' => 'private', 'template_key' => $data['template'] ?? null,
            'widgets' => json_encode($data['template'] ?? null ? DashboardTemplates::widgets($data['template']) : []),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect("/sales/bi/{$uuid}?edit=1");
    }

    public function update(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $dash = $this->visible($request)->where('uuid', $uuid)->firstOrFail();
        abort_unless($this->canEdit($request, $dash), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500'],
            'widgets' => ['sometimes', 'array', 'max:60'],
            'widgets.*.id' => ['required', 'string', 'max:20'], 'widgets.*.type' => ['required', 'string', 'max:40'],
            'widgets.*.title' => ['nullable', 'string', 'max:150'], 'widgets.*.config' => ['required', 'array'],
            'widgets.*.x' => ['required', 'integer', 'min:0', 'max:11'], 'widgets.*.y' => ['required', 'integer', 'min:0'],
            'widgets.*.w' => ['required', 'integer', 'min:1', 'max:12'], 'widgets.*.h' => ['required', 'integer', 'min:2', 'max:30'],
            'visibility' => ['sometimes', Rule::in(['private', 'shared', 'organisation'])],
            'shared_roles' => ['nullable', 'array'], 'shared_users' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
        ]);
        foreach (['widgets', 'shared_roles', 'shared_users', 'filters'] as $j) {
            if (array_key_exists($j, $data)) {
                $data[$j] = json_encode($data[$j]);
            }
        }
        // Keep the layout as it was, so it can be restored (gap 12); a changed dashboard loses its certification.
        if (array_key_exists('widgets', $data) && $data['widgets'] !== $dash->widgets) {
            BICollaborationController::snapshot($dash, $request->user()->id);
            $data['certified'] = false;
        }
        DB::table('bi_dashboards')->where('id', $dash->id)->update($data + ['updated_at' => now()]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Dashboard saved.');
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $dash = $this->visible($request)->where('uuid', $uuid)->firstOrFail();
        abort_unless($dash->owner_id === $request->user()->id || $request->user()->can('settings.manage'), 403);
        DB::table('bi_dashboards')->where('id', $dash->id)->delete();

        return redirect('/sales/bi')->with('success', 'Dashboard deleted.');
    }

    /** Data for one visual — always computed server-side by the measure engine (§9). */
    public function query(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('dashboards.view'), 403);
        $data = $request->validate(['config' => ['required', 'array'], 'filters' => ['nullable', 'array']]);

        return response()->json($this->bi->run($data['config'], $this->query->filters(new Request($data['filters'] ?? [])), $request->user()));
    }

    /** Board pack (gap 10): the dashboard as PowerPoint or a print-ready PDF, charts as drawn on screen. */
    public function boardPack(Request $request, string $uuid, \App\Modules\Sales\Services\BI\BoardPackService $pack)
    {
        abort_unless($request->user()->can('dashboards.view') && $request->user()->can('reports.export'), 403);
        $dash = $this->visible($request)->where('uuid', $uuid)->firstOrFail();
        $data = $request->validate([
            'format' => ['required', Rule::in(['pptx', 'pdf'])], 'period' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'max:60'], 'items.*.title' => ['required', 'string', 'max:150'],
            'items.*.image' => ['nullable', 'string', 'max:4000000', 'starts_with:data:image/png;base64,'],
            'items.*.text' => ['nullable', 'string', 'max:600'],
        ]);
        $file = $pack->build($data['format'], $dash->name, (string) DB::table('companies')->where('id', SalesCompany::id())->value('name'),
            $data['period'] ?? '', $data['items']);
        activity('sales')->causedBy($request->user())->withProperties(['dashboard' => $dash->name, 'format' => $data['format']])->log('board pack export');

        return response()->download($file['path'], $file['name'], ['Content-Type' => $file['mime']])->deleteFileAfterSend();
    }

    /** "Ask a question, get a chart" (gap 2): words → a validated visual configuration. */
    public function ask(Request $request, \App\Modules\Sales\Services\BI\AskService $ask): JsonResponse
    {
        abort_unless($request->user()->can('dashboards.view'), 403);
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:300']]);

        return response()->json($ask->ask($data['question']));
    }

    /** Targets table, data freshness — the non-chart widgets (§5.1 bottom section). */
    public function status(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('dashboards.view'), 403);
        $c = SalesCompany::id();
        $f = $this->query->filters(new Request((array) $request->input('filters', [])));

        return response()->json([
            'last_sale' => app(SalesAnalytics::class)->lastSaleDate(),
            'imports' => DB::table('import_batches')->where('company_id', $c)->latest('id')->limit(5)->get(['uuid', 'file_name', 'source', 'status', 'inserted_rows', 'invalid_rows', 'created_at']),
            'last_email' => DB::table('mailbox_runs')->where('company_id', $c)->where('status', 'imported')->max('finished_at'),
            'awaiting' => DB::table('import_batches')->where('company_id', $c)->where('status', 'staged')->count(),
            'needs_review' => DB::table('products')->where('company_id', $c)->where('needs_review', true)->count() + DB::table('customers')->where('company_id', $c)->where('needs_review', true)->count(),
            'targets' => app(TargetService::class)->performanceList($f + ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()], $request->user()),
        ]);
    }

    public function saveBookmark(Request $request, string $uuid): RedirectResponse
    {
        $dash = $this->visible($request)->where('uuid', $uuid)->firstOrFail();
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'state' => ['required', 'array']]);
        DB::table('bi_bookmarks')->insert(['company_id' => SalesCompany::id(), 'dashboard_id' => $dash->id, 'user_id' => $request->user()->id,
            'name' => $data['name'], 'state' => json_encode($data['state']), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'View saved.');
    }

    /** Custom measures built with the controlled formula builder (§7.3). */
    public function saveMeasure(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        $data = $request->validate(['label' => ['required', 'string', 'max:120'], 'formula' => ['required', 'string', 'max:255'], 'format' => ['required', Rule::in(['currency', 'number', 'percent'])]]);
        try {
            Formula::variables($data['formula']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['formula' => $e->getMessage()]);
        }
        DB::table('bi_measures')->updateOrInsert(['company_id' => SalesCompany::id(), 'key' => Str::slug($data['label'], '_')],
            $data + ['created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        BIQueryService::invalidate();

        return back()->with('success', 'Measure saved.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    private function render(Request $request, object $dash, bool $landing): Response
    {
        $f = $this->query->filters($request);

        return Inertia::render('Modules/Sales/BI/Dashboard', [
            'dashboard' => ['uuid' => $dash->uuid, 'name' => $dash->name, 'description' => $dash->description, 'visibility' => $dash->visibility,
                'widgets' => json_decode($dash->widgets, true), 'shared_roles' => json_decode($dash->shared_roles ?? '[]', true),
                'owner_id' => $dash->owner_id, 'template_key' => $dash->template_key, 'certified' => (bool) ($dash->certified ?? false)],
            'canCertify' => $request->user()->can('settings.manage'),
            'canShareLink' => $this->canEdit($request, $dash) || $request->user()->can('reports.export'),
            'landing' => $landing,
            'editing' => $request->boolean('edit') && $this->canEdit($request, $dash),
            'canEdit' => $this->canEdit($request, $dash),
            'filters' => $f,
            'options' => $this->query->filterOptions($f),
            'catalogue' => ['measures' => $this->bi->catalogue(), 'dimensions' => SalesAnalytics::DIMENSIONS, 'periods' => BIQueryService::PERIODS, 'grains' => BIQueryService::GRAINS],
            'bookmarks' => DB::table('bi_bookmarks')->where('dashboard_id', $dash->id)->where('user_id', $request->user()->id)->get(['id', 'name', 'state']),
            'roles' => \Spatie\Permission\Models\Role::orderBy('name')->pluck('name'),
            'lastSale' => app(SalesAnalytics::class)->lastSaleDate(),
        ]);
    }

    /** Dashboards this user may open: their own, shared with them or their role, or organisation-wide (§4.5). */
    private function visible(Request $request)
    {
        $u = $request->user();
        $roles = $u->getRoleNames()->all();

        return DB::table('bi_dashboards')->where('company_id', SalesCompany::id())->where(function ($q) use ($u, $roles) {
            $q->where('owner_id', $u->id)->orWhere('visibility', 'organisation')
                ->orWhere(fn ($s) => $s->where('visibility', 'shared')->where(fn ($x) => $x->whereJsonContains('shared_users', $u->id)
                    ->orWhere(fn ($r) => array_map(fn ($role) => $r->orWhereJsonContains('shared_roles', $role), $roles ?: ['__none__']))));
        });
    }

    private function seedTemplate(string $key, Request $request, bool $landing): object
    {
        $uuid = (string) Str::uuid();
        DB::table('bi_dashboards')->insert([
            'uuid' => $uuid, 'company_id' => SalesCompany::id(), 'owner_id' => null, 'name' => DashboardTemplates::all()[$key]['name'],
            'visibility' => 'organisation', 'template_key' => $key, 'role_landing' => $landing ? $key : null,
            'widgets' => json_encode(DashboardTemplates::widgets($key)), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('bi_dashboards')->where('uuid', $uuid)->first();
    }

    private function canBuild(Request $request): bool
    {
        return $request->user()->can('dashboards.view') && ($request->user()->can('reports.export') || $request->user()->can('settings.manage'));
    }

    private function canEdit(Request $request, object $dash): bool
    {
        return $dash->owner_id === $request->user()->id || $request->user()->can('settings.manage');
    }
}
