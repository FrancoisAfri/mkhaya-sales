<?php

namespace App\Modules\Sales\Models;

class TargetRevision extends SalesModel
{
    public $timestamps = false;
    protected $casts = ['previous' => 'array', 'created_at' => 'datetime', 'previous_value' => 'float', 'new_value' => 'float'];

    public function reviser() { return $this->belongsTo(\App\Core\User\Models\User::class, 'revised_by'); }
}
