<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends SalesModel
{
    protected $casts = ['is_active' => 'boolean', 'needs_review' => 'boolean', 'attributes' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->uuid ??= (string) Str::uuid());
    }

    public function category(): BelongsTo { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function brand(): BelongsTo { return $this->belongsTo(ProductBrand::class, 'brand_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(ProductUnit::class, 'unit_id'); }
    public function aliases(): HasMany { return $this->hasMany(ProductAlias::class); }
}
