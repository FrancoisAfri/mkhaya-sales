<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\ReportScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Maps, key influencers, network, decomposition drill by province, and scheduled report emails. */
class Release3Test extends SalesTestCase
{
    private function sale(int $co, int $cust, string $date, float $v): array
    {
        return ['company_id' => $co, 'transaction_date' => $date, 'customer_id' => $cust, 'net_value' => $v, 'gross_value' => $v, 'quantity' => 1,
            'row_key' => md5($co . $cust . $date . $v . microtime()), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
    }

    private function geoCompany(): array
    {
        [$co, $admin] = $this->tenant('Geo Co');
        [$other] = $this->tenant('Other Co');
        $this->as($co->id);
        $jhb = Customer::create(['customer_code' => 'J1', 'customer_name' => 'Joburg Store', 'province' => 'Gauteng', 'city' => 'Johannesburg', 'latitude' => -26.2, 'longitude' => 28.04]);
        $cpt = Customer::create(['customer_code' => 'C1', 'customer_name' => 'Cape Store', 'province' => 'Western Cape']);
        $this->as($other->id);
        $x = Customer::create(['customer_code' => 'X1', 'customer_name' => 'Elsewhere', 'province' => 'Gauteng', 'latitude' => -26, 'longitude' => 28]);
        DB::table('sales_records')->insert([
            $this->sale($co->id, $jhb->id, '2026-11-02', 1000), $this->sale($co->id, $jhb->id, '2025-11-02', 2000),
            $this->sale($co->id, $cpt->id, '2026-11-03', 500), $this->sale($co->id, $cpt->id, '2025-11-03', 400),
            $this->sale($other->id, $x->id, '2026-11-02', 99999),
        ]);

        return [$co, $admin];
    }

    public function test_maps_influencers_network_and_province_filters(): void
    {
        [, $admin] = $this->geoCompany();
        $f = ['from' => '2026-11-01', 'to' => '2026-11-30'];
        $q = fn (array $config, array $filters = []) => $this->actingAs($admin)->postJson('/sales/bi/query', ['config' => $config, 'filters' => $filters + $f])->assertOk()->json();

        $geo = $q(['kind' => 'geo']);
        $this->assertCount(1, $geo['points'], 'Only geocoded customers become points; the other company never shows.');
        $this->assertEquals(-50, $geo['points'][0]['growth']);
        $this->assertSame(['customers' => 2, 'located' => 1], ['customers' => $geo['customers'], 'located' => $geo['located']]);
        $gauteng = collect($geo['regions'])->firstWhere('key', 'Gauteng');
        $this->assertEquals(1000, $gauteng['value']);
        $this->assertNotNull(collect($geo['regions'])->firstWhere('key', 'Western Cape')['lat'], 'A province with no geocoded customer still sits at its centre.');

        $ki = $q(['kind' => 'influencers']);
        $this->assertEquals(-900, $ki['overall']['change']);
        $this->assertSame('Gauteng', $ki['decline'][0]['name']);
        $this->assertSame('Western Cape', collect($ki['growth'])->firstWhere('dimension', 'province')['name']);

        $net = $q(['kind' => 'network', 'dimension' => 'customer', 'series' => 'province']);
        $this->assertCount(4, $net['nodes']);
        $this->assertCount(2, $net['links']);

        $tree = $q(['kind' => 'category', 'dimension' => 'customer'], ['province' => 'Western Cape']);
        $this->assertSame(['Cape Store'], $tree['categories'], 'The province filter drives the decomposition tree.');
    }

    public function test_report_schedules_are_due_at_their_time_and_mail_the_file(): void
    {
        Mail::fake();
        [$co, $admin] = $this->geoCompany();
        $this->actingAs($admin)->post('/sales/reports/schedules', ['name' => 'Monthly', 'source' => 'standard', 'source_ref' => 'by_customer', 'format' => 'xlsx',
            'period' => 'last_month', 'frequency' => 'monthly', 'month_day' => 1, 'send_time' => '07:00', 'recipients' => ['boss@example.com']])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/sales/reports/schedules', ['name' => 'Bad', 'source' => 'standard', 'source_ref' => 'by_customer', 'format' => 'xlsx',
            'period' => 'last_month', 'frequency' => 'weekly', 'send_time' => '07:00', 'recipients' => ['not-an-email']])->assertSessionHasErrors(['weekday', 'recipients.0']);

        $s = DB::table('report_schedules')->where('company_id', $co->id)->first();
        $this->assertTrue(ReportScheduler::due($s, CarbonImmutable::parse('2026-12-01 07:00', 'Africa/Johannesburg')));
        $this->assertFalse(ReportScheduler::due($s, CarbonImmutable::parse('2026-12-01 07:01', 'Africa/Johannesburg')));
        $this->assertFalse(ReportScheduler::due($s, CarbonImmutable::parse('2026-12-02 07:00', 'Africa/Johannesburg')));

        $this->actingAs($admin)->post("/sales/reports/schedules/{$s->id}/send")->assertSessionHasNoErrors();
        Mail::assertSent(\App\Modules\Sales\Mail\ScheduledReportMail::class, fn ($m) => $m->hasTo('boss@example.com') && str_ends_with($m->file['name'], '.xlsx'));
        $this->assertSame('sent', DB::table('report_schedules')->where('id', $s->id)->value('last_status'));

        // Another company cannot see or send it.
        $outsider = $this->tenant('Outsider')[1];
        $this->actingAs($outsider)->post("/sales/reports/schedules/{$s->id}/send")->assertNotFound();
    }
}
