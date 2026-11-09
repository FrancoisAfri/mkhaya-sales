<?php

namespace App\Modules\Sales\Support;

/**
 * Ready-made dashboards (BI spec §4.6) and the role landing pages (§5). Each widget names a visual
 * from the library and the measure-engine configuration it draws. Positions are on a 12-column grid.
 */
final class DashboardTemplates
{
    public static function all(): array
    {
        $kpi = fn ($title, $measures, $x, $period = 'mtd', $type = 'kpi_card') => ['type' => $type, 'title' => $title, 'config' => ['kind' => 'kpi', 'measures' => $measures, 'period' => $period], 'x' => $x, 'y' => 0, 'w' => 2, 'h' => 4];
        $w = fn ($type, $title, $config, $x, $y, $wd, $h) => compact('type', 'title', 'config', 'x', 'y') + ['w' => $wd, 'h' => $h];

        $executive = [
            // Meeting 2026-11-09: value and cases side by side, never units; time gone replaces customers.
            $kpi('Total sales', ['net_value', 'growth_pct'], 0, 'mtd', 'kpi_sparkline'),
            $kpi('Sales target', ['target', 'net_value'], 2, 'mtd', 'kpi_target'),
            $kpi('Volume (cases)', ['volume_target', 'quantity'], 4, 'mtd', 'bullet'),
            $kpi('Cases target', ['volume_target', 'quantity'], 6, 'mtd', 'kpi_target'),
            $kpi('Month-end forecast', ['net_value', 'month_target'], 8, 'mtd', 'run_rate_card'),
            $kpi('Time elapsed', ['net_value', 'month_target'], 10, 'mtd', 'month_elapsed'),
            // Year to date, January to the latest month — the same months as "Performance by month" below.
            $w('combo_column_line', 'Monthly sales: actual vs target (year to date)', ['kind' => 'time', 'measures' => ['net_value', 'target'], 'grain' => 'month', 'period' => 'ytd'], 0, 3, 6, 8),
            $w('donut', 'Sales by retail group', ['kind' => 'category', 'measures' => ['net_value'], 'dimension' => 'customer_group', 'period' => 'ytd', 'top' => 6, 'other' => true], 6, 3, 3, 8),
            $w('pie', 'Sales by category', ['kind' => 'category', 'measures' => ['net_value'], 'dimension' => 'category', 'period' => 'ytd', 'top' => 6, 'other' => true], 9, 3, 3, 8),
            $w('bar_ranked', 'Top 10 customers', ['kind' => 'category', 'measures' => ['net_value'], 'dimension' => 'customer', 'period' => 'ytd', 'top' => 10], 0, 11, 4, 9),
            $w('bar_ranked', 'Top 10 products', ['kind' => 'category', 'measures' => ['net_value'], 'dimension' => 'product', 'period' => 'ytd', 'top' => 10], 4, 11, 4, 9),
            $w('diverging_bar', 'Customer growth and decline vs last year', ['kind' => 'category', 'measures' => ['growth_pct'], 'dimension' => 'customer', 'period' => 'ytd', 'top' => 12], 8, 11, 4, 9),
            $w('column_clustered', 'Sales by product category vs last year', ['kind' => 'category', 'measures' => ['net_value', 'last_year'], 'dimension' => 'category', 'period' => 'ytd', 'top' => 10], 0, 20, 6, 8),
            $w('heatmap_matrix', 'Performance by month (year to date, by region)', ['kind' => 'matrix', 'measures' => ['net_value'], 'dimension' => 'region', 'series' => 'month', 'period' => 'ytd', 'top' => 10], 6, 20, 6, 8),
            $w('forecast_line', 'Forecast versus target', ['kind' => 'forecast', 'period' => 'ytd'], 0, 28, 6, 8),
            $w('waterfall', 'What moved sales vs last year (last month)', ['kind' => 'waterfall', 'dimension' => 'customer', 'period' => 'last_month', 'top' => 8], 6, 28, 6, 8),
            $w('smart_narrative', 'Summary', ['kind' => 'narrative', 'period' => 'mtd'], 0, 36, 6, 6),
            $w('ai_insight', 'AI insights', ['kind' => 'narrative', 'period' => 'mtd'], 6, 36, 6, 6),
            $w('data_status', 'Data freshness and imports', ['kind' => 'kpi', 'measures' => ['transactions'], 'period' => 'mtd'], 0, 42, 12, 5),
        ];

        $customer = [
            $kpi('Active customers', ['active_customers'], 0, 'ytd'),
            $kpi('Average sales per customer', ['avg_customer_sales'], 2, 'ytd'),
            $kpi('Customer sales', ['net_value', 'growth_pct'], 4, 'ytd', 'kpi_sparkline'),
            $w('pareto', '80/20: customers by contribution', ['kind' => 'pareto', 'measures' => ['net_value'], 'dimension' => 'customer', 'period' => 'ytd', 'top' => 20], 6, 0, 6, 8),
            $w('quadrant', 'Customer quadrant: size vs growth', ['kind' => 'scatter', 'dimension' => 'customer', 'period' => 'ytd', 'top' => 150], 0, 3, 6, 9),
            $w('bar_ranked', 'Customer ranking', ['kind' => 'category', 'measures' => ['net_value'], 'dimension' => 'customer', 'period' => 'ytd', 'top' => 15], 6, 8, 6, 9),
            $w('histogram', 'Distribution of customer sales', ['kind' => 'histogram', 'dimension' => 'customer', 'period' => 'ytd'], 0, 12, 6, 7),
            $w('boxplot', 'Customer sales spread by region', ['kind' => 'boxplot', 'dimension' => 'customer', 'series' => 'region', 'period' => 'ytd'], 6, 17, 6, 7),
            $w('cohort', 'Customer retention by first-purchase month', ['kind' => 'cohort', 'period' => 'ytd'], 0, 19, 12, 8),
        ];

        $product = [
            $kpi('Products sold', ['active_products'], 0, 'ytd'),
            $kpi('Average selling price', ['avg_selling_price'], 2, 'ytd'),
            $kpi('Volume (cases)', ['quantity', 'growth_pct'], 4, 'ytd', 'kpi_sparkline'),
            $w('sunburst', 'Category → product', ['kind' => 'treemap', 'measures' => ['net_value'], 'dimension' => 'category', 'series' => 'product', 'period' => 'ytd', 'top' => 6], 6, 0, 6, 9),
            $w('column_stacked', 'Monthly sales by category', ['kind' => 'time', 'measures' => ['net_value'], 'series' => 'category', 'grain' => 'month', 'period' => 'last_12_months', 'top' => 6], 0, 3, 6, 8),
            $w('bar_ranked', 'Product ranking', ['kind' => 'category', 'measures' => ['net_value'], 'dimension' => 'product', 'period' => 'ytd', 'top' => 15], 6, 9, 6, 9),
            $w('area_100', 'Brand mix over time', ['kind' => 'time', 'measures' => ['net_value'], 'series' => 'brand', 'grain' => 'month', 'period' => 'last_12_months', 'top' => 5], 0, 11, 6, 8),
            $w('sankey', 'Category to brand flow', ['kind' => 'sankey', 'measures' => ['net_value'], 'dimension' => 'category', 'series' => 'brand', 'period' => 'ytd', 'top' => 6], 0, 19, 12, 9),
        ];

        $target = [
            $kpi('Target achievement', ['achievement_pct'], 0, 'mtd', 'gauge'),
            $kpi('Variance to target', ['variance', 'target'], 2, 'mtd', 'variance_card'),
            $kpi('Required run rate', ['net_value', 'month_target'], 4, 'mtd', 'run_rate_card'),
            $kpi('Target', ['target', 'net_value'], 6, 'mtd', 'bullet'),
            $kpi('Year to date vs target', ['achievement_pct'], 8, 'ytd', 'progress'),
            $kpi('Target health', ['achievement_pct'], 10, 'mtd', 'traffic_light'),
            $w('combo_column_line', 'Actual vs target by month', ['kind' => 'time', 'measures' => ['net_value', 'target'], 'grain' => 'month', 'period' => 'ytd'], 0, 3, 8, 8),
            $w('forecast_line', 'Target achievement projection', ['kind' => 'forecast', 'period' => 'ytd'], 8, 3, 4, 8),
            $w('target_table', 'All active targets', ['kind' => 'kpi', 'measures' => ['net_value'], 'period' => 'mtd'], 0, 11, 12, 8),
        ];

        $historical = [
            $w('line_multi', 'Monthly sales: this year vs last year', ['kind' => 'time', 'measures' => ['net_value', 'last_year'], 'grain' => 'month', 'period' => 'last_24_months'], 0, 0, 12, 8),
            $w('calendar_heatmap', 'Daily sales calendar', ['kind' => 'calendar', 'measures' => ['net_value'], 'period' => 'last_12_months'], 0, 8, 12, 6),
            $w('running_total', 'Year-to-date running total', ['kind' => 'time', 'measures' => ['net_value'], 'grain' => 'month', 'period' => 'ytd'], 0, 14, 6, 8),
            $w('moving_average', 'Weekly sales with 4-week moving average', ['kind' => 'time', 'measures' => ['net_value'], 'grain' => 'week', 'period' => 'last_12_months'], 6, 14, 6, 8),
            $w('anomaly_line', 'Daily sales with unusual days marked', ['kind' => 'time', 'measures' => ['net_value'], 'grain' => 'day', 'period' => 'last_month'], 0, 22, 12, 7),
        ];

        $growth = [
            $kpi('Year-on-year growth', ['growth_pct', 'last_year'], 0, 'ytd', 'variance_card'),
            $w('waterfall', 'Sales bridge: last year → this year by customer', ['kind' => 'waterfall', 'dimension' => 'customer', 'period' => 'ytd', 'top' => 10], 2, 0, 10, 8),
            $w('diverging_bar', 'Product growth vs last year', ['kind' => 'category', 'measures' => ['growth_pct'], 'dimension' => 'product', 'period' => 'ytd', 'top' => 15], 0, 8, 6, 9),
            $w('waterfall', 'Contribution to growth by category', ['kind' => 'waterfall', 'dimension' => 'category', 'period' => 'ytd', 'top' => 8], 6, 8, 6, 9),
            $w('key_influencers', 'What explains the change on last year', ['kind' => 'influencers', 'period' => 'ytd'], 0, 17, 6, 10),
            $w('decomp_tree', 'Break down sales', ['kind' => 'decomposition', 'measures' => ['net_value'], 'dimension' => 'region', 'period' => 'ytd'], 6, 17, 6, 10),
            $w('network', 'Who buys what: customers and categories', ['kind' => 'network', 'measures' => ['net_value'], 'dimension' => 'customer', 'series' => 'category', 'period' => 'ytd', 'top' => 15], 0, 27, 12, 10),
        ];

        $regional = [
            $w('column_clustered', 'Sales by region vs last year', ['kind' => 'category', 'measures' => ['net_value', 'last_year'], 'dimension' => 'region', 'period' => 'ytd'], 0, 0, 6, 8),
            $w('radar', 'Regional profile', ['kind' => 'category', 'measures' => ['net_value', 'quantity', 'active_customers'], 'dimension' => 'region', 'period' => 'ytd'], 6, 0, 6, 8),
            $w('heatmap_matrix', 'Region × category', ['kind' => 'matrix', 'measures' => ['net_value'], 'dimension' => 'region', 'series' => 'category', 'period' => 'ytd'], 0, 8, 12, 8),
            $w('map_regional', 'Sales by province', ['kind' => 'geo', 'period' => 'ytd'], 0, 16, 12, 9),
        ];

        $forecasting = [
            $w('forecast_line', 'Forecast with likely range', ['kind' => 'forecast', 'period' => 'ytd'], 0, 0, 8, 9),
            $kpi('Month-end estimate', ['net_value', 'month_target'], 8, 'mtd', 'run_rate_card'),
            $kpi('Achievement so far', ['achievement_pct'], 10, 'mtd', 'gauge'),
            $w('smart_narrative', 'What the numbers say', ['kind' => 'narrative', 'period' => 'mtd'], 8, 3, 4, 6),
            $w('line_smooth', 'Seasonal pattern (24 months)', ['kind' => 'time', 'measures' => ['net_value'], 'grain' => 'month', 'period' => 'last_24_months'], 0, 9, 12, 7),
        ];

        $quality = [
            $w('data_status', 'Imports, mailbox and data quality', ['kind' => 'kpi', 'measures' => ['transactions'], 'period' => 'mtd'], 0, 0, 12, 6),
            $w('table', 'Products with no sales this year', ['kind' => 'table', 'measures' => ['net_value', 'quantity'], 'dimension' => 'product', 'period' => 'ytd', 'sort' => 'asc', 'top' => 50], 0, 6, 12, 9),
        ];

        $management = [
            $kpi('Total sales', ['net_value', 'growth_pct'], 0, 'ytd', 'kpi_sparkline'),
            $kpi('Target achievement', ['achievement_pct'], 2, 'ytd', 'gauge'),
            $kpi('Active customers', ['active_customers'], 4, 'ytd'),
            $kpi('Average selling price', ['avg_selling_price'], 6, 'ytd'),
            $kpi('Discount %', ['discount_rate'], 8, 'ytd'),
            $kpi('Sales lines', ['transactions'], 10, 'ytd'),
            $w('matrix_table', 'Customer × month', ['kind' => 'matrix', 'measures' => ['net_value'], 'dimension' => 'customer', 'series' => 'month', 'period' => 'last_12_months', 'top' => 15], 0, 3, 12, 9),
            $w('smart_narrative', 'Management summary', ['kind' => 'narrative', 'period' => 'mtd'], 0, 12, 12, 5),
        ];

        return [
            'executive' => ['name' => 'Executive Sales Dashboard', 'widgets' => $executive],
            'customer' => ['name' => 'Customer Performance Dashboard', 'widgets' => $customer],
            'product' => ['name' => 'Product Performance Dashboard', 'widgets' => $product],
            'target' => ['name' => 'Sales Target Dashboard', 'widgets' => $target],
            'historical' => ['name' => 'Historical Sales Dashboard', 'widgets' => $historical],
            'growth' => ['name' => 'Sales Growth Dashboard', 'widgets' => $growth],
            'regional' => ['name' => 'Regional Performance Dashboard', 'widgets' => $regional],
            'forecasting' => ['name' => 'Forecasting Dashboard', 'widgets' => $forecasting],
            'quality' => ['name' => 'Data Quality Dashboard', 'widgets' => $quality],
            'management' => ['name' => 'Management Overview Dashboard', 'widgets' => $management],
        ];
    }

    /** Which template a role lands on (§5.1–5.4). */
    public static function forRoles(array $roles): string
    {
        return match (true) {
            (bool) array_intersect($roles, ['organisation administrator', 'executive', 'administrator']) => 'executive',
            in_array('sales manager', $roles, true) => 'target',
            in_array('sales analyst', $roles, true) => 'management',
            in_array('data administrator', $roles, true) => 'quality',
            default => 'executive',
        };
    }

    /** A template's widgets with fresh ids. */
    public static function widgets(string $key): array
    {
        return collect(self::all()[$key]['widgets'] ?? [])->values()->map(fn ($w, $i) => $w + ['id' => 'w' . ($i + 1)])->all();
    }
}
