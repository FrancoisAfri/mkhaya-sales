<?php

namespace App\Modules\Sales\Models;

use Illuminate\Support\Str;

/** A client's file format (SRS §6.2): sheet, header row, column → field mapping, ingestion mode. */
class ReportLayout extends SalesModel
{
    public const MODES = [
        'append' => 'Individual transactions — each row is added once',
        'upsert_daily' => 'Daily totals — a row per day/customer/product, a re-sent day replaces it',
        'replace_period' => 'Snapshot / full report — replaces everything this layout loaded for the dates in the file',
    ];

    protected $casts = [
        'mapping' => 'array', 'dedupe_fields' => 'array', 'keep_unmapped' => 'boolean',
        'decimal_comma' => 'boolean', 'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $l) => $l->uuid ??= (string) Str::uuid());
        // A changed mapping is a new template version, so an older file is never read the new way silently.
        static::updating(function (self $l) {
            if ($l->isDirty(['mapping', 'sheet', 'header_row', 'ingestion_mode', 'dedupe_fields'])) {
                $l->version = (int) $l->getOriginal('version') + 1;
            }
        });
    }

    /** source heading → internal field */
    public function fieldFor(string $source): ?string
    {
        foreach ($this->mapping ?? [] as $m) {
            if (mb_strtolower(trim($m['source'])) === mb_strtolower(trim($source))) {
                return $m['field'] ?: null;
            }
        }

        return null;
    }
}
