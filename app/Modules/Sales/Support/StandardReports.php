<?php

namespace App\Modules\Sales\Support;

/**
 * The standard report catalogue (SRS §14.1). Each report is a preset of the one grouped engine —
 * dimensions, measure, comparison and ordering — so the report builder can open any of them and
 * change it. Target, forecast and data reports are produced by their own services.
 */
final class StandardReports
{
    public static function all(): array
    {
        $r = fn (string $title, array $dims, string $compare = 'none', string $order = 'desc', string $measure = 'net_value') =>
            compact('title', 'dims', 'compare', 'order', 'measure');

        return [
            'daily' => $r('Daily sales', ['day'], 'previous_year', 'chronological'),
            'weekly' => $r('Weekly sales', ['week'], 'previous_year', 'chronological'),
            'monthly' => $r('Monthly sales', ['month'], 'previous_year', 'chronological'),
            'annual' => $r('Annual sales', ['year'], 'none', 'chronological'),
            'by_customer' => $r('Sales by customer', ['customer']),
            'by_product' => $r('Sales by product', ['product']),
            'by_category' => $r('Sales by category', ['category']),
            'by_brand' => $r('Sales by brand', ['brand']),
            'customer_product' => $r('Customer–product sales', ['customer', 'product']),
            'customer_ranking' => $r('Customer ranking', ['customer'], 'previous_year'),
            'product_ranking' => $r('Product ranking', ['product'], 'previous_year'),
            'yoy' => $r('Year-on-year comparison', ['month'], 'previous_year', 'chronological'),
            'mom' => $r('Month-on-month comparison', ['month'], 'previous_period', 'chronological'),
            'product_growth' => $r('Product growth', ['product'], 'previous_year', 'growth'),
            'customer_growth' => $r('Customer growth', ['customer'], 'previous_year', 'growth'),
            'declining_customers' => $r('Declining customers', ['customer'], 'previous_year', 'decline'),
            'target_achievement' => ['title' => 'Sales target achievement', 'special' => 'targets'],
            'variance' => ['title' => 'Sales variance against target', 'special' => 'targets'],
            'forecast' => ['title' => 'Sales forecast', 'special' => 'forecast'],
            'import_reconciliation' => ['title' => 'Data import reconciliation', 'special' => 'imports'],
            'data_quality' => ['title' => 'Data quality', 'special' => 'quality'],
        ];
    }
}
