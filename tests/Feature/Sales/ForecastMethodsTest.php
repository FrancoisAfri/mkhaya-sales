<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Services\ForecastService;

/** Gap 11: the statistical forecast learns a seasonal pattern and is compared with the plain method. */
class ForecastMethodsTest extends SalesTestCase
{
    public function test_holt_winters_learns_a_season_with_a_trend(): void
    {
        // 36 months: a 12-month season (December peak) on a rising trend.
        $season = [90, 85, 95, 100, 105, 100, 95, 100, 105, 110, 120, 160];
        $y = [];
        for ($t = 0; $t < 36; $t++) {
            $y[] = 1000 + 5 * $t + 10 * $season[$t % 12];
        }
        $fit = app(ForecastService::class)->holtWinters($y, 12);
        $this->assertNotNull($fit);
        foreach ($fit['forecast'] as $k => $f) {
            $expected = 1000 + 5 * (36 + $k) + 10 * $season[(36 + $k) % 12];
            $this->assertEqualsWithDelta($expected, $f, $expected * 0.03, "Month +{$k} within 3%.");
        }
        $this->assertNull(app(ForecastService::class)->holtWinters(array_slice($y, 0, 12), 3), 'Needs at least 13 months.');
    }

    public function test_the_page_offers_both_methods(): void
    {
        [, $admin] = $this->tenant('Method Co');
        $props = $this->actingAs($admin)->get('/sales/forecast?method=ets')->assertOk()->viewData('page')['props'];
        $this->assertSame('ets', $props['methodChoice']);
        $this->assertArrayHasKey('plain', $props['forecast']['methods']);
        $this->assertArrayHasKey('ets', $props['forecast']['methods']);
    }
}
