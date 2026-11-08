<?php

namespace App\Modules\Sales\Models;

class TargetApprovalRule extends SalesModel
{
    protected $casts = ['min_value' => 'float', 'max_value' => 'float', 'allow_creator' => 'boolean', 'is_active' => 'boolean'];

    public function approver() { return $this->belongsTo(\App\Core\User\Models\User::class, 'approver_user_id'); }

    public function applies(Target $t): bool
    {
        return $this->is_active
            && ($this->metric === null || $this->metric === $t->metric)
            && ($this->min_value === null || $t->target_value >= $this->min_value)
            && ($this->max_value === null || $t->target_value <= $this->max_value);
    }
}
