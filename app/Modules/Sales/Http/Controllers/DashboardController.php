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

        return Inertia::render('Modules/Sales/Forecast/Index', [
            'forecast' => $forecast->monthly($f, $request->user()),
            'products' => collect($this->analytics->grouped(array_merge($f, ['from' => now()->subMonths(3)->startOfMonth()->toDateString()]), $request->user(), ['product'], 'net_value', 'previous_year', 15)['rows']),
            'filters' => $f,
            'options' => $this->query->filterOptions($f ?? []),
        ]);
    }
}
