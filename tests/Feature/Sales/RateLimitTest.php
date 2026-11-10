<?php

namespace Tests\Feature\Sales;

/** System test 2026-11-10: opening a dashboard must not use up the export allowance. */
class RateLimitTest extends SalesTestCase
{
    public function test_each_throttled_route_has_its_own_counter(): void
    {
        [, $admin] = $this->tenant('Throttle Co');
        for ($i = 0; $i < 40; $i++) {   // a dashboard with ~40 visuals
            $this->actingAs($admin)->postJson('/sales/bi/query', ['config' => ['kind' => 'kpi', 'measures' => ['net_value']]])->assertOk();
        }
        $this->actingAs($admin)->get('/sales/reports/export/csv?report=monthly')->assertOk();
    }
}
