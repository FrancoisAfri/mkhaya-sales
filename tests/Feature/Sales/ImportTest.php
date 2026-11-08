<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Models\Product;
use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Models\SalesRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Phases 3–4 — SRS IMP-001, DATA-001, SAL-001, HIS-001 and acceptance scenarios 1, 3–9. */
class ImportTest extends SalesTestCase
{
    private function csv(array $rows, string $name = 'report.csv'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        $fh = fopen($tmp, 'w');
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);

        return new UploadedFile($tmp, $name, 'text/csv', null, true);
    }

    private function upload($user, string $target, UploadedFile $file, array $extra = []): ImportBatch
    {
        $this->actingAs($user)->post('/sales/imports', ['target' => $target, 'file' => $file] + $extra)->assertRedirect();

        return ImportBatch::withoutGlobalScopes()->where('company_id', $user->company_id)->latest('id')->firstOrFail();
    }

    /** A Dr. Oetker-style layout: own headings, extra columns the system has never heard of. */
    private function layout(int $companyId, string $mode = 'append'): ReportLayout
    {
        $this->as($companyId);

        return ReportLayout::create([
            'name' => "Daily sales {$mode}", 'target' => 'sales', 'header_row' => 1, 'ingestion_mode' => $mode,
            'unknown_masters' => 'create', 'keep_unmapped' => true,
            'mapping' => [
                ['source' => 'Inv Date', 'field' => 'transaction_date'],
                ['source' => 'Acc No', 'field' => 'customer_code'],
                ['source' => 'Acc Name', 'field' => 'customer_name'],
                ['source' => 'Material', 'field' => 'product_code'],
                ['source' => 'Material Desc', 'field' => 'product_name'],
                ['source' => 'Cases', 'field' => 'quantity'],
                ['source' => 'Nett Value', 'field' => 'net_value'],
                ['source' => 'Promo Flag', 'field' => 'attr:promo'],
            ],
        ]);
    }

    private function sales(array $lines): UploadedFile
    {
        return $this->csv(array_merge([['Inv Date', 'Acc No', 'Acc Name', 'Material', 'Material Desc', 'Cases', 'Nett Value', 'Promo Flag', 'Truck Reg']], $lines));
    }

    public function test_a_daily_report_with_its_own_columns_loads_and_a_resent_report_is_not_double_counted(): void
    {
        [$co, $admin] = $this->tenant('Daily Co');
        $layout = $this->layout($co->id);
        $file = fn () => $this->sales([
            ['01/11/2026', 'C1', 'Spar Rosebank', 'P1', 'Pizza base', '10', 'R 1,000.00', 'Y', 'ND123'],
            ['01/11/2026', 'C2', 'Checkers Fourways', 'P1', 'Pizza base', '5', '500', 'N', 'ND124'],
            ['02/11/2026', 'C1', 'Spar Rosebank', 'P2', 'Custard 1L', '2', '150.50', '', 'ND125'],
        ]);

        $batch = $this->upload($admin, 'sales', $file(), ['layout_id' => $layout->id]);
        $this->assertSame('staged', $batch->status, (string) $batch->error);
        $this->assertSame(3, $batch->total_rows);
        $this->assertEquals(1650.5, $batch->totals['net_value']);
        $this->assertSame('2026-11-01', $batch->period_from->toDateString());
        $this->assertSame(0, SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->count(), 'Nothing may be saved before confirmation.');

        $this->actingAs($admin)->post("/sales/imports/{$batch->uuid}/confirm")->assertRedirect();
        $this->assertSame('committed', $batch->fresh()->status);
        $this->assertSame(3, SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->count());

        // Unknown customers/products were created and flagged; extra columns were kept.
        $this->assertTrue((bool) Customer::withoutGlobalScopes()->where('company_id', $co->id)->where('customer_code', 'C2')->value('needs_review'));
        $rec = SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->where('net_value', 1000)->first();
        $this->assertSame(['promo' => 'Y', 'truck_reg' => 'ND123'], $rec->attributes);

        // The same report arrives again: flagged, every row a duplicate, nothing added.
        $again = $this->upload($admin, 'sales', $file(), ['layout_id' => $layout->id]);
        $this->assertNotNull($again->previous_batch_id);
        $this->assertSame(3, $again->duplicate_rows);
        $this->actingAs($admin)->post("/sales/imports/{$again->uuid}/confirm");
        $this->assertSame(3, SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->where('status', 'active')->count());
        $this->assertEquals(1650.5, (float) SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->where('status', 'active')->sum('net_value'));
    }

    public function test_a_cumulative_snapshot_replaces_the_period_instead_of_adding_up(): void
    {
        [$co, $admin] = $this->tenant('Snapshot Co');
        $layout = $this->layout($co->id, 'replace_period');

        $first = $this->upload($admin, 'sales', $this->sales([['01/11/2026', 'C1', 'A', 'P1', 'X', '10', '1000', '', '']]), ['layout_id' => $layout->id]);
        $this->actingAs($admin)->post("/sales/imports/{$first->uuid}/confirm");
        // Next day the month-to-date report restates the 1st and adds the 2nd.
        $second = $this->upload($admin, 'sales', $this->sales([
            ['01/11/2026', 'C1', 'A', 'P1', 'X', '12', '1200', '', ''],
            ['02/11/2026', 'C1', 'A', 'P1', 'X', '3', '300', '', ''],
        ]), ['layout_id' => $layout->id]);
        $this->actingAs($admin)->post("/sales/imports/{$second->uuid}/confirm");

        $live = SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->where('status', 'active');
        $this->assertEquals(1500, (float) $live->sum('net_value'));
        $this->assertSame(1, $second->fresh()->replaced_rows);
    }

    public function test_daily_totals_mode_updates_a_resent_day(): void
    {
        [$co, $admin] = $this->tenant('Upsert Co');
        $layout = $this->layout($co->id, 'upsert_daily');
        $b1 = $this->upload($admin, 'sales', $this->sales([['01/11/2026', 'C1', 'A', 'P1', 'X', '10', '1000', '', '']]), ['layout_id' => $layout->id]);
        $this->actingAs($admin)->post("/sales/imports/{$b1->uuid}/confirm");
        $b2 = $this->upload($admin, 'sales', $this->sales([['01/11/2026', 'C1', 'A', 'P1', 'X', '11', '1100', '', '']]), ['layout_id' => $layout->id]);
        $this->actingAs($admin)->post("/sales/imports/{$b2->uuid}/confirm");

        $this->assertSame(1, $b2->fresh()->updated_rows);
        $this->assertEquals(1100, (float) SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->where('status', 'active')->sum('net_value'));
    }

    public function test_bad_rows_are_reported_and_a_fake_spreadsheet_is_refused(): void
    {
        [$co, $admin] = $this->tenant('Invalid Co');
        $layout = $this->layout($co->id);
        $batch = $this->upload($admin, 'sales', $this->sales([
            ['not a date', 'C1', 'A', 'P1', 'X', '1', '10', '', ''],
            ['01/11/2026', '', '', 'P1', 'X', '1', 'abc', '', ''],
            ['01/11/2026', 'C1', 'A', 'P1', 'X', '1', '10', '', ''],
        ]), ['layout_id' => $layout->id]);
        $this->assertSame(2, $batch->invalid_rows);
        $this->actingAs($admin)->get("/sales/imports/{$batch->uuid}/errors")->assertOk();

        $fake = UploadedFile::fake()->createWithContent('report.xlsx', 'this is not a spreadsheet');
        $this->actingAs($admin)->post('/sales/imports', ['target' => 'sales', 'file' => $fake])->assertSessionHasErrors('file');
    }

    public function test_product_import_respects_the_chosen_update_rule(): void
    {
        [$co, $admin] = $this->tenant('Products Co');
        $file = fn () => $this->csv([['Product code', 'Product name', 'Category', 'Brand'], ['P1', 'Pizza base', 'Baking', 'Dr Oetker'], ['P2', 'Custard', 'Desserts', 'Moirs']]);

        $b = $this->upload($admin, 'products', $file());
        $this->assertSame(2, $b->valid_rows);
        $this->actingAs($admin)->post("/sales/imports/{$b->uuid}/confirm");
        $this->assertSame(2, Product::withoutGlobalScopes()->where('company_id', $co->id)->count());

        $insertOnly = $this->upload($admin, 'products', $file(), ['mode' => 'insert']);
        $this->assertSame(2, $insertOnly->duplicate_rows);
    }

    public function test_a_correction_needs_a_second_person(): void
    {
        [$co, $admin] = $this->tenant('Adjust Co');
        $dataAdmin = $this->member($co->id, 'data administrator');
        $layout = $this->layout($co->id);
        $b = $this->upload($admin, 'sales', $this->sales([['01/11/2026', 'C1', 'A', 'P1', 'X', '1', '100', '', '']]), ['layout_id' => $layout->id]);
        $this->actingAs($admin)->post("/sales/imports/{$b->uuid}/confirm");
        $rec = SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->firstOrFail();

        $this->actingAs($dataAdmin)->post("/sales/records/{$rec->id}/adjust", ['field' => 'net_value', 'new_value' => '120', 'reason' => 'Credit note missed'])->assertRedirect();
        $adj = DB::table('sales_adjustments')->where('sales_record_id', $rec->id)->first();
        $this->assertEquals(100, (float) $rec->fresh()->net_value, 'A request alone must not change the figure.');

        $this->actingAs($admin)->post("/sales/adjustments/{$adj->id}/decide", ['decision' => 'apply'])->assertRedirect();
        $this->assertEquals(120, (float) $rec->fresh()->net_value);
        $this->assertSame('100.00', DB::table('sales_adjustments')->where('id', $adj->id)->value('original_value'));
    }

    public function test_scoped_users_and_other_companies_see_only_what_they_may(): void
    {
        [$co, $admin] = $this->tenant('Scope Sales Co');
        [$other, $otherAdmin] = $this->tenant('Other Sales Co');
        $layout = $this->layout($co->id);
        $this->as($co->id);
        $layout->update(['mapping' => array_merge($layout->mapping, [['source' => 'Region', 'field' => 'region']])]);
        $b = $this->upload($admin, 'sales', $this->csv([
            ['Inv Date', 'Acc No', 'Acc Name', 'Material', 'Material Desc', 'Cases', 'Nett Value', 'Region'],
            ['01/11/2026', 'C1', 'A', 'P1', 'X', '1', '100', 'Gauteng'],
            ['01/11/2026', 'C2', 'B', 'P1', 'X', '1', '200', 'Western Cape'],
        ]), ['layout_id' => $layout->id]);
        $this->actingAs($admin)->post("/sales/imports/{$b->uuid}/confirm");

        $analyst = $this->member($co->id, 'sales analyst');
        $gauteng = DB::table('regions')->where('company_id', $co->id)->where('name', 'Gauteng')->value('id');
        DB::table('user_data_scopes')->insert(['company_id' => $co->id, 'user_id' => $analyst->id, 'scope_type' => 'region', 'scope_id' => $gauteng, 'created_at' => now(), 'updated_at' => now()]);

        $totals = $this->actingAs($analyst)->get('/sales/records')->assertOk()->viewData('page')['props']['totals'];
        $this->assertEquals(100, (float) ((array) $totals)['net_value']);

        // Another company: the batch does not exist for them, and their totals are empty.
        $this->actingAs($otherAdmin)->get("/sales/imports/{$b->uuid}")->assertNotFound();
        $this->assertEquals(0, (float) $this->actingAs($otherAdmin)->get('/sales/records')->viewData('page')['props']['totals']->net_value);
    }
}
