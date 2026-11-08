<?php

namespace App\Modules\Sales\Support;

use App\Modules\Tenancy\Services\CurrentCompany;

/**
 * The company a Sales Intelligence request works in: the one the request is pinned to (including a
 * platform admin who chose "View as company"), else the user's own. Sales data always belongs to
 * one company, so the sales routes refuse to run without one (see RequireSalesCompany).
 */
final class SalesCompany
{
    public static function id(): ?int
    {
        return app(CurrentCompany::class)->id() ?: (auth()->user()?->company_id ?: null);
    }
}
