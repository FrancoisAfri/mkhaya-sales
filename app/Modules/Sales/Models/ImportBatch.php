<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ImportBatch extends SalesModel
{
    protected $casts = [
        'totals' => 'array', 'source_totals' => 'array', 'headers' => 'array',
        'period_from' => 'date', 'period_to' => 'date',
        'confirmed_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $b) => $b->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function layout(): BelongsTo { return $this->belongsTo(ReportLayout::class, 'layout_id'); }
    public function rows(): HasMany { return $this->hasMany(ImportRow::class, 'batch_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(\App\Core\User\Models\User::class, 'uploaded_by'); }

    public function permission(): string
    {
        return match ($this->target) {
            'products' => 'imports.products',
            'customers' => 'imports.customers',
            'targets' => 'targets.create',
            default => 'imports.historical',
        };
    }

    public function isCommitted(): bool { return $this->status === 'committed'; }
}
