<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Support\SalesCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales beside stock, promotions and prices (gap 7):
 * - stock cover: cases on hand ÷ average cases sold a day over the last 28 days;
 * - promotion uplift: sales during a promotion against the same number of days before it;
 * - price realisation: the price achieved per case against the price list.
 */
class TradeController extends Controller
{
    public function __construct(private SalesQuery $query, private SalesAnalytics $analytics) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('sales.view'), 403);
        $user = $request->user();
        $c = SalesCompany::id();
        $f = $this->query->filters($request);
        $last = CarbonImmutable::parse($this->analytics->lastSaleDate() ?? now());

        // ── Stock cover ──
        $snapshot = DB::table('stock_levels')->where('company_id', $c)->max('snapshot_date');
        $stock = collect();
        if ($snapshot) {
            $onHand = DB::table('stock_levels')->where('company_id', $c)->where('snapshot_date', $snapshot)
                ->when($f['product_id'] ?? null, fn ($q, $p) => $q->where('product_id', $p))
                ->groupBy('product_id')->selectRaw('product_id, sum(cases_on_hand) as cases')->pluck('cases', 'product_id');
            $sold = $this->query->base(['from' => $last->subDays(27)->toDateString(), 'to' => $last->toDateString()] + $f, $user)
                ->whereIn('s.product_id', $onHand->keys())->groupBy('s.product_id')->selectRaw('s.product_id, sum(s.quantity) as cases')->pluck('cases', 'product_id');
            $names = DB::table('products')->whereIn('id', $onHand->keys())->pluck('product_name', 'id');
            $stock = $onHand->map(function ($cases, $pid) use ($sold, $names) {
                $daily = (float) ($sold[$pid] ?? 0) / 28;

                return ['product' => $names[$pid] ?? '—', 'on_hand' => round((float) $cases), 'daily' => round($daily, 1),
                    'cover_days' => $daily > 0 ? round((float) $cases / $daily, 1) : null];
            })->sortBy(fn ($r) => $r['cover_days'] ?? INF)->values();
        }

        // ── Promotions: uplift against the same number of days before ──
        $promos = DB::table('promotions as p')->where('p.company_id', $c)->where('p.start_date', '<=', $last->toDateString())
            ->where('p.end_date', '>=', $last->subMonths(6)->toDateString())->orderByDesc('p.start_date')->limit(30)->get()
            ->map(function ($p) use ($f, $user, $last) {
                $scope = array_filter(['product_id' => $p->product_id, 'category_id' => $p->category_id, 'customer_id' => $p->customer_id, 'customer_group_id' => $p->customer_group_id]) + $f;
                $s = CarbonImmutable::parse($p->start_date);
                $e = CarbonImmutable::parse($p->end_date)->min($last);
                $days = (int) $s->diffInDays($e) + 1;
                $sum = fn ($a, $b) => (array) $this->query->base(['from' => $a->toDateString(), 'to' => $b->toDateString()] + $scope, $user)
                    ->selectRaw('coalesce(sum(s.quantity),0) as cases, coalesce(sum(s.net_value),0) as value')->first();
                $during = $sum($s, $e);
                $before = $sum($s->subDays($days), $s->subDay());
                $uplift = (float) $before['cases'] > 0 ? round(((float) $during['cases'] - (float) $before['cases']) / (float) $before['cases'] * 100, 1) : null;

                return ['name' => $p->name, 'start' => $p->start_date, 'end' => $p->end_date, 'running' => $p->end_date >= $last->toDateString(),
                    'covers' => collect([$p->product_id ? DB::table('products')->where('id', $p->product_id)->value('product_name') : null,
                        $p->category_id ? DB::table('product_categories')->where('id', $p->category_id)->value('name') : null,
                        $p->customer_id ? DB::table('customers')->where('id', $p->customer_id)->value('customer_name') : null,
                        $p->customer_group_id ? DB::table('customer_groups')->where('id', $p->customer_group_id)->value('name') : null])->filter()->implode(' · '),
                    'discount_pct' => $p->discount_pct, 'mechanic' => $p->mechanic, 'days' => $days,
                    'cases' => round((float) $during['cases']), 'cases_before' => round((float) $before['cases']), 'value' => round((float) $during['value'], 2), 'uplift' => $uplift];
            });

        // ── Price realisation, last 30 days ──
        $prices = DB::table('price_lists')->where('company_id', $c)->where('valid_from', '<=', $last->toDateString())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $last->toDateString()))
            ->groupBy('product_id')->selectRaw('product_id, avg(price_per_case) as list')->pluck('list', 'product_id');
        $realised = collect();
        if ($prices->isNotEmpty()) {
            $actual = $this->query->base(['from' => $last->subDays(29)->toDateString(), 'to' => $last->toDateString()] + $f, $user)
                ->whereIn('s.product_id', $prices->keys())->groupBy('s.product_id')
                ->selectRaw('s.product_id, sum(s.net_value) as value, sum(s.quantity) as cases')->get()->keyBy('product_id');
            $names = DB::table('products')->whereIn('id', $prices->keys())->pluck('product_name', 'id');
            $realised = $prices->map(function ($list, $pid) use ($actual, $names) {
                $a = $actual[$pid] ?? null;
                $achieved = $a && (float) $a->cases > 0 ? (float) $a->value / (float) $a->cases : null;

                return ['product' => $names[$pid] ?? '—', 'list' => round((float) $list, 2), 'achieved' => $achieved === null ? null : round($achieved, 2),
                    'realisation' => $achieved === null ? null : round($achieved / (float) $list * 100, 1), 'cases' => $a ? round((float) $a->cases) : 0];
            })->sortBy(fn ($r) => $r['realisation'] ?? INF)->values();
        }

        return Inertia::render('Modules/Sales/Trade/Index', [
            'snapshot' => $snapshot, 'stock' => $stock, 'promotions' => $promos, 'prices' => $realised,
            'asOf' => $last->toDateString(), 'filters' => $f, 'options' => $this->query->filterOptions($f),
        ]);
    }
}
