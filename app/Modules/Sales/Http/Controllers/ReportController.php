<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Services\ForecastService;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Services\TargetService;
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
        $result = $this->run($report, $f, $request);
        [$headings, $rows] = $this->table($result);

        DB::table('report_exports')->insert([
            'company_id' => \App\Modules\Sales\Support\SalesCompany::id(), 'user_id' => $request->user()->id, 'report' => $report['title'],
            'format' => $format, 'parameters' => json_encode($request->query()), 'rows' => count($rows), 'created_at' => now(),
        ]);
        activity('sales')->causedBy($request->user())->withProperties(['report' => $report['title'], 'format' => $format])->log('report export');

        $name = str($report['title'])->slug() . '-' . $f['from'] . '-to-' . $f['to'];
        if ($format === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('sales.report-pdf', [
                'title' => $report['title'], 'period' => "{$f['from']} to {$f['to']}", 'headings' => $headings, 'rows' => $rows,
                'company' => DB::table('companies')->where('id', \App\Modules\Sales\Support\SalesCompany::id())->value('name'),
            ])->setPaper('a4', count($headings) > 5 ? 'landscape' : 'portrait')->download("{$name}.pdf");
        }
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headings, $rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, $headings);
                foreach ($rows as $r) {
                    fputcsv($out, $r);
                }
                fclose($out);
            }, "{$name}.csv", ['Content-Type' => 'text/csv']);
        }

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Report');
        $sheet->fromArray([[$report['title']], ["{$f['from']} to {$f['to']}"], [], $headings], null, 'A1');
        $sheet->fromArray($rows, null, 'A5');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A4:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headings)) . '4')->getFont()->setBold(true);
        foreach (range(1, count($headings)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'rep');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($tmp);

        return response()->download($tmp, "{$name}.xlsx")->deleteFileAfterSend();
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
        $user = $request->user();

        return match ($report['special'] ?? null) {
            'targets' => ['kind' => 'targets', 'rows' => app(TargetService::class)->performanceList($f, $user)],
            'forecast' => ['kind' => 'forecast'] + app(ForecastService::class)->monthly($f, $user),
            'imports' => ['kind' => 'imports', 'rows' => ImportBatch::where('target', 'sales')->whereBetween('created_at', [$f['from'], $f['to'] . ' 23:59:59'])
                ->latest('id')->limit(200)->get(['uuid', 'file_name', 'source', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'duplicate_rows', 'inserted_rows', 'totals', 'source_totals', 'period_from', 'period_to', 'completed_at'])],
            'quality' => ['kind' => 'quality', 'rows' => $this->quality()],
            default => ['kind' => 'grouped'] + $this->analytics->grouped($f, $user, $report['dims'], $report['measure'] ?? 'net_value', $report['compare'] ?? 'none', 2000, $report['order'] ?? 'desc'),
        };
    }

    private function quality(): array
    {
        $count = fn ($q) => $q->count();

        return [
            ['check' => 'Products created from sales files awaiting review', 'value' => DB::table('products')->where('company_id', $this->company())->where('needs_review', true)->count()],
            ['check' => 'Customers created from sales files awaiting review', 'value' => DB::table('customers')->where('company_id', $this->company())->where('needs_review', true)->count()],
            ['check' => 'Products without a category', 'value' => DB::table('products')->where('company_id', $this->company())->whereNull('category_id')->count()],
            ['check' => 'Products without a brand', 'value' => DB::table('products')->where('company_id', $this->company())->whereNull('brand_id')->count()],
            ['check' => 'Customers without a region', 'value' => DB::table('customers')->where('company_id', $this->company())->whereNull('region_id')->count()],
            ['check' => 'Rejected rows in imports (last 30 days)', 'value' => (int) DB::table('import_batches')->where('company_id', $this->company())->where('created_at', '>=', now()->subDays(30))->sum('invalid_rows')],
            ['check' => 'Imports waiting for confirmation', 'value' => DB::table('import_batches')->where('company_id', $this->company())->where('status', 'staged')->count()],
            ['check' => 'Corrections awaiting approval', 'value' => DB::table('sales_adjustments')->where('company_id', $this->company())->where('status', 'pending')->count()],
        ];
    }

    /** Flatten any result into headings + rows for export. */
    private function table(array $result): array
    {
        return match ($result['kind']) {
            'targets' => [['Target', 'Metric', 'From', 'To', 'Target', 'Actual', 'Achievement %', 'Variance', 'Remaining', 'Forecast', 'Status'],
                collect($result['rows'])->map(fn ($r) => [$r['name'], $r['metric'], $r['start_date'], $r['end_date'], $r['performance']['target'], $r['performance']['actual'],
                    $r['performance']['achievement'], $r['performance']['variance'], $r['performance']['remaining'], $r['performance']['forecast'], $r['performance']['status']])->all()],
            'forecast' => [['Month', 'Actual', 'Forecast', 'Low', 'High', 'Method'],
                collect($result['months'])->map(fn ($m) => [$m['month'], $m['actual'], $m['forecast'], $m['low'], $m['high'], $m['method']])->all()],
            'imports' => [['File', 'Source', 'Status', 'Rows', 'Valid', 'Invalid', 'Duplicates', 'Added', 'Net value', 'Source net value', 'From', 'To'],
                collect($result['rows'])->map(fn ($b) => [$b->file_name, $b->source, $b->status, $b->total_rows, $b->valid_rows, $b->invalid_rows, $b->duplicate_rows, $b->inserted_rows,
                    $b->totals['net_value'] ?? null, $b->source_totals['net_value'] ?? null, $b->period_from?->toDateString(), $b->period_to?->toDateString()])->all()],
            'quality' => [['Check', 'Count'], collect($result['rows'])->map(fn ($r) => [$r['check'], $r['value']])->all()],
            default => (function () use ($result) {
                $first = $result['rows'][0] ?? null;
                $dims = $first ? array_values(array_filter(array_keys($first), fn ($k) => isset(SalesAnalytics::DIMENSIONS[$k]))) : [];
                $cols = array_merge($dims, ['value', 'share'], $first && array_key_exists('previous', $first) ? ['previous', 'change', 'growth'] : []);
                $labels = array_map(fn ($c) => SalesAnalytics::DIMENSIONS[$c] ?? ['value' => 'Value', 'share' => 'Share %', 'previous' => 'Comparison', 'change' => 'Change', 'growth' => 'Growth %'][$c], $cols);

                return [$labels, collect($result['rows'])->map(fn ($r) => array_map(fn ($c) => $r[$c] ?? null, $cols))->all()];
            })(),
        };
    }

    private function company(): int
    {
        return (int) app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id();
    }
}
