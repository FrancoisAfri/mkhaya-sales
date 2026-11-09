<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\ForecastService;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Sales\Services\TargetService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Executive, customer, product and target dashboards; forecast page (SRS BI-001, FCT-001). */
class DashboardController extends Controller
{
    public function __construct(private SalesAnalytics $analytics, private SalesQuery $query) {}

    public function show(Request $request, ?string $view = null): Response
    {
        abort_unless($request->user()->can('dashboards.view'), 403);
        $view ??= 'executive';
        abort_unless(in_array($view, ['executive', 'customers', 'products', 'targets'], true), 404);

        $f = $this->query->filters($request);
        $user = $request->user();
        $kpis = $this->analytics->kpis($f, $user);
        $f += ['from' => $kpis['from'], 'to' => $kpis['to']];
        $targets = app(TargetService::class);

        $data = match ($view) {
            'executive' => [
                'trend' => $this->analytics->trend($f, $user),
                'topCustomers' => $this->analytics->grouped($f, $user, ['customer'], 'net_value', 'previous_year', 10)['rows'],
                'topProducts' => $this->analytics->grouped($f, $user, ['product'], 'net_value', 'previous_year', 10)['rows'],
                'decliningCustomers' => $this->analytics->grouped($f, $user, ['customer'], 'net_value', 'previous_year', 5, 'decline')['rows'],
                'decliningProducts' => $this->analytics->grouped($f, $user, ['product'], 'net_value', 'previous_year', 5, 'decline')['rows'],
                'targetSummary' => $targets->summary($f, $user),
            ],
            'customers' => [
                'ranking' => $this->analytics->grouped($f, $user, ['customer'], 'net_value', 'previous_year', 50)['rows'],
                'groups' => $this->analytics->grouped($f, $user, ['customer_group'], 'net_value', 'previous_year', 20)['rows'],
                'regions' => $this->analytics->grouped($f, $user, ['region'], 'net_value', 'previous_year', 20)['rows'],
                'concentration' => $this->analytics->concentration($f, $user),
                'volume' => $this->analytics->grouped($f, $user, ['customer'], 'quantity', 'none', 10)['rows'],
            ],
            'products' => [
                'ranking' => $this->analytics->grouped($f, $user, ['product'], 'net_value', 'previous_year', 50)['rows'],
                'categories' => $this->analytics->grouped($f, $user, ['category'], 'net_value', 'previous_year', 20)['rows'],
                'brands' => $this->analytics->grouped($f, $user, ['brand'], 'net_value', 'previous_year', 20)['rows'],
                'growth' => $this->analytics->grouped($f, $user, ['product'], 'net_value', 'previous_year', 10, 'growth')['rows'],
                'quantity' => $this->analytics->grouped($f, $user, ['product'], 'quantity', 'none', 10)['rows'],
            ],
            'targets' => ['targets' => $targets->performanceList($f, $user), 'targetSummary' => $targets->summary($f, $user)],
        };

        return Inertia::render('Modules/Sales/Dashboard/Index', [
            'view' => $view,
            'kpis' => $kpis,
            'filters' => $f,
            'options' => $this->query->filterOptions($f ?? []),
        ] + $data);
    }

    public function forecast(Request $request, ForecastService $forecast): Response
    {
        abort_unless($request->user()->can('dashboards.view'), 403);
        $f = $this->query->filters($request);

        // Forecast horizon in months (meeting 2026-11-09): 1, 2, 3, 6, 9, 12, 18 or any custom 1–36.
        $horizon = max(1, min(36, (int) $request->query('months', 3)));
        // Value (rand) or volume (cases) — the client forecasts both.
        $measure = $request->query('measure') === 'cases' ? 'quantity' : 'net_value';
        $user = $request->user();
        $fc = $forecast->monthly($f, $user, $horizon, $measure);

        // The same forecast per category: this month's estimate and next month.
        $since = now()->subMonths(3)->startOfMonth()->toDateString();
        $categories = collect($this->analytics->grouped(array_merge($f, ['from' => $since]), $user, ['category'], $measure, 'none', 8)['rows'])
            ->filter(fn ($r) => $r['category_id'] !== null)
            ->map(function ($r) use ($forecast, $f, $user, $measure) {
                $c = $forecast->monthly(array_merge($f, ['category_id' => (int) $r['category_id']]), $user, 1, $measure);

                return ['category' => $r['category'], 'month_to_date' => $c['months'][0]['actual'] ?? 0, 'month_end' => $c['months'][0]['forecast'] ?? 0,
                    'next' => $c['months'][1]['forecast'] ?? null, 'next_last_year' => $c['months'][1]['last_year'] ?? null, 'growth' => $c['recent_growth']];
            })->values();

        return Inertia::render('Modules/Sales/Forecast/Index', [
            'forecast' => $fc,
            'horizon' => $horizon,
            'measure' => $measure === 'quantity' ? 'cases' : 'value',
            'targets' => $forecast->targetsByMonth(array_merge(array_column($fc['history'], 'month'), array_column($fc['months'], 'month')), $measure),
            'categories' => $categories,
            'products' => collect($this->analytics->grouped(array_merge($f, ['from' => $since]), $user, ['product'], $measure, 'previous_year', 15)['rows']),
            'filters' => $f,
            'options' => $this->query->filterOptions($f ?? []),
        ]);
    }
}
