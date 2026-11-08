<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Models\Region;
use App\Modules\Sales\Models\Target;
use App\Modules\Sales\Notifications\SalesAlert;
use App\Modules\Sales\Services\PerformanceAlerts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Release 2 — target imports (§9.1) and performance alerts (§17). */
class TargetImportAndAlertsTest extends SalesTestCase
{
    public function test_targets_are_imported_as_drafts_with_their_dimensions(): void
    {
        [$co, $admin] = $this->tenant('TImport Co');
        $this->as($co->id);
        $gp = Region::create(['name' => 'Gauteng']);
        Customer::create(['customer_code' => 'C1', 'customer_name' => 'Spar']);

        $tmp = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($tmp, "Target name,Measure,Period,Start,End,Target value,Region,Customer\n"
            . "GP December,net_value,monthly,2026-12-01,2026-12-31,500000,Gauteng,\n"
            . "Spar December,quantity,monthly,2026-12-01,2026-12-31,1200,,C1\n"
            . "Bad one,net_value,monthly,2026-12-01,2026-12-31,100,Atlantis,\n");
        $this->actingAs($admin)->post('/sales/imports', ['target' => 'targets', 'file' => new UploadedFile($tmp, 'targets.csv', 'text/csv', null, true)]);
        $batch = ImportBatch::withoutGlobalScopes()->where('company_id', $co->id)->latest('id')->firstOrFail();

        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(1, $batch->invalid_rows, 'An unknown region must be reported.');
        $this->actingAs($admin)->post("/sales/imports/{$batch->uuid}/confirm");

        $gpTarget = Target::where('name', 'GP December')->firstOrFail();
        $this->assertSame('draft', $gpTarget->status);
        $this->assertSame($gp->id, $gpTarget->dimensions['region_id']);
        $this->assertSame('quantity', Target::where('name', 'Spar December')->value('metric'));
    }

    public function test_alerts_fire_once_for_an_achieved_target_and_respect_settings(): void
    {
        Notification::fake();
        Cache::flush();
        [$co, $admin] = $this->tenant('Alerts Co');
        $this->as($co->id);
        $c = Customer::create(['customer_code' => 'C1', 'customer_name' => 'Spar']);
        DB::table('sales_records')->insert(['company_id' => $co->id, 'transaction_date' => now()->toDateString(), 'customer_id' => $c->id,
            'net_value' => 5000, 'gross_value' => 5000, 'row_key' => 'k1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Target::create(['name' => 'Easy', 'metric' => 'net_value', 'method' => 'fixed', 'period_type' => 'monthly', 'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(), 'target_value' => 1000, 'status' => 'approved', 'created_by' => $admin->id]);

        $raised = app(PerformanceAlerts::class)->run($co->id);
        $this->assertContains('Target achieved: Easy', $raised);
        $this->assertNotContains('Target achieved: Easy', app(PerformanceAlerts::class)->run($co->id), 'The same issue must not repeat.');
        Notification::assertSentTo($admin, SalesAlert::class);

        $this->actingAs($admin)->post('/sales/targets/alerts', ['target_achieved' => ['enabled' => false], 'target_at_risk' => ['enabled' => true, 'threshold' => 80],
            'unusual_movement' => ['enabled' => false, 'threshold' => 50], 'run_time' => '06:30'])->assertSessionHasNoErrors();
        $this->assertSame('06:30', PerformanceAlerts::settingsFor($co->id)['run_time']);
        $this->assertFalse(PerformanceAlerts::settingsFor($co->id)['target_achieved']['enabled']);
        $this->actingAs($admin)->get('/sales/targets/alerts')->assertOk();
    }
}
