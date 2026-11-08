<?php

namespace App\Modules\Sales\Models;

class ProductAlias extends SalesModel
{
    protected $table = 'product_aliases';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
