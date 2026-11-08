<?php

namespace App\Modules\Sales\Models;

class SalesAdjustment extends SalesModel
{
    public const FIELDS = ['quantity', 'unit_price', 'gross_value', 'discount', 'net_value', 'transaction_date', 'customer_id', 'product_id'];

    protected $casts = ['decided_at' => 'datetime'];

    public function record() { return $this->belongsTo(SalesRecord::class, 'sales_record_id'); }
    public function requester() { return $this->belongsTo(\App\Core\User\Models\User::class, 'requested_by'); }
}
