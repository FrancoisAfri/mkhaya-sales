<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\BillingAddon;
use App\Modules\Tenancy\Models\CompanyAddon;

/**
 * Volume-tiered add-ons (extra websites): the band comes from the company's total units of the type,
 * and every unit is charged at that band's price. A company row stores its per-row monthly price
 * (units in the package × the band price), so the subscription total keeps reading quantity × price.
 */
class AddonTierPricing
{
    /** Monthly charge for $units of a tiered type (e.g. 25 extra websites → 25 × R395). */
    public function monthlyFor(string $type, int $units): float
    {
        $tiered = $this->tieredAddon($type);

        return $tiered ? round($units * $tiered->unitPriceFor($units), 2) : 0.0;
    }

    /** Re-price every row of a tiered type for one company after its quantity changed. */
    public function reprice(int $companyId, string $type): void
    {
        $tiered = $this->tieredAddon($type);
        if (! $tiered) {
            return;
        }
        $units = BillingAddon::query()->where('type', $type)->pluck('units', 'code');
        $rows = CompanyAddon::query()->where('company_id', $companyId)->where('type', $type)->get();
        $total = (int) $rows->sum(fn (CompanyAddon $r) => (int) $r->quantity * (int) ($units[$r->addon_code] ?? 1));
        $unitPrice = $tiered->unitPriceFor(max(1, $total));
        foreach ($rows as $row) {
            $row->update(['price_monthly' => round($unitPrice * (int) ($units[$row->addon_code] ?? 1), 2)]);
        }
    }

    /** Every type with tiers, for callers that change several add-ons at once. */
    public function repriceAll(int $companyId): void
    {
        foreach (BillingAddon::query()->whereNotNull('tiers')->distinct()->pluck('type') as $type) {
            $this->reprice($companyId, $type);
        }
    }

    private function tieredAddon(string $type): ?BillingAddon
    {
        return BillingAddon::query()->where('type', $type)->whereNotNull('tiers')->orderBy('sort')->first();
    }
}
