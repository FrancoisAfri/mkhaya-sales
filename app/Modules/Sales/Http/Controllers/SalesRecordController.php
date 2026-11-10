<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\SalesAdjustment;
use App\Modules\Sales\Models\SalesRecord;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Support\MasterData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sales records, data coverage and controlled corrections (SRS SAL-001, HIS-001). */
class SalesRecordController extends Controller
{
    public function index(Request $request, SalesQuery $sales): Response
    {
        abort_unless($request->user()->can('sales.view'), 403);
        $filters = $sales->filters($request);
        $base = $sales->base($filters, $request->user());

        $rows = (clone $base)
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('products as p', 'p.id', '=', 's.product_id')
            ->select('s.id', 's.transaction_date', 's.quantity', 's.unit_of_measure', 's.unit_price', 's.gross_value', 's.discount',
                's.net_value', 's.currency', 's.external_id', 's.is_adjusted', 's.import_batch_id',
                'c.customer_code', 'c.customer_name', 'p.product_code', 'p.product_name')
            ->orderByDesc('s.transaction_date')->orderByDesc('s.id')
            ->paginate((int) $request->query('per_page', 50))->withQueryString();

        $totals = (clone $base)->selectRaw('count(*) as rows, coalesce(sum(s.net_value),0) as net_value, coalesce(sum(s.gross_value),0) as gross_value, coalesce(sum(s.quantity),0) as quantity, count(distinct s.customer_id) as customers, count(distinct s.product_id) as products, min(s.transaction_date) as first_date, max(s.transaction_date) as last_date')->first();

        $coverage = (clone $base)->selectRaw("to_char(date_trunc('month', s.transaction_date), 'YYYY-MM') as month, count(*) as rows, sum(s.net_value) as net_value, sum(s.quantity) as quantity")
            ->groupByRaw("date_trunc('month', s.transaction_date)")->orderByRaw("date_trunc('month', s.transaction_date) desc")->limit(24)->get();

        return Inertia::render('Modules/Sales/Records/Index', [
            'rows' => $rows,
            'totals' => $totals,
            'coverage' => $coverage,
            'filters' => $filters,
            'options' => $sales->filterOptions($filters),
            'pendingAdjustments' => SalesAdjustment::where('status', 'pending')->count(),
            'canAdjust' => $request->user()->can('imports.historical'),
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        abort_unless($request->user()->can('sales.view'), 403);
        $record = SalesRecord::with(['customer:id,customer_code,customer_name', 'product:id,product_code,product_name',
            'batch:id,uuid,file_name,source,created_at', 'adjustments.requester:id,email'])->findOrFail($id);

        return Inertia::render('Modules/Sales/Records/Show', [
            'record' => $record,
            'fields' => SalesAdjustment::FIELDS,
            'canAdjust' => $request->user()->can('imports.historical'),
            'canApprove' => $request->user()->can('settings.manage'),
        ]);
    }

    /** A correction is requested with a reason; the imported value is kept alongside it (§12.3). */
    public function adjust(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('imports.historical'), 403);
        $record = SalesRecord::findOrFail($id);
        $data = $request->validate([
            'field' => ['required', Rule::in(SalesAdjustment::FIELDS)],
            'new_value' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $value = $record->getRawOriginal($data['field']);

        SalesAdjustment::create([
            'sales_record_id' => $record->id,
            'field' => $data['field'],
            'original_value' => $value === null ? null : (string) $value,
            'new_value' => $data['new_value'],
            'reason' => $data['reason'],
            'requested_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Correction submitted for approval.');
    }

    /** Someone other than the requester applies or rejects it. */
    public function decide(Request $request, int $adjustmentId): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        $adj = SalesAdjustment::findOrFail($adjustmentId);
        abort_if($adj->status !== 'pending', 422, 'Already decided.');
        if ($adj->requested_by === $request->user()->id) {
            return back()->withErrors(['adjustment' => 'You cannot approve your own correction.']);
        }
        $approve = $request->validate(['decision' => ['required', Rule::in(['apply', 'reject'])]])['decision'] === 'apply';

        DB::transaction(function () use ($adj, $approve, $request) {
            if ($approve) {
                $value = in_array($adj->field, ['customer_id', 'product_id'], true) ? (int) $adj->new_value : $adj->new_value;
                SalesRecord::whereKey($adj->sales_record_id)->update([$adj->field => $value, 'is_adjusted' => true, 'updated_at' => now()]);
                \App\Modules\Sales\Services\BI\BIQueryService::invalidate();   // figures and the monthly summary refresh
            }
            $adj->update(['status' => $approve ? 'applied' : 'rejected', 'approved_by' => $request->user()->id, 'decided_at' => now()]);
        });

        return back()->with('success', $approve ? 'Correction applied.' : 'Correction rejected.');
    }

    public function export(Request $request, SalesQuery $sales): StreamedResponse
    {
        abort_unless($request->user()->can('sales.view') && $request->user()->can('reports.export'), 403);
        $base = $sales->base($sales->filters($request), $request->user())
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('products as p', 'p.id', '=', 's.product_id')
            ->select('s.id', 's.transaction_date', 'c.customer_code', 'c.customer_name', 'p.product_code', 'p.product_name',
                's.quantity', 's.unit_of_measure', 's.unit_price', 's.gross_value', 's.discount', 's.net_value', 's.currency', 's.external_id')
            ->orderBy('s.transaction_date')->orderBy('s.id');
        activity('sales')->causedBy($request->user())->withProperties($request->query())->log('sales export');

        return response()->streamDownload(function () use ($base) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Customer code', 'Customer', 'Product code', 'Product', 'Cases', 'UOM', 'Unit price', 'Gross', 'Discount', 'Net', 'Currency', 'Source ref']);
            $base->chunk(2000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->transaction_date, $r->customer_code, $r->customer_name, $r->product_code, $r->product_name,
                        $r->quantity, $r->unit_of_measure, $r->unit_price, $r->gross_value, $r->discount, $r->net_value, $r->currency, $r->external_id]);
                }
            });
            fclose($out);
        }, 'sales-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
