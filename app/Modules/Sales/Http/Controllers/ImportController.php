<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Jobs\ProcessImport;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Services\ImportEngine;
use App\Modules\Sales\Services\TemplateBuilder;
use App\Modules\Sales\Support\ImportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Data Import Centre (SRS IMP-001 §9.3–9.6, HIS-001 §10.5). */
class ImportController extends Controller
{
    private const PERMS = ['sales' => 'imports.historical', 'products' => 'imports.products', 'customers' => 'imports.customers'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $targets = collect(ImportFields::TARGETS)->filter(fn ($l, $k) => $user->can(self::PERMS[$k]));
        abort_if($targets->isEmpty(), 403);

        $batches = ImportBatch::query()->with('uploader:id,email', 'layout:id,name')
            ->whereIn('target', $targets->keys())
            ->when($request->query('target'), fn ($q, $t) => $q->where('target', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('Modules/Sales/Imports/Index', [
            'targets' => $targets->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
            'layouts' => ReportLayout::where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name', 'target', 'version', 'ingestion_mode']),
            'batches' => $batches,
            'filters' => $request->only('target', 'status'),
            'modes' => ['upsert' => 'Insert and update', 'insert' => 'Insert only', 'update' => 'Update existing only', 'reject' => 'Reject duplicates'],
        ]);
    }

    public function template(Request $request, string $target, TemplateBuilder $builder): \Illuminate\Http\Response
    {
        abort_unless(isset(self::PERMS[$target]) && $request->user()->can(self::PERMS[$target]), 403);
        $layout = $request->query('layout') ? ReportLayout::where('uuid', $request->query('layout'))->firstOrFail() : null;
        $name = str($layout?->name ?? $target)->slug() . '-template-v' . ($layout?->version ?? 1) . '.xlsx';

        return response($builder->build($target, $layout), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    public function store(Request $request, ImportEngine $engine): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(array_keys(ImportFields::TARGETS))],
            'layout_id' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in(['upsert', 'insert', 'update', 'reject'])],
            'source' => ['nullable', Rule::in(['upload', 'historical'])],
            'file' => ['required', 'file', 'max:40960', 'mimes:xlsx,xls,csv,txt'],
        ]);
        abort_unless($request->user()->can(self::PERMS[$data['target']]), 403);
        $layout = isset($data['layout_id']) ? ReportLayout::where('target', $data['target'])->findOrFail($data['layout_id']) : null;

        try {
            $batch = $engine->receiveUpload($data['target'], $request->file('file'), [
                'layout_id' => $layout?->id,
                'mode' => $data['mode'] ?? 'upsert',
                'source' => $data['source'] ?? 'upload',
            ]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        ProcessImport::run($batch, 'stage', $request->user()->id);

        return redirect("/sales/imports/{$batch->uuid}");
    }

    public function show(Request $request, ImportBatch $batch, ImportEngine $engine): Response
    {
        $this->authorizeBatch($request, $batch);
        $status = $request->query('rows', $batch->invalid_rows ? 'invalid' : 'all');

        $rows = $batch->rows()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $s) => $q->whereRaw('data::text ilike ?', ["%{$s}%"]))
            ->orderBy('row_number')->paginate(50)->withQueryString();

        $inspect = null;
        if (in_array($batch->status, ['staged', 'failed', 'uploaded'], true)) {
            try {
                $inspect = $engine->inspect($batch, $batch->layout);
            } catch (\Throwable $e) {
                $inspect = ['error' => $e->getMessage()];
            }
        }

        return Inertia::render('Modules/Sales/Imports/Show', [
            'batch' => $batch->load('layout:id,uuid,name,version,ingestion_mode,mapping', 'uploader:id,email'),
            'previous' => $batch->previous_batch_id ? ImportBatch::find($batch->previous_batch_id, ['uuid', 'file_name', 'completed_at']) : null,
            'rows' => $rows,
            'rowFilter' => $status,
            'search' => $request->query('q'),
            'fields' => ImportFields::for($batch->target),
            'inspect' => $inspect,
            'layouts' => ReportLayout::where('target', $batch->target)->where('is_active', true)->get(['id', 'name', 'version']),
            'modeLabels' => ReportLayout::MODES,
            'canCommit' => $request->user()->can($batch->permission()),
        ]);
    }

    /** Change layout or update mode and validate again (only before commit). */
    public function update(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_if(in_array($batch->status, ['committed', 'committing', 'cancelled'], true), 422);
        $data = $request->validate([
            'layout_id' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in(['upsert', 'insert', 'update', 'reject'])],
        ]);
        $batch->update([
            'layout_id' => isset($data['layout_id']) ? ReportLayout::where('target', $batch->target)->findOrFail($data['layout_id'])->id : null,
            'mode' => $data['mode'] ?? $batch->mode,
        ]);
        ProcessImport::run($batch, 'stage', $request->user()->id);

        return back()->with('success', 'Validated again.');
    }

    public function sourceTotals(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        $batch->update(['source_totals' => $request->validate([
            'net_value' => ['nullable', 'numeric'], 'quantity' => ['nullable', 'numeric'], 'rows' => ['nullable', 'integer'],
        ])]);

        return back()->with('success', 'Source totals saved.');
    }

    public function confirm(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        if ($batch->status !== 'staged') {
            return back()->withErrors(['batch' => 'Only a validated import can be confirmed.']);
        }
        ProcessImport::run($batch, 'commit', $request->user()->id);

        return back()->with('success', 'Import confirmed.');
    }

    public function cancel(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_if($batch->isCommitted(), 422);
        $batch->update(['status' => 'cancelled', 'completed_at' => now()]);

        return redirect('/sales/imports')->with('success', 'Import cancelled — nothing was saved.');
    }

    /** Authorised re-processing of a received file: a fresh batch, same file, same safety rules. */
    public function reprocess(Request $request, ImportBatch $batch, ImportEngine $engine): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        $copy = $engine->receive($batch->target, $batch->file_name, (string) Storage::disk('documents')->get($batch->file_path), [
            'layout_id' => $batch->layout_id, 'mode' => $batch->mode, 'source' => $batch->source, 'mailbox_id' => $batch->mailbox_id,
        ]);
        ProcessImport::run($copy, 'stage', $request->user()->id);

        return redirect("/sales/imports/{$copy->uuid}");
    }

    public function errors(Request $request, ImportBatch $batch): StreamedResponse
    {
        $this->authorizeBatch($request, $batch);

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Row', 'Status', 'Problem', 'Data']);
            $batch->rows()->whereIn('status', ['invalid', 'duplicate', 'warning'])->orderBy('row_number')
                ->chunk(1000, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->row_number, $r->status, implode(' ', $r->messages ?? []), json_encode($r->data)]);
                    }
                });
            fclose($out);
        }, "import-errors-{$batch->id}.csv", ['Content-Type' => 'text/csv']);
    }

    public function download(Request $request, ImportBatch $batch): \Illuminate\Http\Response
    {
        $this->authorizeBatch($request, $batch);

        return response((string) Storage::disk('documents')->get($batch->file_path), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes($batch->file_name) . '"',
        ]);
    }

    private function authorizeBatch(Request $request, ImportBatch $batch): void
    {
        abort_unless($request->user()->can($batch->permission()), 403);
    }
}
