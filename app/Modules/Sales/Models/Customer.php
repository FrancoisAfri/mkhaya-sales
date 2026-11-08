<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Customer extends SalesModel
{
    protected $casts = ['is_active' => 'boolean', 'needs_review' => 'boolean', 'attributes' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
    }

    public function group(): BelongsTo { return $this->belongsTo(CustomerGroup::class, 'customer_group_id'); }
    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function businessUnit(): BelongsTo { return $this->belongsTo(BusinessUnit::class); }
    public function salesTeam(): BelongsTo { return $this->belongsTo(SalesTeam::class); }
    public function salesperson(): BelongsTo { return $this->belongsTo(Salesperson::class); }
    public function aliases(): HasMany { return $this->hasMany(CustomerAlias::class); }
}
