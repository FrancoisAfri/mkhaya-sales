<?php

namespace App\Modules\Sales\Models;

class TargetApproval extends SalesModel
{
    public $timestamps = false;
    protected $casts = ['decided_at' => 'datetime'];

    public function decider() { return $this->belongsTo(\App\Core\User\Models\User::class, 'decided_by'); }
}
