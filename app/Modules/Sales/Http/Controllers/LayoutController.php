<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Services\SpreadsheetReader;
use App\Modules\Sales\Support\ImportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * File layouts — the setup that makes any report format loadable without code (SRS §6.2).
 * Upload a sample, map each column to a field or keep it as a custom field, save.
 */
class LayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSetup($request);

        return Inertia::render('Modules/Sales/Layouts/Index', [
            'layouts' => ReportLayout::orderBy('target')->orderBy('name')->get(),
            'targets' => ImportFields::TARGETS,
            'modes' => ReportLayout::MODES,
            'fields' => collect(ImportFields::TARGETS)->mapWithKeys(fn ($l, $k) => [$k => collect(ImportFields::for($k))
                ->map(fn ($f, $key) => ['key' => $key, 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'] ?? false])->values()]),
        ]);
    }

    /** Read a sample file's sheets, headings and first rows, with a suggested mapping. */
    public function inspect(Request $request, SpreadsheetReader $reader): JsonResponse
    {
        $this->authorizeSetup($request);
        $data = $request->validate([
            'target' => ['required', Rule::in(array_keys(ImportFields::TARGETS))],
            'file' => ['required', 'file', 'max:40960', 'mimes:xlsx,xls,csv,txt'],
            'sheet' => ['nullable', 'string', 'max:120'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        try {
            $info = $reader->inspect($file->getRealPath(), $ext, $data['sheet'] ?? null, (int) ($data['header_row'] ?? 1));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($info + ['mapping' => ImportFields::defaultMapping($data['target'], $info['headings'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSetup($request);
        ReportLayout::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Layout saved.');
    }

    public function update(Request $request, ReportLayout $layout): RedirectResponse
    {
        $this->authorizeSetup($request);
        $layout->update($this->validated($request, $layout->id));

        return back()->with('success', "Layout saved (version {$layout->version}).");
    }

    public function destroy(Request $request, ReportLayout $layout): RedirectResponse
    {
        $this->authorizeSetup($request);
        $layout->update(['is_active' => false]);

        return back()->with('success', 'Layout retired. Imports already made with it are unchanged.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('report_layouts', 'name')->ignore($ignoreId)
                ->where('company_id', \App\Modules\Sales\Support\SalesCompany::id())],
            'target' => ['required', Rule::in(array_keys(ImportFields::TARGETS))],
            'sheet' => ['nullable', 'string', 'max:120'],
            'header_row' => ['required', 'integer', 'min:1', 'max:100'],
            'date_format' => ['nullable', 'string', 'max:30'],
            'decimal_comma' => ['boolean'],
            'ingestion_mode' => ['required', Rule::in(array_keys(ReportLayout::MODES))],
            'unknown_masters' => ['required', Rule::in(['create', 'reject'])],
            'keep_unmapped' => ['boolean'],
            'is_active' => ['boolean'],
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*.source' => ['required', 'string', 'max:190'],
            'mapping.*.field' => ['nullable', 'string', 'max:80'],
            'dedupe_fields' => ['nullable', 'array'],
        ]);

        $fields = ImportFields::for($data['target']);
        $seen = [];
        foreach ($data['mapping'] as $i => $m) {
            $f = $m['field'] ?? null;
            if ($f && ! str_starts_with($f, 'attr:') && ! isset($fields[$f])) {
                throw ValidationException::withMessages(["mapping.{$i}.field" => "Unknown field {$f}."]);
            }
            if ($f && ! str_starts_with($f, 'attr:') && isset($seen[$f])) {
                throw ValidationException::withMessages(["mapping.{$i}.field" => "{$fields[$f]['label']} is mapped twice."]);
            }
            $seen[$f] = true;
        }
        $missing = collect($fields)->filter(fn ($f, $k) => ($f['required'] ?? false) && ! isset($seen[$k]))->pluck('label');
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['mapping' => 'Map a column to: ' . $missing->implode(', ')]);
        }

        return $data;
    }

    private function authorizeSetup(Request $request): void
    {
        abort_unless($request->user()->can('imports.historical') || $request->user()->can('settings.manage'), 403);
    }
}
