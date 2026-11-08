<?php

namespace App\Modules\Sales\Models;

use Illuminate\Support\Str;

class Target extends SalesModel
{
    public const METRICS = [
        'net_value' => 'Net sales value', 'gross_value' => 'Gross sales value', 'quantity' => 'Units / cases sold',
        'active_customers' => 'Active customers (coverage)', 'transactions' => 'Number of sales lines',
        'formula' => 'Formula (e.g. net_value / quantity)',
    ];
    public const METHODS = ['fixed' => 'Reach the value', 'minimum' => 'At least (minimum threshold)', 'maximum' => 'At most (maximum threshold)', 'range' => 'Within a range',
        'growth' => 'Growth % on the same period last year', 'composite' => 'Composite score of its child targets (weighted)'];
    public const PERIODS = ['daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'annual', 'financial_year', 'custom'];
    public const DIMENSIONS = [
        'customer_id' => 'Customer', 'customer_group_id' => 'Customer group', 'product_id' => 'Product', 'category_id' => 'Product category',
        'brand_id' => 'Brand', 'region_id' => 'Region', 'business_unit_id' => 'Business unit', 'sales_team_id' => 'Sales team',
        'salesperson_id' => 'Salesperson', 'distribution_centre_id' => 'Distribution centre',
    ];

    protected $casts = [
        'dimensions' => 'array', 'start_date' => 'date', 'end_date' => 'date', 'target_value' => 'float', 'target_value_max' => 'float', 'weight' => 'float',
        'submitted_at' => 'datetime', 'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->uuid ??= (string) Str::uuid());
        static::saved(fn (self $t) => \App\Modules\Sales\Services\BI\BIQueryService::invalidate($t->company_id));
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function creator() { return $this->belongsTo(\App\Core\User\Models\User::class, 'created_by'); }
    public function approvals() { return $this->hasMany(TargetApproval::class)->orderBy('decided_at'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('name'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function revisions() { return $this->hasMany(TargetRevision::class)->orderBy('created_at'); }

    /** Lifecycle as the SRS names it (§11.6): approved targets are Active inside their period. */
    public function lifecycle(): string
    {
        if ($this->status === 'approved') {
            $today = now()->toDateString();
            if ($this->end_date->toDateString() < $today) {
                return 'completed';
            }

            return $this->start_date->toDateString() <= $today ? ($this->version > 1 ? 'active (revised)' : 'active') : 'approved';
        }

        return $this->status === 'submitted' && $this->version > 1 ? 'revision submitted' : $this->status;
    }
}
