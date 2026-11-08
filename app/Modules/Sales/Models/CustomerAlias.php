<?php

namespace App\Modules\Sales\Models;

class CustomerAlias extends SalesModel
{
    protected $table = 'customer_aliases';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
