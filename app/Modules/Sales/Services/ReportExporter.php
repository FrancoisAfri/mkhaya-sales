<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Services\BI\BIQueryService;
use App\Modules\Sales\Support\SalesCompany;
use App\Modules\Sales\Support\StandardReports;
use Illuminate\Support\Facades\DB;

/**
 * Builds report files — the same data, scopes and layout whether a person clicks Export or a
 * schedule emails the report at 07:00. Files are written to a temporary path; callers stream
 * or attach them, then delete them.
 */
class ReportExporter
{
    public function __construct(private SalesAnalytics $analytics) {}

    /** A standard report by key or a saved report by id, as the report definition. */
    public function definition(string $source, string $ref): array
    {
        if ($source === 'saved') {
            $saved = DB::table('saved_reports')->where('company_id', SalesCompany::id())->where('id', (int) $ref)->first();
            abort_unless($saved, 404);

            return ['key' => 'custom', 'title' => $saved->name] + json_decode($saved->definition, true);
        }
        $catalogue = StandardReports::all();
        abort_unless(isset($catalogue[$ref]), 404);

        return ['key' => $ref] + $catalogue[$ref];
    }

    public function result(array $report, array $f, ?User $user): array
    {
        return match ($report['special'] ?? null) {
            'targets' => ['kind' => 'targets', 'rows' => app(TargetService::class)->performanceList($f, $user)],
            'forecast' => ['kind' => 'forecast'] + app(ForecastService::class)->monthly($f, $user),
            'imports' => ['kind' => 'imports', 'rows' => ImportBatch::where('target', 'sales')->whereBetween('created_at', [$f['from'], $f['to'] . ' 23:59:59'])
                ->latest('id')->limit(200)->get(['uuid', 'file_name', 'source', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'duplicate_rows', 'inserted_rows', 'totals', 'source_totals', 'period_from', 'period_to', 'completed_at'])],
            'quality' => ['kind' => 'quality', 'rows' => $this->quality()],
            default => ['kind' => 'grouped'] + $this->analytics->grouped($f, $user, $report['dims'], $report['measure'] ?? 'net_value', $report['compare'] ?? 'none', 2000, $report['order'] ?? 'desc'),
        };
    }

    /** Flatten any result into headings + rows. */
    public function table(array $result): array
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

    /**
     * Write a report to a temporary file.
     *
     * @return array{path: string, name: string, rows: int, mime: string}
     */
    public function file(array $report, array $f, ?User $user, string $format): array
    {
        [$headings, $rows] = $this->table($this->result($report, $f, $user));
        $name = str($report['title'])->slug() . '-' . $f['from'] . '-to-' . $f['to'];
        $tmp = tempnam(sys_get_temp_dir(), 'rep');

        if ($format === 'pdf') {
            file_put_contents($tmp, \Barryvdh\DomPDF\Facade\Pdf::loadView('sales.report-pdf', [
                'title' => $report['title'], 'period' => "{$f['from']} to {$f['to']}", 'headings' => $headings, 'rows' => $rows, 'company' => $this->companyName(),
            ])->setPaper('a4', count($headings) > 5 ? 'landscape' : 'portrait')->output());

            return ['path' => $tmp, 'name' => "{$name}.pdf", 'rows' => count($rows), 'mime' => 'application/pdf'];
        }
        if ($format === 'csv') {
            $out = fopen($tmp, 'w');
            fputcsv($out, $headings);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);

            return ['path' => $tmp, 'name' => "{$name}.csv", 'rows' => count($rows), 'mime' => 'text/csv'];
        }

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Report');
        $sheet->fromArray([[$report['title']], ["{$f['from']} to {$f['to']}"], [], $headings], null, 'A1');
        $sheet->fromArray($rows, null, 'A5');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A4:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($headings))) . '4')->getFont()->setBold(true);
        foreach (range(1, max(1, count($headings))) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($tmp);

        return ['path' => $tmp, 'name' => "{$name}.xlsx", 'rows' => count($rows), 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }

    /** A one-page PDF summary of a dashboard: headline figures, the narrative and the top lists. */
    public function dashboardPdf(string $uuid, array $f, ?User $user): array
    {
        $dash = DB::table('bi_dashboards')->where('company_id', SalesCompany::id())->where('uuid', $uuid)->first();
        abort_unless($dash, 404);
        $bi = app(BIQueryService::class);
        $kpi = $bi->run(['kind' => 'kpi', 'measures' => ['net_value', 'last_year', 'growth_pct', 'target', 'achievement_pct', 'active_customers']], $f, $user)['values'] ?? [];
        $lines = $bi->run(['kind' => 'narrative'], $f, $user)['lines'] ?? [];
        $top = fn (string $dim) => $bi->run(['kind' => 'table', 'measures' => ['net_value', 'last_year', 'growth_pct'], 'dimension' => $dim, 'top' => 10], $f, $user);
        $tables = collect(['customer' => 'Top customers', 'product' => 'Top products', 'region' => 'By region'])->map(function ($title, $dim) use ($top) {
            $d = $top($dim);

            return ['title' => $title, 'rows' => collect($d['categories'] ?? [])->map(fn ($c, $i) => [$c, $d['series'][0]['data'][$i] ?? 0, $d['series'][1]['data'][$i] ?? 0, $d['series'][2]['data'][$i] ?? null])->all()];
        })->values()->all();

        $tmp = tempnam(sys_get_temp_dir(), 'dash');
        file_put_contents($tmp, \Barryvdh\DomPDF\Facade\Pdf::loadView('sales.dashboard-pdf', [
            'title' => $dash->name, 'period' => "{$f['from']} to {$f['to']}", 'company' => $this->companyName(), 'kpi' => $kpi, 'lines' => $lines, 'tables' => $tables,
        ])->setPaper('a4')->output());

        return ['path' => $tmp, 'name' => str($dash->name)->slug() . "-{$f['from']}-to-{$f['to']}.pdf", 'rows' => 0, 'mime' => 'application/pdf'];
    }

    private function quality(): array
    {
        $c = SalesCompany::id();

        return [
            ['check' => 'Products created from sales files awaiting review', 'value' => DB::table('products')->where('company_id', $c)->where('needs_review', true)->count()],
            ['check' => 'Customers created from sales files awaiting review', 'value' => DB::table('customers')->where('company_id', $c)->where('needs_review', true)->count()],
            ['check' => 'Products without a category', 'value' => DB::table('products')->where('company_id', $c)->whereNull('category_id')->count()],
            ['check' => 'Products without a brand', 'value' => DB::table('products')->where('company_id', $c)->whereNull('brand_id')->count()],
            ['check' => 'Customers without a region', 'value' => DB::table('customers')->where('company_id', $c)->whereNull('region_id')->count()],
            ['check' => 'Customers without a location (for maps)', 'value' => DB::table('customers')->where('company_id', $c)->whereNull('latitude')->count()],
            ['check' => 'Rejected rows in imports (last 30 days)', 'value' => (int) DB::table('import_batches')->where('company_id', $c)->where('created_at', '>=', now()->subDays(30))->sum('invalid_rows')],
            ['check' => 'Imports waiting for confirmation', 'value' => DB::table('import_batches')->where('company_id', $c)->where('status', 'staged')->count()],
            ['check' => 'Corrections awaiting approval', 'value' => DB::table('sales_adjustments')->where('company_id', $c)->where('status', 'pending')->count()],
        ];
    }

    private function companyName(): ?string
    {
        return DB::table('companies')->where('id', SalesCompany::id())->value('name');
    }
}
