<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Support\MasterData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Products, customers and their classification lists (SRS MDM-001/002). */
class MasterDataController extends Controller
{
    public function index(Request $request, string $resource): Response
    {
        $def = MasterData::get($resource);
        $this->authorizePermission($def['view']);

        $query = $def['model']::query()->with($def['with'] ?? []);
        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($def, $q) {
                foreach ($def['search'] as $col) {
                    $w->orWhere($col, 'ilike', "%{$q}%");
                }
            });
        }
        foreach ($def['filters'] ?? ['is_active'] as $f) {
            $v = $request->query($f);
            if ($v !== null && $v !== '') {
                $query->where($f, $f === 'is_active' ? filter_var($v, FILTER_VALIDATE_BOOLEAN) : $v);
            }
        }
        if ($request->boolean('needs_review')) {
            $query->where('needs_review', true);
        }

        $sort = $def['search'][1] ?? 'name';
        $rows = $query->orderBy($sort)->paginate((int) $request->query('per_page', 25))->withQueryString();

        if (! empty($def['aliases'])) {
            $rows->getCollection()->load('aliases:id,' . rtrim($resource, 's') . '_id,alias_code,alias_name');
        }

        $options = [];
        foreach ($def['fields'] as $field) {
            if (($field['type'] ?? '') === 'select') {
                $options[$field['options']] = MasterData::options($field['options']);
            }
        }

        return Inertia::render('Modules/Sales/MasterData/Index', [
            'resource' => $resource,
            'definition' => collect($def)->except('model')->all(),
            'resources' => collect(MasterData::all())
                ->filter(fn ($d) => $request->user()->can($d['view']))
                ->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label']])->values(),
            'rows' => $rows,
            'options' => $options,
            'filters' => $request->only(array_merge(['q', 'needs_review'], $def['filters'] ?? ['is_active'])),
            'canManage' => $request->user()->can($def['manage']),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $def = MasterData::get($resource);
        $this->authorizePermission($def['manage']);
        $def['model']::create($this->validated($request, $def));

        return back()->with('success', 'Saved.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $def = MasterData::get($resource);
        $this->authorizePermission($def['manage']);
        $record = $def['model']::findOrFail($id);
        $data = $this->validated($request, $def, $record->id);
        if (! empty($def['aliases'])) {
            $data['needs_review'] = false; // someone has looked at it now
        }
        $record->update($data);

        return back()->with('success', 'Updated.');
    }

    /** Deactivate rather than delete: sales history keeps pointing at the record (§7.2). */
    public function destroy(string $resource, int $id): RedirectResponse
    {
        $def = MasterData::get($resource);
        $this->authorizePermission($def['manage']);
        $def['model']::findOrFail($id)->update(['is_active' => false]);

        return back()->with('success', 'Deactivated.');
    }

    public function addAlias(Request $request, string $resource, int $id): RedirectResponse
    {
        $def = MasterData::get($resource);
        abort_unless(! empty($def['aliases']), 404);
        $this->authorizePermission($def['manage']);
        $record = $def['model']::findOrFail($id);

        $data = $request->validate([
            'alias_code' => ['nullable', 'string', 'max:80', 'required_without:alias_name'],
            'alias_name' => ['nullable', 'string', 'max:255'],
        ]);
        $record->aliases()->create($data);

        return back()->with('success', 'Alias added.');
    }

    public function removeAlias(string $resource, int $id, int $aliasId): RedirectResponse
    {
        $def = MasterData::get($resource);
        $this->authorizePermission($def['manage']);
        $def['model']::findOrFail($id)->aliases()->whereKey($aliasId)->delete();

        return back()->with('success', 'Alias removed.');
    }

    public function export(Request $request, string $resource): StreamedResponse
    {
        $def = MasterData::get($resource);
        $this->authorizePermission($def['view']);
        $this->authorizePermission('reports.export');

        $cols = collect($def['fields'])->pluck('key')->all();
        activity('sales')->causedBy($request->user())->withProperties(['resource' => $resource])->log('export');

        return response()->streamDownload(function () use ($def, $cols) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $cols);
            $def['model']::query()->orderBy('id')->chunk(1000, function ($chunk) use ($out, $cols) {
                foreach ($chunk as $row) {
                    fputcsv($out, array_map(fn ($c) => is_bool($row->{$c}) ? (int) $row->{$c} : $row->{$c}, $cols));
                }
            });
            fclose($out);
        }, "{$resource}-" . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function validated(Request $request, array $def, ?int $ignoreId = null): array
    {
        $table = (new $def['model'])->getTable();
        $companyId = \App\Modules\Sales\Support\SalesCompany::id();
        $rules = [];
        foreach ($def['fields'] as $f) {
            $r = [($f['required'] ?? false) ? 'required' : 'nullable'];
            $r[] = match ($f['type']) {
                'number' => 'numeric',
                'switch' => 'boolean',
                'select' => 'integer',
                default => 'string',
            };
            if (in_array($f['key'], ['code', 'product_code', 'customer_code'], true)) {
                $r[] = 'max:80';
                $r[] = Rule::unique($table, $f['key'])->ignore($ignoreId)
                    ->where(fn ($q) => $companyId ? $q->where('company_id', $companyId) : $q);
            }
            $rules[$f['key']] = $r;
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(request()->user()?->can($permission), 403);
    }
}
