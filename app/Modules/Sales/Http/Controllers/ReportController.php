<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\ReportExporter;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Support\StandardReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Standard reports, the report builder, saved reports and exports (SRS REP-001). */
class ReportController extends Controller
{
    public function __construct(private SalesAnalytics $analytics, private SalesQuery $query) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('sales.view'), 403);
        $report = $this->resolve($request);
        $f = $this->query->filters($request);
        [$from, $to] = $this->analytics->period($f);
        $f += ['from' => $from->toDateString(), 'to' => $to->toDateString()];

        return Inertia::render('Modules/Sales/Reports/Index', [
            'catalogue' => collect(StandardReports::all())->map(fn ($r, $k) => ['key' => $k, 'title' => $r['title']])->values(),
            'saved' => DB::table('saved_reports')->where('company_id', \App\Modules\Sales\Support\SalesCompany::id())
                ->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('shared', true))->orderBy('name')->get(['id', 'name', 'definition', 'shared']),
            'report' => $report,
            'result' => $this->run($report, $f, $request),
            'dimensions' => SalesAnalytics::DIMENSIONS,
            'measures' => SalesAnalytics::MEASURES,
            'filters' => $f,
            'options' => $this->query->filterOptions($f ?? []),
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('sales.view'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'shared' => ['boolean'],
            'definition' => ['required', 'array'], 'definition.dims' => ['required', 'array', 'min:1', 'max:3'],
            'definition.dims.*' => [Rule::in(array_keys(SalesAnalytics::DIMENSIONS))],
            'definition.measure' => ['required', Rule::in(array_keys(SalesAnalytics::MEASURES))],
            'definition.compare' => ['required', Rule::in(['none', 'previous_year', 'previous_period'])],
            'definition.order' => ['nullable', 'string'], 'definition.filters' => ['nullable', 'array'],
        ]);
        DB::table('saved_reports')->insert([
            'company_id' => \App\Modules\Sales\Support\SalesCompany::id(), 'user_id' => $request->user()->id, 'name' => $data['name'],
            'definition' => json_encode($data['definition']), 'shared' => $data['shared'] ?? false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Report saved.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        DB::table('saved_reports')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Saved report removed.');
    }

    /** Excel, CSV or PDF of exactly what is on screen — same filters, same scopes (§14.3). */
    public function export(Request $request, string $format)
    {
        abort_unless($request->user()->can('sales.view') && $request->user()->can('reports.export'), 403);
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);
        $report = $this->resolve($request);
        $f = $this->query->filters($request);
        [$from, $to] = $this->analytics->period($f);
        $f += ['from' => $from->toDateString(), 'to' => $to->toDateString()];
        $file = app(ReportExporter::class)->file($report, $f, $request->user(), $format);

        DB::table('report_exports')->insert([
            'company_id' => \App\Modules\Sales\Support\SalesCompany::id(), 'user_id' => $request->user()->id, 'report' => $report['title'],
            'format' => $format, 'parameters' => json_encode($request->query()), 'rows' => $file['rows'], 'created_at' => now(),
        ]);
        activity('sales')->causedBy($request->user())->withProperties(['report' => $report['title'], 'format' => $format])->log('report export');

        return response()->download($file['path'], $file['name'], ['Content-Type' => $file['mime']])->deleteFileAfterSend();
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    /** A standard report key, a saved report, or a builder definition from the query string. */
    private function resolve(Request $request): array
    {
        $catalogue = StandardReports::all();
        if ($id = $request->query('saved')) {
            $saved = DB::table('saved_reports')->where('id', $id)->first();
            abort_unless($saved, 404);

            return ['key' => 'custom', 'title' => $saved->name] + json_decode($saved->definition, true);
        }
        if ($request->query('dims')) {
            $dims = array_values(array_intersect((array) $request->query('dims'), array_keys(SalesAnalytics::DIMENSIONS)));

            return ['key' => 'custom', 'title' => 'Custom report', 'dims' => $dims ?: ['customer'],
                'measure' => array_key_exists($request->query('measure'), SalesAnalytics::MEASURES) ? $request->query('measure') : 'net_value',
                'compare' => in_array($request->query('compare'), ['none', 'previous_year', 'previous_period'], true) ? $request->query('compare') : 'none',
                'order' => $request->query('order', 'desc')];
        }
        $key = $request->query('report', 'monthly');
        abort_unless(isset($catalogue[$key]), 404);

        return ['key' => $key] + $catalogue[$key];
    }

    private function run(array $report, array $f, Request $request): array
    {
        return app(ReportExporter::class)->result($report, $f, $request->user());
    }

    private function company(): int
    {
        return (int) app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id();
    }
}
