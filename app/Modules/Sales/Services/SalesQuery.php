<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use App\Modules\Sales\Support\MasterData;
use App\Modules\Tenancy\Services\CurrentCompany;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The single definition of "which sales rows count" — used by the records screen, dashboards,
 * reports, targets, forecasts and the AI assistant, so every figure is computed the same way
 * (SRS §32 rule 12). It applies the company, live rows only, the global filters (§13.6) and the
 * user's data scopes (§4.3).
 */
class SalesQuery
{
    public const FILTERS = [
        'customer_id', 'customer_group_id', 'product_id', 'category_id', 'brand_id', 'region_id',
        'business_unit_id', 'sales_team_id', 'salesperson_id', 'distribution_centre_id',
    ];

    public function filters(Request $request): array
    {
        $f = ['from' => $request->query('from'), 'to' => $request->query('to'), 'batch' => $request->query('batch')];
        foreach (self::FILTERS as $k) {
            $v = $request->query($k);
            $f[$k] = $v !== null && $v !== '' ? (int) $v : null;
        }

        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    public function base(array $f, ?User $user = null): Builder
    {
        $company = (int) app(CurrentCompany::class)->id() ?: (int) $user?->company_id;
        $q = DB::table('sales_records as s')->where('s.company_id', $company)->where('s.status', 'active');

        if (! empty($f['from'])) {
            $q->where('s.transaction_date', '>=', $f['from']);
        }
        if (! empty($f['to'])) {
            $q->where('s.transaction_date', '<=', $f['to']);
        }
        if (! empty($f['batch'])) {
            $q->whereIn('s.import_batch_id', DB::table('import_batches')->where('uuid', $f['batch'])->select('id'));
        }
        foreach (['customer_id', 'product_id', 'region_id', 'business_unit_id', 'sales_team_id', 'salesperson_id', 'distribution_centre_id'] as $col) {
            if (! empty($f[$col])) {
                $q->where("s.{$col}", $f[$col]);
            }
        }
        if (! empty($f['customer_group_id'])) {
            $q->whereIn('s.customer_id', DB::table('customers')->where('customer_group_id', $f['customer_group_id'])->select('id'));
        }
        if (! empty($f['brand_id'])) {
            $q->whereIn('s.product_id', DB::table('products')->where('brand_id', $f['brand_id'])->select('id'));
        }
        if (! empty($f['category_id'])) {
            $cats = DB::table('product_categories')->where('id', $f['category_id'])->orWhere('parent_id', $f['category_id'])->select('id');
            $q->whereIn('s.product_id', DB::table('products')->whereIn('category_id', $cats)->select('id'));
        }

        if ($user) {
            $this->applyScopes($q, $user);
        }

        return $q;
    }

    /** A user limited to regions / teams / business units / customer groups sees only those (§4.3). */
    public function applyScopes(Builder $q, User $user): void
    {
        $scopes = DB::table('user_data_scopes')->where('user_id', $user->id)->get()->groupBy('scope_type');
        foreach ($scopes as $type => $rows) {
            $ids = $rows->pluck('scope_id')->all();
            match ($type) {
                'region' => $q->where(fn ($w) => $w->whereIn('s.region_id', $ids)
                    ->orWhereIn('s.customer_id', DB::table('customers')->whereIn('region_id', $ids)->select('id'))),
                'sales_team' => $q->where(fn ($w) => $w->whereIn('s.sales_team_id', $ids)
                    ->orWhereIn('s.customer_id', DB::table('customers')->whereIn('sales_team_id', $ids)->select('id'))),
                'business_unit' => $q->where(fn ($w) => $w->whereIn('s.business_unit_id', $ids)
                    ->orWhereIn('s.customer_id', DB::table('customers')->whereIn('business_unit_id', $ids)->select('id'))),
                'customer_group' => $q->whereIn('s.customer_id', DB::table('customers')->whereIn('customer_group_id', $ids)->select('id')),
                default => null,
            };
        }
    }

    /**
     * Options for the global filter bar. Customers and products can run to tens of thousands, so
     * only the ones already selected are sent; the picker searches the rest as the user types.
     */
    public function filterOptions(array $selected = []): array
    {
        $pick = fn (string $k) => array_merge((array) ($selected[$k] ?? []), array_filter([$selected[$k . '_single'] ?? null]));

        return [
            'customers' => MasterData::labels('customers', $pick('customer_id')),
            'products' => MasterData::labels('products', $pick('product_id')),
            'customer_groups' => MasterData::options('customer-groups'),
            'categories' => MasterData::options('categories'),
            'brands' => MasterData::options('brands'),
            'regions' => MasterData::options('regions'),
            'business_units' => MasterData::options('business-units'),
            'sales_teams' => MasterData::options('sales-teams'),
            'salespeople' => MasterData::options('salespeople'),
        ];
    }
}
