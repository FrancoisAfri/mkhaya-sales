<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\ForecastService;
use App\Modules\Sales\Services\InsightsService;
use App\Modules\Sales\Services\PerformanceAlerts;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Support\SalesCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Insights: anomalies, product lifecycle, what-if simulation and AI insights (SRS Release 3). */
class InsightsController extends Controller
{
    public function index(Request $request, InsightsService $insights, ForecastService $forecast, SalesAnalytics $analytics): Response
    {
        abort_unless($request->user()->can('dashboards.view'), 403);
        $user = $request->user();
        $last = $analytics->lastSaleDate();
        $year = $last ? ['from' => CarbonImmutable::parse($last)->subYear()->addDay()->toDateString(), 'to' => $last] : [];

        return Inertia::render('Modules/Sales/Insights/Index', [
            'anomalies' => $insights->anomalies($user, (float) PerformanceAlerts::settingsFor(SalesCompany::id())['unusual_movement']['threshold']),
            'lifecycle' => $insights->lifecycle($user),
            'forecast' => $last ? $forecast->monthly([], $user, 12) : null,
            'customers' => $last ? $analytics->grouped($year, $user, ['customer'], 'net_value', 'none', 200)['rows'] : [],
            'lastTwelveMonths' => $last ? $analytics->sum([], $user, CarbonImmutable::parse($year['from']), CarbonImmutable::parse($last)) : 0,
            'canUseAi' => $user->can('ai.use'),
        ]);
    }
}
