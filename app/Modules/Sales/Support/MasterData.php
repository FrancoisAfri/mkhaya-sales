<?php

namespace App\Modules\Sales\Support;

use App\Modules\Sales\Models;

/**
 * One description per master-data list, so one controller and one screen serve them all
 * (SRS §7, §8, §21.2). Field types: text | number | select | switch | textarea.
 * A select's `options` names another resource in this registry.
 */
final class MasterData
{
    public static function all(): array
    {
        $lookup = fn (string $label, string $model, array $extra = []) => [
            'label' => $label,
            'model' => $model,
            'view' => 'products.view',
            'manage' => 'products.manage',
            'search' => ['code', 'name'],
            'fields' => array_merge([
                ['key' => 'code', 'label' => 'Code', 'type' => 'text'],
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ], $extra, [
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
            ]),
        ];
        $customerSide = fn (array $def) => array_merge($def, ['view' => 'customers.view', 'manage' => 'customers.manage']);

        return [
            'products' => [
                'label' => 'Products',
                'model' => Models\Product::class,
                'view' => 'products.view',
                'manage' => 'products.manage',
                'search' => ['product_code', 'product_name', 'alternative_code'],
                'filters' => ['category_id', 'brand_id', 'is_active'],
                'with' => ['category:id,name', 'brand:id,name', 'unit:id,name'],
                'aliases' => true,
                'fields' => [
                    ['key' => 'product_code', 'label' => 'Product code', 'type' => 'text', 'required' => true],
                    ['key' => 'product_name', 'label' => 'Product name', 'type' => 'text', 'required' => true],
                    ['key' => 'alternative_code', 'label' => 'Alternative code', 'type' => 'text'],
                    ['key' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => 'categories'],
                    ['key' => 'brand_id', 'label' => 'Brand', 'type' => 'select', 'options' => 'brands'],
                    ['key' => 'unit_id', 'label' => 'Unit of measure', 'type' => 'select', 'options' => 'units'],
                    ['key' => 'pack_size', 'label' => 'Pack size', 'type' => 'text'],
                    ['key' => 'currency', 'label' => 'Currency', 'type' => 'text'],
                    ['key' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
                ],
            ],
            'customers' => [
                'label' => 'Customers',
                'model' => Models\Customer::class,
                'view' => 'customers.view',
                'manage' => 'customers.manage',
                'search' => ['customer_code', 'customer_name', 'alternative_name'],
                'filters' => ['customer_group_id', 'region_id', 'is_active'],
                'with' => ['group:id,name', 'region:id,name', 'salesTeam:id,name', 'salesperson:id,name'],
                'aliases' => true,
                'fields' => [
                    ['key' => 'customer_code', 'label' => 'Customer code', 'type' => 'text', 'required' => true],
                    ['key' => 'customer_name', 'label' => 'Customer name', 'type' => 'text', 'required' => true],
                    ['key' => 'alternative_name', 'label' => 'Alternative / trading name', 'type' => 'text'],
                    ['key' => 'customer_group_id', 'label' => 'Customer group', 'type' => 'select', 'options' => 'customer-groups'],
                    ['key' => 'region_id', 'label' => 'Region', 'type' => 'select', 'options' => 'regions'],
                    ['key' => 'business_unit_id', 'label' => 'Business unit', 'type' => 'select', 'options' => 'business-units'],
                    ['key' => 'sales_team_id', 'label' => 'Sales team', 'type' => 'select', 'options' => 'sales-teams'],
                    ['key' => 'salesperson_id', 'label' => 'Salesperson', 'type' => 'select', 'options' => 'salespeople'],
                    ['key' => 'distribution_centre_id', 'label' => 'Distribution centre', 'type' => 'select', 'options' => 'distribution-centres'],
                    ['key' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
                ],
            ],
            'categories' => $lookup('Product categories', Models\ProductCategory::class, [
                ['key' => 'parent_id', 'label' => 'Parent category (for a subcategory)', 'type' => 'select', 'options' => 'categories'],
            ]),
            'brands' => $lookup('Brands', Models\ProductBrand::class),
            'units' => $lookup('Units of measure', Models\ProductUnit::class),
            'customer-groups' => $customerSide($lookup('Customer groups', Models\CustomerGroup::class)),
            'regions' => $customerSide($lookup('Regions', Models\Region::class, [
                ['key' => 'parent_id', 'label' => 'Parent region', 'type' => 'select', 'options' => 'regions'],
            ])),
            'business-units' => $customerSide($lookup('Business units', Models\BusinessUnit::class)),
            'sales-teams' => $customerSide($lookup('Sales teams', Models\SalesTeam::class, [
                ['key' => 'region_id', 'label' => 'Region', 'type' => 'select', 'options' => 'regions'],
                ['key' => 'business_unit_id', 'label' => 'Business unit', 'type' => 'select', 'options' => 'business-units'],
            ])),
            'salespeople' => $customerSide($lookup('Salespeople', Models\Salesperson::class, [
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
            ])),
            'distribution-centres' => $customerSide($lookup('Distribution centres', Models\DistributionCentre::class)),
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();
        abort_unless(isset($all[$key]), 404);

        return $all[$key] + ['key' => $key];
    }

    /** Lists too large to send whole to the browser — searched as you type instead. */
    public const REMOTE = ['customers', 'products'];

    /** Search a list by code or name (for search-as-you-type pickers). */
    public static function search(string $key, string $q = '', int $limit = 30): array
    {
        $def = self::get($key);
        [$code, $label] = self::columns($key);
        $q = trim($q);

        return $def['model']::query()->where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where($code, 'ilike', "{$q}%")->orWhere($label, 'ilike', "%{$q}%")))
            ->orderBy($label)->limit($limit)->get(['id', $code, $label])
            ->map(fn ($r) => ['value' => $r->id, 'title' => $r->{$label} . ($r->{$code} ? " ({$r->{$code}})" : '')])->all();
    }

    /** Labels for ids already chosen, so a picker can show them without loading the whole list. */
    public static function labels(string $key, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $ids) {
            return [];
        }
        [$code, $label] = self::columns($key);

        return self::get($key)['model']::query()->whereIn('id', $ids)->get(['id', $code, $label])
            ->map(fn ($r) => ['value' => $r->id, 'title' => $r->{$label} . ($r->{$code} ? " ({$r->{$code}})" : '')])->all();
    }

    private static function columns(string $key): array
    {
        return match ($key) {
            'products' => ['product_code', 'product_name'],
            'customers' => ['customer_code', 'customer_name'],
            default => ['code', 'name'],
        };
    }

    /** id → label options for a select field. */
    public static function options(string $key): array
    {
        $def = self::get($key);
        $label = $key === 'products' ? 'product_name' : ($key === 'customers' ? 'customer_name' : 'name');

        return $def['model']::query()->where('is_active', true)->orderBy($label)->limit(2000)
            ->get(['id', $label])->map(fn ($r) => ['value' => $r->id, 'title' => $r->{$label}])->all();
    }
}
