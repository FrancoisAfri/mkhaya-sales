<?php

namespace App\Modules\Sales\Models;

/** A code + name classification list (categories, brands, regions, teams…). */
abstract class Lookup extends SalesModel
{
    protected $casts = ['is_active' => 'boolean'];
}
