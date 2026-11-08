<?php

namespace App\Modules\Sales\Support;

/**
 * The internal fields a file column can be mapped to, per import target (SRS §6.2, §9.2).
 * Anything a client sends beyond these is kept as a custom attribute (`attr:<name>`), so a report
 * with 20 columns and one with 30 both load without a code change.
 *
 * type: date | number | text. `aliases` are headings recognised automatically when mapping.
 */
final class ImportFields
{
    public const TARGETS = [
        'sales' => 'Sales (daily or historical)',
        'products' => 'Products',
        'customers' => 'Customers',
        'targets' => 'Targets',
    ];

    public static function for(string $target): array
    {
        return match ($target) {
            'sales' => [
                'transaction_date' => ['label' => 'Sales date', 'type' => 'date', 'required' => true, 'aliases' => ['date', 'sales date', 'invoice date', 'inv date', 'transaction date', 'doc date', 'billing date', 'posting date']],
                'customer_code' => ['label' => 'Customer code', 'type' => 'text', 'aliases' => ['customer code', 'cust code', 'cust no', 'account', 'acc no', 'acc code', 'account no', 'customer no', 'customer number', 'account number', 'sold to', 'ship to']],
                'customer_name' => ['label' => 'Customer name', 'type' => 'text', 'aliases' => ['customer', 'customer name', 'cust name', 'account name', 'acc name', 'sold to name', 'store', 'outlet']],
                'product_code' => ['label' => 'Product code', 'type' => 'text', 'aliases' => ['product code', 'item code', 'item no', 'sku', 'article', 'material', 'material no', 'stock code', 'prod code']],
                'product_name' => ['label' => 'Product description', 'type' => 'text', 'aliases' => ['product', 'product description', 'description', 'desc', 'item description', 'product name', 'material desc', 'material description', 'article description', 'item name']],
                'quantity' => ['label' => 'Quantity', 'type' => 'number', 'aliases' => ['qty', 'quantity', 'units', 'cases', 'cartons', 'volume', 'ctns']],
                'unit_of_measure' => ['label' => 'Unit of measure', 'type' => 'text', 'aliases' => ['uom', 'unit', 'unit of measure']],
                'unit_price' => ['label' => 'Unit price', 'type' => 'number', 'aliases' => ['unit price', 'price']],
                'gross_value' => ['label' => 'Gross sales value', 'type' => 'number', 'aliases' => ['gross', 'gross sales', 'gross value', 'gross amount']],
                'discount' => ['label' => 'Discount', 'type' => 'number', 'aliases' => ['discount', 'disc']],
                'net_value' => ['label' => 'Net sales value', 'type' => 'number', 'aliases' => ['sales value', 'net sales', 'net value', 'nett value', 'nett sales', 'value', 'amount', 'nett', 'revenue', 'sales', 'turnover', 'net amount']],
                'currency' => ['label' => 'Currency', 'type' => 'text', 'aliases' => ['currency', 'curr']],
                'external_id' => ['label' => 'Source transaction / invoice no.', 'type' => 'text', 'aliases' => ['invoice', 'invoice no', 'invoice number', 'document', 'doc no', 'transaction id']],
                'region' => ['label' => 'Region (code or name)', 'type' => 'text', 'aliases' => ['region', 'area', 'province']],
                'business_unit' => ['label' => 'Business unit', 'type' => 'text', 'aliases' => ['business unit', 'division', 'bu']],
                'sales_team' => ['label' => 'Sales team', 'type' => 'text', 'aliases' => ['team', 'sales team']],
                'salesperson' => ['label' => 'Salesperson', 'type' => 'text', 'aliases' => ['rep', 'sales rep', 'salesperson', 'sales person', 'representative']],
                'distribution_centre' => ['label' => 'Distribution centre', 'type' => 'text', 'aliases' => ['dc', 'depot', 'distribution centre', 'warehouse', 'branch']],
            ],
            'products' => [
                'product_code' => ['label' => 'Product code', 'type' => 'text', 'required' => true, 'aliases' => ['product code', 'item code', 'sku', 'code']],
                'product_name' => ['label' => 'Product name', 'type' => 'text', 'required' => true, 'aliases' => ['product name', 'description', 'name', 'product']],
                'alternative_code' => ['label' => 'Alternative code', 'type' => 'text', 'aliases' => ['alternative code', 'alt code', 'barcode', 'ean']],
                'category' => ['label' => 'Category', 'type' => 'text', 'aliases' => ['category']],
                'subcategory' => ['label' => 'Subcategory', 'type' => 'text', 'aliases' => ['subcategory', 'sub category']],
                'brand' => ['label' => 'Brand', 'type' => 'text', 'aliases' => ['brand']],
                'unit' => ['label' => 'Unit of measure', 'type' => 'text', 'aliases' => ['uom', 'unit', 'unit of measure']],
                'pack_size' => ['label' => 'Pack size', 'type' => 'text', 'aliases' => ['pack size', 'pack']],
                'currency' => ['label' => 'Currency', 'type' => 'text', 'aliases' => ['currency']],
                'status' => ['label' => 'Status (active/inactive)', 'type' => 'text', 'aliases' => ['status', 'active']],
            ],
            'customers' => [
                'customer_code' => ['label' => 'Customer code', 'type' => 'text', 'required' => true, 'aliases' => ['customer code', 'account', 'code', 'customer no']],
                'customer_name' => ['label' => 'Customer name', 'type' => 'text', 'required' => true, 'aliases' => ['customer name', 'name', 'customer', 'account name']],
                'alternative_name' => ['label' => 'Alternative / trading name', 'type' => 'text', 'aliases' => ['trading name', 'alternative name']],
                'customer_group' => ['label' => 'Customer group', 'type' => 'text', 'aliases' => ['group', 'customer group', 'channel']],
                'region' => ['label' => 'Region', 'type' => 'text', 'aliases' => ['region', 'area', 'province']],
                'business_unit' => ['label' => 'Business unit', 'type' => 'text', 'aliases' => ['business unit', 'division']],
                'sales_team' => ['label' => 'Sales team', 'type' => 'text', 'aliases' => ['team', 'sales team']],
                'salesperson' => ['label' => 'Salesperson', 'type' => 'text', 'aliases' => ['rep', 'salesperson', 'sales rep']],
                'distribution_centre' => ['label' => 'Distribution centre', 'type' => 'text', 'aliases' => ['dc', 'depot', 'distribution centre']],
                'province' => ['label' => 'Province', 'type' => 'text', 'aliases' => ['province', 'state']],
                'city' => ['label' => 'City / town', 'type' => 'text', 'aliases' => ['city', 'town', 'suburb']],
                'latitude' => ['label' => 'Latitude', 'type' => 'number', 'aliases' => ['latitude', 'lat', 'gps lat', 'y']],
                'longitude' => ['label' => 'Longitude', 'type' => 'number', 'aliases' => ['longitude', 'lng', 'lon', 'long', 'gps long', 'x']],
                'status' => ['label' => 'Status (active/inactive)', 'type' => 'text', 'aliases' => ['status', 'active']],
            ],
            'targets' => [
                'name' => ['label' => 'Target name', 'type' => 'text', 'required' => true, 'aliases' => ['target', 'target name', 'name']],
                'metric' => ['label' => 'Measure (net_value, gross_value, quantity, active_customers, transactions)', 'type' => 'text', 'aliases' => ['measure', 'metric', 'kpi']],
                'method' => ['label' => 'Rule (fixed, minimum, maximum, range)', 'type' => 'text', 'aliases' => ['rule', 'method', 'type']],
                'period_type' => ['label' => 'Period (monthly, quarterly, annual…)', 'type' => 'text', 'aliases' => ['period', 'period type', 'frequency']],
                'start_date' => ['label' => 'Start date', 'type' => 'date', 'required' => true, 'aliases' => ['start', 'from', 'start date', 'from date']],
                'end_date' => ['label' => 'End date', 'type' => 'date', 'required' => true, 'aliases' => ['end', 'to', 'end date', 'to date']],
                'target_value' => ['label' => 'Target value', 'type' => 'number', 'required' => true, 'aliases' => ['target', 'value', 'target value', 'amount']],
                'target_value_max' => ['label' => 'Upper value (range)', 'type' => 'number', 'aliases' => ['max', 'upper', 'to value']],
                'customer' => ['label' => 'Customer (code or name)', 'type' => 'text', 'aliases' => ['customer', 'customer code', 'account']],
                'customer_group' => ['label' => 'Customer group', 'type' => 'text', 'aliases' => ['customer group', 'channel']],
                'product' => ['label' => 'Product (code or name)', 'type' => 'text', 'aliases' => ['product', 'product code', 'sku']],
                'category' => ['label' => 'Product category', 'type' => 'text', 'aliases' => ['category']],
                'brand' => ['label' => 'Brand', 'type' => 'text', 'aliases' => ['brand']],
                'region' => ['label' => 'Region', 'type' => 'text', 'aliases' => ['region', 'area', 'province']],
                'business_unit' => ['label' => 'Business unit', 'type' => 'text', 'aliases' => ['business unit', 'division']],
                'sales_team' => ['label' => 'Sales team', 'type' => 'text', 'aliases' => ['team', 'sales team']],
                'salesperson' => ['label' => 'Salesperson', 'type' => 'text', 'aliases' => ['rep', 'salesperson', 'sales rep']],
                'distribution_centre' => ['label' => 'Distribution centre', 'type' => 'text', 'aliases' => ['dc', 'depot', 'distribution centre']],
                'notes' => ['label' => 'Notes', 'type' => 'text', 'aliases' => ['notes', 'comment']],
            ],
            default => abort(404),
        };
    }

    /**
     * Best guess of the field for a heading, used to pre-fill a mapping: an exact heading or alias
     * first, otherwise the longest alias that appears as whole words inside the heading
     * ("Inv Date" → sales date, "Nett Value ZAR" → net value). Only a suggestion — the person
     * confirms the mapping when saving the layout.
     */
    public static function guess(string $target, string $heading): ?string
    {
        $h = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $heading)));
        $best = null;
        $bestLen = 0;
        foreach (self::for($target) as $key => $f) {
            $names = array_merge([str_replace('_', ' ', $key), mb_strtolower($f['label'])], $f['aliases']);
            foreach ($names as $alias) {
                if ($h === $alias) {
                    return $key;
                }
                if (mb_strlen($alias) > $bestLen && preg_match('/\b' . preg_quote($alias, '/') . '\b/u', $h)) {
                    $best = $key;
                    $bestLen = mb_strlen($alias);
                }
            }
        }

        return $best;
    }

    /** Default mapping when no layout exists: every heading guessed, the rest kept as attributes. */
    public static function defaultMapping(string $target, array $headings): array
    {
        $used = [];

        return array_map(function ($h) use ($target, &$used) {
            $field = self::guess($target, (string) $h);
            if ($field && isset($used[$field])) {
                $field = null;
            }
            if ($field) {
                $used[$field] = true;
            }

            return ['source' => (string) $h, 'field' => $field ?? 'attr:' . self::slug((string) $h)];
        }, $headings);
    }

    public static function slug(string $heading): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($heading)), '_') ?: 'column';
    }
}
