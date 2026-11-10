<?php

namespace App\Modules\Sales\Services\BI;

use App\Core\AI\Services\AIChatProviderChain;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Support\SalesCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Ask a question, get a chart" (gap 2). The question becomes a visual configuration — the same
 * JSON a person builds in the dashboard editor — and the measure engine draws it. The AI only
 * chooses settings from fixed lists; it never writes SQL and never sees sales rows, and the result
 * is validated by BIQueryService like any other visual. A keyword parser answers when the AI is off.
 */
class AskService
{
    public const VISUALS = ['kpi_card', 'line', 'column_clustered', 'bar_ranked', 'donut', 'table', 'diverging_bar', 'heatmap_matrix', 'map_regional', 'forecast_line'];

    private const KIND = ['kpi_card' => 'kpi', 'line' => 'time', 'column_clustered' => 'category', 'bar_ranked' => 'category', 'donut' => 'category',
        'table' => 'table', 'diverging_bar' => 'category', 'heatmap_matrix' => 'matrix', 'map_regional' => 'geo', 'forecast_line' => 'forecast'];

    public function __construct(private AIChatProviderChain $ai, private BIQueryService $bi) {}

    /** @return array{type: string, title: string, config: array, explanation: string, source: string} */
    public function ask(string $question): array
    {
        $question = Str::limit(trim($question), 300, '');
        $plan = $this->viaAi($question) ?? $this->viaKeywords($question);

        return $this->clean($plan, $question);
    }

    private function viaAi(string $question): ?array
    {
        $measures = collect($this->bi->catalogue())->map(fn ($m, $k) => "{$k} = {$m['label']}")->implode('; ');
        $dims = collect(SalesAnalytics::DIMENSIONS)->map(fn ($l, $k) => "{$k} = {$l}")->implode('; ');
        $names = $this->names();
        $system = 'You turn a sales question into a chart specification. Reply with ONE JSON object only, no prose. Keys: '
            . 'visual (one of ' . implode(', ', self::VISUALS) . '), title (short), measures (array of measure keys), '
            . 'dimension (a dimension key, for category/table/matrix/map visuals), series (a second dimension key for heatmap_matrix, else null), '
            . 'grain (day|week|month|quarter|year, for line), period (mtd|last_month|qtd|ytd|last_12_months|last_24_months|all), '
            . 'top (number of items, 0 = all), filters (object of name filters: customer, product, category, brand, region, customer_group, province — values are names as the user wrote them), '
            . "explanation (one sentence saying what the chart shows). Measures: {$measures}. Dimensions: {$dims}. "
            . 'Volume is always in cases (measure quantity). "Growth" or "vs last year" means growth_pct or adding last_year. '
            . "Known names — categories: {$names['categories']}; regions: {$names['regions']}; customer groups: {$names['groups']}.";
        try {
            $text = $this->ai->text($system, $question, ['module' => 'sales_ask', 'max_tokens' => 400, 'temperature' => 0, 'timeout' => 6, 'deadline' => 10]);
        } catch (\Throwable) {
            return null;
        }
        if (! $text || ! preg_match('/\{.*\}/s', $text, $m)) {
            return null;
        }
        $json = json_decode($m[0], true);

        return is_array($json) ? $json + ['_source' => 'ai'] : null;
    }

    /** No AI: understand the common shapes of a sales question from its words. */
    private function viaKeywords(string $q): array
    {
        $l = mb_strtolower($q);
        $has = fn (...$w) => collect($w)->contains(fn ($x) => str_contains($l, $x));
        $measure = $has('case', 'volume', 'quantity', 'units') ? 'quantity' : ($has('customers buying', 'active customer') ? 'active_customers' : 'net_value');
        $dimension = collect(['customer_group' => ['group', 'chain', 'retailer'], 'customer' => ['customer', 'store', 'account'], 'product' => ['product', 'sku', 'item'],
            'category' => ['category', 'categories'], 'brand' => ['brand'], 'region' => ['region'], 'province' => ['province'], 'salesperson' => ['rep', 'salesperson'],
        ])->search(fn ($words) => $has(...$words));
        $period = match (true) {
            $has('last month') => 'last_month', $has('this month', 'mtd', 'month to date') => 'mtd', $has('quarter') => 'qtd',
            $has('last 12', 'past year', 'last year') && ! $has('vs last year', 'versus last year', 'compared') => 'last_12_months',
            default => 'ytd',
        };
        // "Growth / decline" ranks by the change; "… vs last year" shows the figure beside last year.
        $growth = $has('growth', 'decline', 'declining', 'drop', 'falling');
        $vsLy = $has('vs last year', 'versus last year', 'compared to last year', 'than last year');
        $trend = $has('trend', 'by month', 'monthly', 'over time', 'per month', 'by week', 'weekly');
        preg_match('/top\s+(\d+)/', $l, $top);
        $visual = match (true) {
            $has('forecast', 'predict') => 'forecast_line',
            $has('map', 'where') => 'map_regional',
            $trend => 'line',
            $growth && $dimension => 'diverging_bar',
            $vsLy && $dimension => 'column_clustered',
            $dimension && $has('share', 'split', 'mix') => 'donut',
            (bool) $dimension => 'bar_ranked',
            default => 'kpi_card',
        };

        return ['visual' => $visual, 'title' => Str::ucfirst($q), 'measures' => $visual === 'diverging_bar' ? ['growth_pct'] : ($vsLy ? [$measure, ['net_value' => 'last_year', 'quantity' => 'last_year_cases'][$measure] ?? 'growth_pct'] : ($growth ? [$measure, 'growth_pct'] : [$measure])),
            'dimension' => $dimension ?: null, 'grain' => $has('by week', 'weekly') ? 'week' : 'month', 'period' => $period, 'top' => (int) ($top[1] ?? 10),
            'filters' => $this->namesIn($l), 'explanation' => 'Understood from the words of the question (AI assistant not used).', '_source' => 'keywords'];
    }

    /** Validate the plan against the real lists and turn names into ids, so only safe settings reach the engine. */
    private function clean(array $p, string $question): array
    {
        $visual = in_array($p['visual'] ?? null, self::VISUALS, true) ? $p['visual'] : 'bar_ranked';
        $catalogue = array_keys($this->bi->catalogue());
        $measures = array_values(array_intersect((array) ($p['measures'] ?? []), $catalogue)) ?: ['net_value'];
        $dims = array_keys(SalesAnalytics::DIMENSIONS);
        $dimension = in_array($p['dimension'] ?? null, $dims, true) && ! in_array($p['dimension'], BIQueryService::GRAINS, true) ? $p['dimension'] : 'customer';
        $config = ['kind' => self::KIND[$visual], 'measures' => $measures, 'dimension' => $dimension,
            'period' => in_array($p['period'] ?? null, BIQueryService::PERIODS, true) ? $p['period'] : 'ytd',
            'grain' => in_array($p['grain'] ?? null, BIQueryService::GRAINS, true) ? $p['grain'] : 'month',
            'top' => max(0, min(50, (int) ($p['top'] ?? 10))), 'filters' => $this->resolveFilters((array) ($p['filters'] ?? []))];
        if ($visual === 'heatmap_matrix') {
            $config['series'] = in_array($p['series'] ?? null, $dims, true) ? $p['series'] : 'month';
        }
        if ($visual === 'line' && count($measures) === 1 && $measures[0] === 'net_value') {
            $config['measures'] = ['net_value', 'last_year'];
        }
        // "… vs last year" always shows last year beside this year (value) or the growth (cases and others).
        if (preg_match('/vs\.? last year|versus last year|compared (to|with) last year|than last year/i', $question)
            && ! array_intersect($config['measures'], ['last_year', 'growth_pct', 'last_year_cases', 'cases_growth_pct']) && $config['kind'] !== 'kpi') {
            $config['measures'][] = ['net_value' => 'last_year', 'quantity' => 'last_year_cases'][$config['measures'][0]] ?? 'growth_pct';
            if ($visual === 'bar_ranked') {
                $visual = 'column_clustered';
            }
        }
        if ($visual === 'kpi_card' && count($config['measures']) === 1) {
            $config['measures'][] = 'growth_pct';
        }
        // A question in cases is compared in cases: last year's cases and cases growth, never rand.
        if (in_array('quantity', $config['measures'], true)) {
            $config['measures'] = array_values(array_unique(array_map(fn ($m) => ['last_year' => 'last_year_cases', 'growth_pct' => 'cases_growth_pct'][$m] ?? $m, $config['measures'])));
        }

        return ['type' => $visual, 'title' => Str::limit((string) ($p['title'] ?? $question), 120, ''), 'config' => $config,
            'explanation' => Str::limit((string) ($p['explanation'] ?? ''), 300, ''), 'source' => $p['_source'] ?? 'ai',
            'filters_used' => collect($config['filters'])->map(fn ($v, $k) => $this->label($k, $v))->values()->all()];
    }

    /** Name filters → ids (case-insensitive, partial match), limited to this company. */
    private function resolveFilters(array $names): array
    {
        $c = SalesCompany::id();
        $map = ['customer' => ['customers', 'customer_name', 'customer_id'], 'product' => ['products', 'product_name', 'product_id'],
            'category' => ['product_categories', 'name', 'category_id'], 'brand' => ['product_brands', 'name', 'brand_id'],
            'region' => ['regions', 'name', 'region_id'], 'customer_group' => ['customer_groups', 'name', 'customer_group_id']];
        $out = [];
        foreach ($names as $k => $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            if ($k === 'province') {
                $out['province'] = DB::table('customers')->where('company_id', $c)->whereRaw('lower(province) = ?', [mb_strtolower(trim($name))])->value('province') ?? null;
                continue;
            }
            if (! isset($map[$k])) {
                continue;
            }
            [$table, $col, $key] = $map[$k];
            $id = DB::table($table)->where('company_id', $c)->whereRaw("lower({$col}) = ?", [mb_strtolower(trim($name))])->value('id')
                ?? DB::table($table)->where('company_id', $c)->whereRaw("lower({$col}) like ?", ['%' . mb_strtolower(trim($name)) . '%'])->orderByRaw("length({$col})")->value('id');
            if ($id) {
                $out[$key] = (int) $id;
            }
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    /** Category, region, group and province names written in the question (for the keyword parser). */
    private function namesIn(string $l): array
    {
        $c = SalesCompany::id();
        $found = [];
        foreach (['category' => ['product_categories', 'name'], 'region' => ['regions', 'name'], 'customer_group' => ['customer_groups', 'name'], 'brand' => ['product_brands', 'name']] as $k => [$t, $col]) {
            $hit = DB::table($t)->where('company_id', $c)->pluck($col)->sortByDesc(fn ($n) => mb_strlen($n))->first(fn ($n) => mb_strlen($n) > 2 && str_contains($l, mb_strtolower($n)));
            if ($hit) {
                $found[$k] = $hit;
            }
        }
        $province = DB::table('customers')->where('company_id', $c)->whereNotNull('province')->distinct()->pluck('province')->first(fn ($n) => str_contains($l, mb_strtolower($n)));
        if ($province) {
            $found['province'] = $province;
        }

        return $found;
    }

    private function names(): array
    {
        $c = SalesCompany::id();
        $list = fn ($t) => DB::table($t)->where('company_id', $c)->orderBy('name')->limit(40)->pluck('name')->implode(', ');

        return ['categories' => $list('product_categories'), 'regions' => $list('regions'), 'groups' => $list('customer_groups')];
    }

    private function label(string $key, $value): string
    {
        $c = SalesCompany::id();
        $name = match ($key) {
            'customer_id' => DB::table('customers')->where('company_id', $c)->where('id', $value)->value('customer_name'),
            'product_id' => DB::table('products')->where('company_id', $c)->where('id', $value)->value('product_name'),
            'category_id' => DB::table('product_categories')->where('company_id', $c)->where('id', $value)->value('name'),
            'brand_id' => DB::table('product_brands')->where('company_id', $c)->where('id', $value)->value('name'),
            'region_id' => DB::table('regions')->where('company_id', $c)->where('id', $value)->value('name'),
            'customer_group_id' => DB::table('customer_groups')->where('company_id', $c)->where('id', $value)->value('name'),
            default => (string) $value,
        };

        return (string) $name;
    }
}
