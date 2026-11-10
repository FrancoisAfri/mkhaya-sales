<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Models\ImportRow;
use App\Modules\Sales\Models\Product;
use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Support\ImportFields;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * The one import pipeline behind manual uploads, historical loads and the nightly mailbox
 * (SRS IMP-001, DATA-001, §6.4, §9.6):
 *
 *   receive()  stores the file and opens a batch — nothing touches business data
 *   stage()    reads, types and validates every row into import_rows (preview)
 *   commit()   writes the valid rows in one transaction — all of it, or none of it
 *
 * Idempotent: every sales row has a key and the database holds one live row per key, so a report
 * received twice, or re-processed, cannot double-count (§5.4, §28.4 scenario 3).
 */
class ImportEngine
{
    public const MAX_BYTES = 40 * 1024 * 1024;

    public function __construct(private SpreadsheetReader $reader) {}

    // ── 1. Receive ──────────────────────────────────────────────────────────────────────────────

    public function receive(string $target, string $fileName, string $bytes, array $attrs = []): ImportBatch
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (! in_array($ext, SpreadsheetReader::EXTENSIONS, true)) {
            throw new RuntimeException('Only XLSX, XLS and CSV files can be imported.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('The file is larger than 40 MB.');
        }
        $this->assertLooksLikeSpreadsheet($ext, $bytes);

        $hash = hash('sha256', $bytes);
        $path = 'sales-imports/' . Str::uuid() . '.' . $ext;
        Storage::disk('documents')->put($path, $bytes);

        $previous = ImportBatch::where('file_hash', $hash)->where('status', 'committed')->latest('id')->first();

        return ImportBatch::create(array_merge([
            'target' => $target,
            'file_name' => mb_substr(basename($fileName), 0, 255),
            'file_path' => $path,
            'file_hash' => $hash,
            'status' => 'uploaded',
            'previous_batch_id' => $previous?->id,
            'uploaded_by' => auth()->id(),
        ], $attrs));
    }

    public function receiveUpload(string $target, UploadedFile $file, array $attrs = []): ImportBatch
    {
        return $this->receive($target, $file->getClientOriginalName(), (string) file_get_contents($file->getRealPath()), $attrs);
    }

    /** Headings and a few rows, for building a layout or checking a mapping. */
    public function inspect(ImportBatch $batch, ?ReportLayout $layout = null): array
    {
        return $this->withLocalFile($batch, fn ($p, $ext) => $this->reader->inspect($p, $ext, $layout?->sheet, $layout?->header_row ?? 1));
    }

    // ── 2. Stage ────────────────────────────────────────────────────────────────────────────────

    public function stage(ImportBatch $batch): ImportBatch
    {
        $batch->update(['status' => 'staging', 'started_at' => now(), 'error' => null]);
        ImportRow::where('batch_id', $batch->id)->delete();
        $layout = $batch->layout;
        if ($layout) {
            $batch->layout_version = $layout->version;
        }

        try {
            $stats = $this->withLocalFile($batch, fn ($path, $ext) => $this->stageRows($batch, $layout, $path, $ext));
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'error' => $e->getMessage(), 'completed_at' => now()]);

            return $batch;
        }

        $batch->fill($stats + ['status' => 'staged'])->save();

        return $batch;
    }

    private function stageRows(ImportBatch $batch, ?ReportLayout $layout, string $path, string $ext): array
    {
        $fields = ImportFields::for($batch->target);
        $matcher = new MasterMatcher;
        $headings = [];
        $buffer = [];
        $seen = [];
        $c = ['total_rows' => 0, 'valid_rows' => 0, 'invalid_rows' => 0, 'duplicate_rows' => 0, 'warning_rows' => 0];
        $tot = ['net_value' => 0.0, 'gross_value' => 0.0, 'quantity' => 0.0, 'customers' => [], 'products' => []];
        $minDate = $maxDate = null;
        $mapping = null;

        foreach ($this->reader->rows($path, $ext, $layout?->sheet, $layout?->header_row ?? 1, $sheets, $headings) as $r) {
            $mapping ??= $this->mapping($batch->target, $layout, $headings);
            $c['total_rows']++;
            [$data, $errors, $warnings] = $this->normalise($batch->target, $fields, $mapping, $r['values'], $layout);

            $status = $errors ? 'invalid' : ($warnings ? 'warning' : 'valid');
            $key = null;

            if (! $errors && $batch->target === 'sales') {
                $data['customer_id'] = $matcher->customer($data['customer_code'] ?? null, $data['customer_name'] ?? null);
                $data['product_id'] = $matcher->product($data['product_code'] ?? null, $data['product_name'] ?? null);
                foreach (['customer' => 'Customer', 'product' => 'Product'] as $k => $label) {
                    if (! $data["{$k}_id"]) {
                        $ident = $data["{$k}_code"] ?? $data["{$k}_name"];
                        if (($layout?->unknown_masters ?? 'create') === 'reject') {
                            $errors[] = "Unknown {$k} \"{$ident}\".";
                        } else {
                            $warnings[] = "New {$k} \"{$ident}\" will be created and flagged for review.";
                        }
                    }
                }
                $key = $this->rowKey($data, $layout);
                $status = $errors ? 'invalid' : ($warnings ? 'warning' : 'valid');
            }

            if (! $errors && $batch->target === 'targets') {
                [$data, $errors] = $this->resolveTarget($data, $matcher);
                $status = $errors ? 'invalid' : 'valid';
                $key = $errors ? null : md5(mb_strtolower($data['name']) . '|' . $data['start_date'] . '|' . $data['end_date']);
                if ($key && \App\Modules\Sales\Models\Target::where('name', $data['name'])->where('start_date', $data['start_date'])->where('end_date', $data['end_date'])->where('status', '!=', 'archived')->exists()) {
                    $status = 'duplicate';
                    $errors[] = 'A target with this name and period already exists.';
                }
            }

            if (! $errors && in_array($batch->target, ImportFields::EXTRA, true)) {
                [$data, $errors, $key] = $this->resolveExtra($batch->target, $data, $matcher);
                $status = $errors ? 'invalid' : 'valid';
            }

            if (! $errors && ! in_array($batch->target, array_merge(['sales', 'targets'], ImportFields::EXTRA), true)) {
                [$status, $msg] = $this->masterStatus($batch, $data);
                if ($msg) {
                    $status === 'invalid' || $status === 'duplicate' ? $errors[] = $msg : $warnings[] = $msg;
                }
                $key = $data[$batch->target === 'products' ? 'product_code' : 'customer_code'] ?? null;
            }

            if ($key !== null && in_array($status, ['valid', 'warning'], true)) {
                if (isset($seen[$key])) {
                    $status = 'duplicate';
                    $errors[] = "Same as row {$seen[$key]} in this file.";
                } else {
                    $seen[$key] = $r['row'];
                }
            }

            if (in_array($status, ['valid', 'warning'], true) && $batch->target === 'sales') {
                $tot['net_value'] += (float) ($data['net_value'] ?? 0);
                $tot['gross_value'] += (float) ($data['gross_value'] ?? 0);
                $tot['quantity'] += (float) ($data['quantity'] ?? 0);
                $tot['customers'][mb_strtolower((string) ($data['customer_code'] ?? $data['customer_name']))] = true;
                $tot['products'][mb_strtolower((string) ($data['product_code'] ?? $data['product_name']))] = true;
                $d = $data['transaction_date'];
                $minDate = $minDate === null || $d < $minDate ? $d : $minDate;
                $maxDate = $maxDate === null || $d > $maxDate ? $d : $maxDate;
            }

            $c[$status === 'valid' ? 'valid_rows' : "{$status}_rows"]++;
            $buffer[] = [
                'company_id' => $batch->company_id, 'batch_id' => $batch->id, 'row_number' => $r['row'],
                'status' => $status, 'row_key' => $key,
                'data' => json_encode($data), 'messages' => json_encode(array_values(array_merge($errors, $warnings))),
            ];
            if (count($buffer) >= 500) {
                ImportRow::insert($buffer);
                $buffer = [];
            }
        }
        if ($buffer) {
            ImportRow::insert($buffer);
        }
        if ($c['total_rows'] === 0) {
            throw new RuntimeException('The file has headings but no data rows.');
        }

        // Rows already live from an earlier import are duplicates, not new sales (append mode).
        if ($batch->target === 'sales' && ($layout?->ingestion_mode ?? 'append') === 'append') {
            $c = $this->markAlreadyImported($batch, $c, $tot);
        }

        return $c + [
            'headers' => $headings,
            'period_from' => $minDate, 'period_to' => $maxDate,
            'totals' => $batch->target === 'sales' ? [
                'net_value' => round($tot['net_value'], 2), 'gross_value' => round($tot['gross_value'], 2),
                'quantity' => round($tot['quantity'], 4),
                'customers' => count($tot['customers']), 'products' => count($tot['products']),
            ] : null,
        ];
    }

    private function markAlreadyImported(ImportBatch $batch, array $c, array &$tot): array
    {
        $dupes = DB::table('import_rows as r')
            ->join('sales_records as s', fn ($j) => $j->on('s.row_key', '=', 'r.row_key')->where('s.status', 'active')->whereColumn('s.company_id', 'r.company_id'))
            ->where('r.batch_id', $batch->id)->whereIn('r.status', ['valid', 'warning'])
            ->select('r.id', 'r.status', 'r.data')->get();

        foreach ($dupes as $d) {
            $data = json_decode($d->data, true);
            $tot['net_value'] -= (float) ($data['net_value'] ?? 0);
            $tot['gross_value'] -= (float) ($data['gross_value'] ?? 0);
            $tot['quantity'] -= (float) ($data['quantity'] ?? 0);
            $c[$d->status === 'valid' ? 'valid_rows' : 'warning_rows']--;
            $c['duplicate_rows']++;
            DB::table('import_rows')->where('id', $d->id)->update([
                'status' => 'duplicate', 'messages' => json_encode(['Already imported earlier — will not be counted again.']),
            ]);
        }

        return $c;
    }

    /** @return array{0: string, 1: ?string} */
    private function masterStatus(ImportBatch $batch, array $data): array
    {
        $exists = $batch->target === 'products'
            ? Product::where('product_code', $data['product_code'])->exists()
            : Customer::where('customer_code', $data['customer_code'])->exists();

        return match ($batch->mode) {
            'insert' => $exists ? ['duplicate', 'Already exists — insert-only mode skips it.'] : ['valid', null],
            'update' => $exists ? ['warning', 'Existing record will be updated.'] : ['invalid', 'Not found — update-only mode cannot add it.'],
            'reject' => $exists ? ['invalid', 'Already exists — duplicates are rejected.'] : ['valid', null],
            default => $exists ? ['warning', 'Existing record will be updated.'] : ['valid', null],
        };
    }

    /** heading → field, from the layout or guessed for a first-time file. */
    private function mapping(string $target, ?ReportLayout $layout, array $headings): array
    {
        $map = [];
        $layoutMap = $layout ? collect($layout->mapping)->keyBy(fn ($m) => mb_strtolower(trim($m['source']))) : collect();
        $guessed = collect(ImportFields::defaultMapping($target, $headings))->keyBy(fn ($m) => mb_strtolower(trim($m['source'])));
        foreach ($headings as $h) {
            $k = mb_strtolower(trim($h));
            if ($layout) {
                $field = $layoutMap[$k]['field'] ?? (($layout->keep_unmapped ?? true) ? 'attr:' . ImportFields::slug($h) : null);
            } else {
                $field = $guessed[$k]['field'] ?? null;
            }
            if ($field) {
                $map[$h] = $field;
            }
        }

        return $map;
    }

    /** @return array{0: array, 1: string[], 2: string[]} data, errors, warnings */
    private function normalise(string $target, array $fields, array $mapping, array $values, ?ReportLayout $layout): array
    {
        $data = [];
        $attributes = [];
        $errors = [];
        $warnings = [];

        foreach ($mapping as $heading => $field) {
            $raw = $values[$heading] ?? null;
            if (str_starts_with($field, 'attr:')) {
                if ($raw !== null && $raw !== '') {
                    $attributes[substr($field, 5)] = is_scalar($raw) ? $raw : (string) $raw;
                }
                continue;
            }
            $def = $fields[$field] ?? null;
            if (! $def || $raw === null || $raw === '') {
                continue;
            }
            if ($def['type'] === 'number') {
                $n = $this->number($raw, (bool) $layout?->decimal_comma);
                $n === null ? $errors[] = "{$def['label']}: \"{$raw}\" is not a number." : $data[$field] = $n;
            } elseif ($def['type'] === 'date') {
                $d = $this->date($raw, $layout?->date_format);
                $d === null ? $errors[] = "{$def['label']}: \"{$raw}\" is not a valid date." : $data[$field] = $d;
            } else {
                $data[$field] = mb_substr(trim((string) $raw), 0, 255);
            }
        }
        if ($attributes) {
            $data['attributes'] = $attributes;
        }

        foreach ($fields as $key => $def) {
            if (($def['required'] ?? false) && ! isset($data[$key]) && ! preg_grep('/^' . preg_quote($def['label'], '/') . ':/', $errors)) {
                $errors[] = "{$def['label']} is required.";
            }
        }

        if ($target === 'sales') {
            if (empty($data['customer_code']) && empty($data['customer_name'])) {
                $errors[] = 'Customer code or name is required.';
            }
            if (empty($data['product_code']) && empty($data['product_name'])) {
                $errors[] = 'Product code or description is required.';
            }
            if (! isset($data['net_value']) && ! isset($data['gross_value']) && ! isset($data['quantity'])) {
                $errors[] = 'No sales value or quantity on this row.';
            }
            $discount = (float) ($data['discount'] ?? 0);
            if (! isset($data['net_value']) && isset($data['gross_value'])) {
                $data['net_value'] = round($data['gross_value'] - $discount, 2);
            }
            if (! isset($data['gross_value']) && isset($data['net_value'])) {
                $data['gross_value'] = round($data['net_value'] + $discount, 2);
            }
            if (! isset($data['net_value']) && isset($data['quantity'], $data['unit_price'])) {
                $data['net_value'] = $data['gross_value'] = round($data['quantity'] * $data['unit_price'], 2);
            }
            if (isset($data['currency']) && ! preg_match('/^[A-Za-z]{3}$/', $data['currency'])) {
                $warnings[] = "Currency \"{$data['currency']}\" is not a 3-letter code; ZAR will be used.";
                unset($data['currency']);
            }
            if (isset($data['transaction_date']) && $data['transaction_date'] > now()->addDay()->toDateString()) {
                $warnings[] = 'Sales date is in the future.';
            }
        }

        return [$data, $errors, $warnings];
    }

    private function rowKey(array $d, ?ReportLayout $layout): string
    {
        $mode = $layout?->ingestion_mode ?? 'append';
        $fields = $layout?->dedupe_fields ?: match ($mode) {
            'upsert_daily' => ['transaction_date', 'customer', 'product', 'region', 'salesperson', 'distribution_centre'],
            default => ['transaction_date', 'customer', 'product', 'external_id', 'quantity', 'net_value', 'gross_value', 'region', 'salesperson', 'distribution_centre'],
        };
        $parts = [$layout?->id ?? 0];
        foreach ($fields as $f) {
            $parts[] = match ($f) {
                'customer' => mb_strtolower((string) ($d['customer_code'] ?? $d['customer_name'] ?? '')),
                'product' => mb_strtolower((string) ($d['product_code'] ?? $d['product_name'] ?? '')),
                default => is_float($d[$f] ?? null) ? number_format($d[$f], 4, '.', '') : mb_strtolower((string) ($d[$f] ?? '')),
            };
        }

        return hash('sha256', implode('|', $parts));
    }

    // ── 3. Commit ───────────────────────────────────────────────────────────────────────────────

    public function commit(ImportBatch $batch, ?User $by = null): ImportBatch
    {
        if ($batch->status !== 'staged') {
            throw new RuntimeException("This import is {$batch->status}; only a validated import can be committed.");
        }
        $batch->update(['status' => 'committing', 'confirmed_by' => $by?->id, 'confirmed_at' => now()]);

        try {
            $result = DB::transaction(fn () => match (true) {
                $batch->target === 'sales' => $this->commitSales($batch),
                $batch->target === 'targets' => $this->commitTargets($batch, $by),
                in_array($batch->target, ImportFields::EXTRA, true) => $this->commitExtra($batch),
                default => $this->commitMasters($batch),
            });
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'error' => 'Nothing was saved: ' . $e->getMessage(), 'completed_at' => now()]);
            report($e);

            return $batch;
        }

        $batch->update($result + ['status' => 'committed', 'completed_at' => now()]);
        \App\Modules\Sales\Services\BI\BIQueryService::invalidate($batch->company_id);
        if ($batch->target === 'sales') {
            \App\Modules\Sales\Services\BI\SalesSummary::rebuild($batch->company_id);   // already in a background job for large files
        }
        activity('sales')->performedOn($batch)->causedBy($by)->withProperties($result + ['file' => $batch->file_name])->log('import committed');

        return $batch;
    }

    private function commitSales(ImportBatch $batch): array
    {
        $layout = $batch->layout;
        $mode = $layout?->ingestion_mode ?? 'append';
        $create = ($layout?->unknown_masters ?? 'create') === 'create';
        $matcher = new MasterMatcher;
        $inserted = $updated = $replaced = 0;

        if ($mode === 'replace_period' && $batch->period_from) {
            $replaced = DB::table('sales_records')
                ->where('company_id', $batch->company_id)
                ->where('layout_id', $batch->layout_id)
                ->where('status', 'active')
                ->whereBetween('transaction_date', [$batch->period_from->toDateString(), $batch->period_to->toDateString()])
                ->update(['status' => 'replaced', 'updated_at' => now()]);
        }

        ImportRow::where('batch_id', $batch->id)->whereIn('status', ['valid', 'warning'])->orderBy('row_number')
            ->chunk(500, function ($rows) use ($batch, $layout, $mode, $create, $matcher, &$inserted, &$updated) {
                $insert = [];
                foreach ($rows as $row) {
                    $d = $row->data;
                    $customerId = $matcher->customer($d['customer_code'] ?? null, $d['customer_name'] ?? null)
                        ?? ($create ? $this->createMaster('customer', $d, $matcher) : null);
                    $productId = $matcher->product($d['product_code'] ?? null, $d['product_name'] ?? null)
                        ?? ($create ? $this->createMaster('product', $d, $matcher) : null);

                    $values = [
                        'company_id' => $batch->company_id,
                        'import_batch_id' => $batch->id,
                        'layout_id' => $batch->layout_id,
                        'transaction_date' => $d['transaction_date'],
                        'customer_id' => $customerId,
                        'product_id' => $productId,
                        'quantity' => $d['quantity'] ?? 0,
                        'unit_of_measure' => $d['unit_of_measure'] ?? null,
                        'unit_price' => $d['unit_price'] ?? null,
                        'gross_value' => $d['gross_value'] ?? 0,
                        'discount' => $d['discount'] ?? 0,
                        'net_value' => $d['net_value'] ?? 0,
                        'currency' => strtoupper($d['currency'] ?? 'ZAR'),
                        'region_id' => $matcher->lookup('regions', $d['region'] ?? null, true),
                        'business_unit_id' => $matcher->lookup('business_units', $d['business_unit'] ?? null, true),
                        'sales_team_id' => $matcher->lookup('sales_teams', $d['sales_team'] ?? null, true),
                        'salesperson_id' => $matcher->lookup('salespeople', $d['salesperson'] ?? null, true),
                        'distribution_centre_id' => $matcher->lookup('distribution_centres', $d['distribution_centre'] ?? null, true),
                        'external_id' => $d['external_id'] ?? null,
                        'row_key' => $row->row_key,
                        'source_row' => $row->row_number,
                        'attributes' => isset($d['attributes']) ? json_encode($d['attributes']) : null,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($mode === 'upsert_daily') {
                        $n = DB::table('sales_records')->where('company_id', $batch->company_id)
                            ->where('row_key', $row->row_key)->where('status', 'active')
                            ->update(collect($values)->except(['company_id', 'row_key', 'created_at', 'status'])->all());
                        if ($n) {
                            $updated += $n;
                            continue;
                        }
                    }
                    $insert[] = $values;
                }
                // The partial unique index refuses a live duplicate; insertOrIgnore turns that into "skip".
                $inserted += $insert ? DB::table('sales_records')->insertOrIgnore($insert) : 0;
            });

        return ['inserted_rows' => $inserted, 'updated_rows' => $updated, 'replaced_rows' => $replaced];
    }

    private function createMaster(string $kind, array $d, MasterMatcher $matcher): ?int
    {
        $code = $d["{$kind}_code"] ?? null;
        $name = $d["{$kind}_name"] ?? $code;
        $model = $kind === 'product' ? new Product : new Customer;
        $model->fill([
            "{$kind}_code" => $code ?: Str::upper(Str::limit(Str::slug($name, ''), 40, '')) . '-' . Str::upper(Str::random(4)),
            "{$kind}_name" => $name,
            'needs_review' => true,
            'is_active' => true,
        ])->save();
        $matcher->remember($kind, $code, $name, $model->id);

        return $model->id;
    }

    /** Targets arrive as drafts and go through the approvals matrix like any other (§9.1, §11.6). */
    private function commitTargets(ImportBatch $batch, ?User $by): array
    {
        $creator = $by?->id ?? $batch->uploaded_by;
        $inserted = 0;
        ImportRow::where('batch_id', $batch->id)->where('status', 'valid')->orderBy('row_number')->each(function ($row) use ($creator, &$inserted) {
            \App\Modules\Sales\Models\Target::create(collect($row->data)->only(['name', 'metric', 'method', 'period_type', 'start_date', 'end_date', 'target_value', 'target_value_max', 'dimensions', 'notes'])->all()
                + ['status' => 'draft', 'created_by' => $creator]);
            $inserted++;
        });

        return ['inserted_rows' => $inserted, 'updated_rows' => 0];
    }

    /**
     * Stock, promotions and price lists point at products that already exist (nothing is invented).
     *
     * @return array{0: array, 1: string[], 2: ?string} data with ids, errors, duplicate key
     */
    private function resolveExtra(string $target, array $d, MasterMatcher $matcher): array
    {
        $errors = [];
        $d['product_id'] = ! empty($d['product_code']) ? $matcher->product($d['product_code'], null) : null;
        if (! empty($d['product_code']) && ! $d['product_id']) {
            $errors[] = "Unknown product \"{$d['product_code']}\" — add it in Products first.";
        }
        $d['distribution_centre_id'] = $matcher->lookup('distribution_centres', $d['distribution_centre'] ?? null);
        $d['customer_group_id'] = $matcher->lookup('customer_groups', $d['customer_group'] ?? null);
        $d['category_id'] = $matcher->lookup('product_categories', $d['category'] ?? null);
        $d['customer_id'] = ! empty($d['customer_code']) ? $matcher->customer($d['customer_code'], null) : null;
        foreach (['customer_group' => 'customer group', 'category' => 'category', 'distribution_centre' => 'distribution centre'] as $k => $label) {
            if (! empty($d[$k]) && ! $d["{$k}_id"]) {
                $errors[] = "Unknown {$label} \"{$d[$k]}\".";
            }
        }
        if ($target === 'promotions' && ! empty($d['start_date']) && ! empty($d['end_date']) && $d['end_date'] < $d['start_date']) {
            $errors[] = 'The promotion ends before it starts.';
        }
        if ($target === 'promotions' && empty($d['product_id']) && empty($d['category_id']) && empty($d['customer_id']) && empty($d['customer_group_id'])) {
            $errors[] = 'Say what the promotion covers: a product, category, customer or customer group.';
        }
        $key = $errors ? null : match ($target) {
            'stock' => "{$d['snapshot_date']}|{$d['product_id']}|{$d['distribution_centre_id']}",
            'prices' => "{$d['product_id']}|{$d['customer_group_id']}|{$d['valid_from']}",
            default => mb_strtolower($d['name']) . "|{$d['start_date']}|{$d['product_id']}|{$d['category_id']}|{$d['customer_id']}|{$d['customer_group_id']}",
        };

        return [$d, $errors, $key];
    }

    /** Stock replaces the same date / product / DC; promotions and prices replace the same key. */
    private function commitExtra(ImportBatch $batch): array
    {
        $inserted = $updated = 0;
        $c = $batch->company_id;
        ImportRow::where('batch_id', $batch->id)->whereIn('status', ['valid', 'warning'])->orderBy('row_number')->each(function ($row) use ($batch, $c, &$inserted, &$updated) {
            $d = $row->data;
            [$table, $match, $values] = match ($batch->target) {
                'stock' => ['stock_levels', ['snapshot_date' => $d['snapshot_date'], 'product_id' => $d['product_id'], 'distribution_centre_id' => $d['distribution_centre_id']],
                    ['cases_on_hand' => $d['cases_on_hand']]],
                'prices' => ['price_lists', ['product_id' => $d['product_id'], 'customer_group_id' => $d['customer_group_id'], 'valid_from' => $d['valid_from']],
                    ['price_per_case' => $d['price_per_case'], 'valid_to' => $d['valid_to'] ?? null]],
                default => ['promotions', ['name' => $d['name'], 'start_date' => $d['start_date'], 'product_id' => $d['product_id'], 'category_id' => $d['category_id'],
                    'customer_id' => $d['customer_id'], 'customer_group_id' => $d['customer_group_id']],
                    ['end_date' => $d['end_date'], 'discount_pct' => $d['discount_pct'] ?? null, 'mechanic' => $d['mechanic'] ?? null]],
            };
            $q = DB::table($table)->where('company_id', $c);
            foreach ($match as $k => $v) {
                $v === null ? $q->whereNull($k) : $q->where($k, $v);
            }
            if ($id = $q->value('id')) {
                DB::table($table)->where('id', $id)->update($values + ['import_batch_id' => $batch->id, 'updated_at' => now()]);
                $updated++;
            } else {
                DB::table($table)->insert(['company_id' => $c] + $match + $values + ['import_batch_id' => $batch->id, 'created_at' => now(), 'updated_at' => now()]);
                $inserted++;
            }
        });

        return ['inserted_rows' => $inserted, 'updated_rows' => $updated];
    }

    /** @return array{0: array, 1: string[]} */
    private function resolveTarget(array $d, MasterMatcher $matcher): array
    {
        $errors = [];
        $d['metric'] = strtolower(str_replace([' ', '-'], '_', $d['metric'] ?? 'net_value'));
        $d['method'] = strtolower($d['method'] ?? 'fixed');
        $d['period_type'] = strtolower(str_replace([' ', '-'], '_', $d['period_type'] ?? 'custom'));
        $T = \App\Modules\Sales\Models\Target::class;
        if (! isset($T::METRICS[$d['metric']])) {
            $errors[] = "Unknown measure \"{$d['metric']}\".";
        }
        if (! isset($T::METHODS[$d['method']])) {
            $errors[] = "Unknown rule \"{$d['method']}\".";
        }
        if (! in_array($d['period_type'], $T::PERIODS, true)) {
            $errors[] = "Unknown period \"{$d['period_type']}\".";
        }
        if (($d['end_date'] ?? '') < ($d['start_date'] ?? '')) {
            $errors[] = 'End date is before start date.';
        }
        if ($d['method'] === 'range' && (($d['target_value_max'] ?? null) === null || $d['target_value_max'] < $d['target_value'])) {
            $errors[] = 'A range needs an upper value above the target value.';
        }

        $dims = [];
        $lookups = ['customer_group' => 'customer_groups', 'category' => 'product_categories', 'brand' => 'product_brands', 'region' => 'regions',
            'business_unit' => 'business_units', 'sales_team' => 'sales_teams', 'salesperson' => 'salespeople', 'distribution_centre' => 'distribution_centres'];
        foreach (['customer', 'product'] as $k) {
            if (! empty($d[$k])) {
                $id = $k === 'customer' ? $matcher->customer($d[$k], $d[$k]) : $matcher->product($d[$k], $d[$k]);
                $id ? $dims["{$k}_id"] = $id : $errors[] = ucfirst($k) . " \"{$d[$k]}\" not found.";
            }
        }
        foreach ($lookups as $k => $table) {
            if (! empty($d[$k])) {
                $id = $matcher->lookup($table, $d[$k]);
                $id ? $dims["{$k}_id"] = $id : $errors[] = str_replace('_', ' ', ucfirst($k)) . " \"{$d[$k]}\" not found.";
            }
        }
        $d['dimensions'] = $dims ?: null;

        return [$d, $errors];
    }

    private function commitMasters(ImportBatch $batch): array
    {
        $kind = $batch->target === 'products' ? 'product' : 'customer';
        $matcher = new MasterMatcher;
        $inserted = $updated = 0;

        ImportRow::where('batch_id', $batch->id)->whereIn('status', ['valid', 'warning'])->orderBy('row_number')
            ->chunk(500, function ($rows) use ($kind, $matcher, &$inserted, &$updated) {
                foreach ($rows as $row) {
                    $d = $row->data;
                    $values = $kind === 'product' ? [
                        'product_name' => $d['product_name'],
                        'alternative_code' => $d['alternative_code'] ?? null,
                        'category_id' => $this->category($d, $matcher),
                        'brand_id' => $matcher->lookup('product_brands', $d['brand'] ?? null, true),
                        'unit_id' => $matcher->lookup('product_units', $d['unit'] ?? null, true),
                        'pack_size' => $d['pack_size'] ?? null,
                        'currency' => strtoupper($d['currency'] ?? 'ZAR'),
                    ] : [
                        'customer_name' => $d['customer_name'],
                        'alternative_name' => $d['alternative_name'] ?? null,
                        'customer_group_id' => $matcher->lookup('customer_groups', $d['customer_group'] ?? null, true),
                        'region_id' => $matcher->lookup('regions', $d['region'] ?? null, true),
                        'business_unit_id' => $matcher->lookup('business_units', $d['business_unit'] ?? null, true),
                        'sales_team_id' => $matcher->lookup('sales_teams', $d['sales_team'] ?? null, true),
                        'salesperson_id' => $matcher->lookup('salespeople', $d['salesperson'] ?? null, true),
                        'distribution_centre_id' => $matcher->lookup('distribution_centres', $d['distribution_centre'] ?? null, true),
                    ];
                    if ($kind !== 'product') {
                        foreach (['province', 'city'] as $k) {
                            if (! empty($d[$k])) {
                                $values[$k] = mb_substr(trim((string) $d[$k]), 0, $k === 'province' ? 60 : 120);
                            }
                        }
                        $lat = isset($d['latitude']) && is_numeric($d['latitude']) ? (float) $d['latitude'] : null;
                        $lng = isset($d['longitude']) && is_numeric($d['longitude']) ? (float) $d['longitude'] : null;
                        if ($lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180) {
                            [$values['latitude'], $values['longitude']] = [$lat, $lng];
                        }
                    }
                    if (isset($d['status'])) {
                        $values['is_active'] = ! in_array(mb_strtolower($d['status']), ['inactive', 'no', '0', 'false', 'n'], true);
                    }
                    if (isset($d['attributes'])) {
                        $values['attributes'] = $d['attributes'];
                    }
                    $values['needs_review'] = false;

                    $model = $kind === 'product' ? Product::firstOrNew(['product_code' => $d['product_code']])
                        : Customer::firstOrNew(['customer_code' => $d['customer_code']]);
                    $model->exists ? $updated++ : $inserted++;
                    $model->fill($values)->save();
                }
            });

        return ['inserted_rows' => $inserted, 'updated_rows' => $updated];
    }

    private function category(array $d, MasterMatcher $matcher): ?int
    {
        $parent = $matcher->lookup('product_categories', $d['category'] ?? null, true);
        if (! empty($d['subcategory'])) {
            return $matcher->lookup('product_categories', $d['subcategory'], true, ['parent_id' => $parent]);
        }

        return $parent;
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    private function number(mixed $raw, bool $decimalComma): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $s = trim((string) $raw);
        $negative = str_starts_with($s, '(') && str_ends_with($s, ')');
        $s = preg_replace('/[^\d,.\-]/', '', $s);
        if ($decimalComma) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } else {
            $s = str_replace(',', '', $s);
        }
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return $negative ? -abs((float) $s) : (float) $s;
    }

    private function date(mixed $raw, ?string $format): ?string
    {
        try {
            if (is_numeric($raw) && (float) $raw > 20000 && (float) $raw < 80000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->toDateString();
            }
            $s = trim((string) $raw);
            if ($format) {
                return Carbon::createFromFormat('!' . $format, $s)->toDateString();
            }
            if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $s, $m)) {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString(); // SA: day first
            }

            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertLooksLikeSpreadsheet(string $ext, string $bytes): void
    {
        $head = substr($bytes, 0, 8);
        $ok = match ($ext) {
            'xlsx' => str_starts_with($head, "PK\x03\x04"),
            'xls' => str_starts_with($head, "\xD0\xCF\x11\xE0") || str_starts_with($head, "PK\x03\x04"),
            default => ! str_contains(substr($bytes, 0, 4096), "\0"),
        };
        if (! $ok) {
            throw new RuntimeException("The file's content is not a valid .{$ext} spreadsheet.");
        }
    }

    private function withLocalFile(ImportBatch $batch, callable $fn): mixed
    {
        $bytes = Storage::disk('documents')->get($batch->file_path);
        if ($bytes === null) {
            throw new RuntimeException('The uploaded file is no longer available.');
        }
        $ext = strtolower(pathinfo($batch->file_name, PATHINFO_EXTENSION));
        $tmp = tempnam(sys_get_temp_dir(), 'imp') . '.' . $ext;
        file_put_contents($tmp, $bytes);
        try {
            return $fn($tmp, $ext);
        } finally {
            @unlink($tmp);
        }
    }
}
