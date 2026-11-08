<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The sales fact (SRS §12.1). Written by imports; changed only through SalesAdjustment. */
class SalesRecord extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];
    protected $casts = [
        'transaction_date' => 'date', 'attributes' => 'array', 'is_adjusted' => 'boolean',
        'quantity' => 'float', 'unit_price' => 'float', 'gross_value' => 'float', 'discount' => 'float', 'net_value' => 'float',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batch(): BelongsTo { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
    public function adjustments(): HasMany { return $this->hasMany(SalesAdjustment::class); }
}
