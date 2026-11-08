<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A sellable add-on stacked on top of a base plan: extra users (per-user rate),
 * AI token packs, or storage packs. `price_monthly`/`units` are null for
 * quote-only tiers (e.g. Corporate AI). Type ∈ user | ai_pack | storage_pack.
 */
class BillingAddon extends Model
{
    protected $table = 'billing_addons';

    protected $fillable = ['type', 'code', 'name', 'unit_label', 'units', 'price_monthly', 'sort', 'is_active', 'tiers'];

    protected $casts = [
        'units' => 'integer',
        'price_monthly' => 'decimal:2',
        'sort' => 'integer',
        'is_active' => 'boolean',
        'tiers' => 'array',
    ];

    /**
     * Price per unit for a company holding $totalUnits of this add-on's type. With volume tiers the
     * band is chosen by the total and every unit pays that band's price (4 extra websites at R495
     * each, 25 at R395 each); without tiers it is the flat price.
     */
    public function unitPriceFor(int $totalUnits): float
    {
        foreach ((array) $this->tiers as $band) {
            $from = (int) ($band['from'] ?? 1);
            $to = $band['to'] ?? null;
            if ($totalUnits >= $from && ($to === null || $totalUnits <= (int) $to)) {
                return (float) $band['price'];
            }
        }

        return (float) $this->price_monthly / max(1, (int) $this->units);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }
}
