<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'company_id', 'plan_code', 'status', 'current_period_start', 'current_period_end',
        // The commercial contract (SRS §19.1).
        'monthly_price', 'contract_start', 'contract_end', 'billing_frequency', 'named_users', 'storage_gb',
        'integration_allowance', 'ai_token_allowance', 'change_request_hourly_rate', 'terms_note', 'payment_terms_days', 'notice_months',
    ];

    protected $casts = [
        'current_period_start' => 'datetime', 'current_period_end' => 'datetime',
        'contract_start' => 'date', 'contract_end' => 'date',
        'monthly_price' => 'decimal:2', 'change_request_hourly_rate' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_code', 'code');
    }
}
