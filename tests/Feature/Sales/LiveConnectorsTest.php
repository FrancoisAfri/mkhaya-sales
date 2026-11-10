<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Mailbox;
use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Models\SalesRecord;
use App\Modules\Sales\Services\Mail\ApiSourceClient;
use App\Modules\Sales\Services\Mail\DatabaseSourceClient;
use App\Modules\Sales\Services\Mail\MailboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** Gap 6: sales read straight from a database or a web API go through the normal import. */
class LiveConnectorsTest extends SalesTestCase
{
    private function layout(): ReportLayout
    {
        return ReportLayout::create(['name' => 'Live layout', 'target' => 'sales', 'ingestion_mode' => 'append', 'mapping' => [
            ['source' => 'inv_date', 'field' => 'transaction_date'], ['source' => 'acc_no', 'field' => 'customer_code'],
            ['source' => 'material', 'field' => 'product_code'], ['source' => 'cases', 'field' => 'quantity'], ['source' => 'nett', 'field' => 'net_value'],
        ]]);
    }

    public function test_a_database_query_is_imported_like_a_file(): void
    {
        Notification::fake();
        // The "client database" is a table in the test database, written on its own connection
        // (outside the test's transaction) so the connector's separate connection can see it.
        $c = config('database.connections.pgsql');
        $ext = new \PDO("pgsql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], (string) $c['password']);
        $ext->exec('create table if not exists ext_vector_sales (inv_date date, acc_no text, material text, cases int, nett numeric)');
        $ext->exec('truncate ext_vector_sales');
        $today = now()->toDateString();
        $ext->exec("insert into ext_vector_sales values ('{$today}', 'C1', 'P1', 3, 300), ('{$today}', 'C2', 'P1', 2, 200)");
        [$co] = $this->tenant('Db Co');
        $this->as($co->id);
        $source = Mailbox::create(['name' => 'ERP', 'provider' => 'database', 'mailbox_address' => $c['username'], 'layout_id' => $this->layout()->id,
            'credentials' => ['db_driver' => 'pgsql', 'host' => $c['host'], 'port' => $c['port'], 'database' => $c['database'], 'password' => (string) $c['password'],
                'query' => 'SELECT inv_date, acc_no, material, cases, nett FROM ext_vector_sales WHERE inv_date BETWEEN :from AND :to'],
            'run_time' => '03:30', 'timezone' => 'Africa/Johannesburg', 'auto_commit' => true, 'enabled' => true]);

        $run = app(MailboxService::class)->run($source->fresh(), 'manual');
        $this->assertSame('imported', $run->status, (string) $run->error);
        $this->assertEquals(500, (float) SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->sum('net_value'));
        $this->assertSame('duplicate', app(MailboxService::class)->run($source->fresh(), 'manual')->status, 'The same rows are not imported twice.');
        $ext->exec('drop table ext_vector_sales');
    }

    public function test_only_a_read_only_select_is_accepted(): void
    {
        DatabaseSourceClient::assertSafe("SELECT * FROM sales WHERE d > :from -- comment");
        DatabaseSourceClient::assertSafe("WITH x AS (SELECT 1) SELECT * FROM x;");
        foreach (['DELETE FROM sales', 'SELECT 1; DROP TABLE sales', 'SELECT * INTO copy FROM sales', 'EXEC sp_who', 'UPDATE t SET a = 1'] as $bad) {
            try {
                DatabaseSourceClient::assertSafe($bad);
                $this->fail("Accepted: {$bad}");
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_web_api_is_imported_and_internal_addresses_are_refused(): void
    {
        Notification::fake();
        Http::fake(['https://api.example.com/*' => Http::response(['data' => ['items' => [
            ['inv_date' => now()->toDateString(), 'acc_no' => 'C9', 'material' => 'P9', 'cases' => 4, 'nett' => 450]]]])]);
        [$co] = $this->tenant('Api Co');
        $this->as($co->id);
        $source = Mailbox::create(['name' => 'API', 'provider' => 'api', 'mailbox_address' => null, 'layout_id' => $this->layout()->id,
            'credentials' => ['url' => 'https://api.example.com/sales?from={from}&to={to}', 'auth_type' => 'bearer', 'password' => 'tok', 'rows_path' => 'data.items'],
            'run_time' => '03:30', 'timezone' => 'Africa/Johannesburg', 'auto_commit' => true, 'enabled' => true]);
        $run = app(MailboxService::class)->run($source->fresh(), 'manual');
        $this->assertSame('imported', $run->status, (string) $run->error);
        $this->assertEquals(450, (float) SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->sum('net_value'));
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok') && str_contains($r->url(), 'from='));

        foreach (['http://api.example.com/x', 'https://127.0.0.1/x', 'https://localhost/x'] as $bad) {
            try {
                ApiSourceClient::assertSafeUrl($bad);
                $this->fail("Accepted: {$bad}");
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
