<?php

namespace Tests\Feature\Sales;

use App\Modules\Sales\Jobs\FetchMailbox;
use App\Modules\Sales\Models\Mailbox;
use App\Modules\Sales\Models\MailboxRun;
use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Models\SalesRecord;
use App\Modules\Sales\Services\Mail\MailboxService;
use App\Modules\Sales\Services\Mail\MailConnector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

/** Phase 5 — SRS INT-001 and acceptance scenarios 1–3. */
class MailboxTest extends SalesTestCase
{
    private array $inbox = [];
    private bool $down = false;

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $this->app->instance(MailConnector::class, new class($test) extends MailConnector {
            public function __construct(private $test) {}

            public function for(Mailbox $mailbox): object
            {
                $test = $this->test;

                return new class($test) {
                    public function __construct(private $test) {}

                    public function fetchSince($since, $from): array
                    {
                        if ($this->test->isDown()) {
                            throw new \RuntimeException('Connection timed out');
                        }

                        return $this->test->messages();
                    }
                };
            }
        });
    }

    public function isDown(): bool { return $this->down; }
    public function messages(): array { return $this->inbox; }

    private function report(string $from, string $subject, string $id, string $csv): array
    {
        return ['message_id' => $id, 'subject' => $subject, 'from_email' => $from, 'date' => now()->toIso8601String(),
            'attachments' => [['name' => 'Daily Sales.csv', 'bytes' => $csv]]];
    }

    private function mailbox(int $companyId, array $extra = []): Mailbox
    {
        $this->as($companyId);
        $layout = ReportLayout::create(['name' => 'Mail layout', 'target' => 'sales', 'ingestion_mode' => 'append', 'mapping' => [
            ['source' => 'Date', 'field' => 'transaction_date'], ['source' => 'Customer', 'field' => 'customer_code'],
            ['source' => 'Product', 'field' => 'product_code'], ['source' => 'Value', 'field' => 'net_value'],
        ]]);

        return Mailbox::create(array_merge([
            'name' => 'Vector daily', 'provider' => 'graph', 'mailbox_address' => 'reports@client.test',
            'credentials' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's'],
            'sender' => 'sales@vector.test', 'subject_pattern' => 'Daily Sales', 'layout_id' => $layout->id,
            'run_time' => '03:30', 'timezone' => 'Africa/Johannesburg', 'auto_commit' => true, 'enabled' => true,
        ], $extra));
    }

    public function test_the_authorised_report_is_imported_once_and_other_mail_is_ignored(): void
    {
        Notification::fake();
        [$co] = $this->tenant('Mail Co');
        $mailbox = $this->mailbox($co->id);
        $csv = "Date,Customer,Product,Value\n2026-11-07,C1,P1,1000\n2026-11-07,C2,P1,250\n";
        $this->inbox = [
            $this->report('sales@vector.test', 'Daily Sales 07/11', 'm-1', $csv),
            $this->report('someone@else.test', 'Daily Sales 07/11', 'm-2', "Date,Customer,Product,Value\n2026-11-07,X,Y,99999\n"),
            $this->report('sales@vector.test', 'Lunch on Friday?', 'm-3', $csv),
        ];

        $run = app(MailboxService::class)->run($mailbox->fresh(), 'schedule');
        $this->assertSame('imported', $run->status, (string) $run->error);
        $this->assertSame(1, $run->emails_seen);
        $this->assertEquals(1250, (float) SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->sum('net_value'));

        $again = app(MailboxService::class)->run($mailbox->fresh(), 'manual');
        $this->assertSame('duplicate', $again->status);
        $this->assertEquals(1250, (float) SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->sum('net_value'));
        $this->assertSame('healthy', $mailbox->fresh()->status);
    }

    public function test_a_central_repository_file_is_collected_like_an_email(): void
    {
        Notification::fake();
        [$co] = $this->tenant('Repo Co');
        $source = $this->mailbox($co->id, ['provider' => 'sftp', 'mailbox_address' => 'vector', 'folder' => '/outgoing',
            'credentials' => ['host' => 'sftp.vector.test', 'password' => 'x'], 'sender' => null, 'subject_pattern' => null, 'filename_pattern' => '*sales*']);
        $this->assertTrue($source->isRepository());
        $file = fn ($name, $id, $csv) => ['message_id' => $id, 'subject' => $name, 'from_email' => '', 'date' => now()->toIso8601String(),
            'attachments' => [['name' => $name, 'bytes' => $csv]]];
        $this->inbox = [
            $file('daily_sales_20261107.csv', 'sftp:a', "Date,Customer,Product,Value\n2026-11-07,C1,P1,700\n"),
            $file('stock_levels.csv', 'sftp:b', "Date,Customer,Product,Value\n2026-11-07,C1,P1,99999\n"),
        ];

        $run = app(MailboxService::class)->run($source->fresh(), 'schedule');
        $this->assertSame('imported', $run->status, (string) $run->error);
        $this->assertEquals(700, (float) SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->sum('net_value'), 'Only files matching the name rule are read.');
        $this->assertSame('repository', \App\Modules\Sales\Models\ImportBatch::withoutGlobalScopes()->find($run->import_batch_id)->source);
        $this->assertSame('duplicate', app(MailboxService::class)->run($source->fresh(), 'manual')->status);
    }

    public function test_a_file_with_errors_waits_for_a_person(): void
    {
        [$co] = $this->tenant('Mail Review Co');
        $mailbox = $this->mailbox($co->id);
        $this->inbox = [$this->report('sales@vector.test', 'Daily Sales', 'm-9', "Date,Customer,Product,Value\nbad,C1,P1,10\n2026-11-07,C1,P1,10\n")];

        $run = app(MailboxService::class)->run($mailbox->fresh());
        $this->assertSame('staged', $run->batch->status);
        $this->assertSame(0, SalesRecord::withoutGlobalScopes()->where('company_id', $co->id)->count());
    }

    public function test_the_run_time_is_configurable_and_runs_once_a_day(): void
    {
        [$co] = $this->tenant('Schedule Co');
        $mailbox = $this->mailbox($co->id, ['run_time' => '05:15']);
        $at = fn (string $t) => CarbonImmutable::parse("2026-11-09 {$t}", 'Africa/Johannesburg');

        $this->assertFalse($mailbox->isDue($at('05:14')));
        $this->assertTrue($mailbox->isDue($at('05:15')));
        $this->assertSame('2026-11-09 05:15', $mailbox->nextRun($at('04:00'))->format('Y-m-d H:i'));

        $mailbox->update(['last_scheduled_on' => '2026-11-09']);
        $this->assertFalse($mailbox->fresh()->isDue($at('05:16')), 'It must not run twice the same day.');

        $mailbox->update(['run_days' => [1, 2, 3, 4, 5]]); // weekdays; 2026-11-08 is a Sunday
        $this->assertFalse($mailbox->fresh()->isDue(CarbonImmutable::parse('2026-11-08 05:15', 'Africa/Johannesburg')));
    }

    public function test_the_scheduler_starts_due_mailboxes_and_reports_a_missing_report(): void
    {
        Bus::fake([FetchMailbox::class]);
        Notification::fake();
        [$co] = $this->tenant('Missing Co');
        $mailbox = $this->mailbox($co->id, ['run_time' => '03:30', 'missing_after_minutes' => 60]);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 03:30', 'Africa/Johannesburg'));

        $this->artisan('sales:mailboxes')->assertSuccessful();
        Bus::assertDispatched(FetchMailbox::class, fn ($j) => $j->mailboxId === $mailbox->id);

        $this->travelTo(CarbonImmutable::parse('2026-11-09 04:31', 'Africa/Johannesburg'));
        $this->artisan('sales:mailboxes')->assertSuccessful();
        Notification::assertSentTimes(\App\Modules\Sales\Notifications\SalesAlert::class, 1);
    }

    public function test_a_connection_failure_is_logged_and_retried(): void
    {
        Notification::fake();
        Bus::fake([FetchMailbox::class]);
        [$co] = $this->tenant('Down Co');
        $mailbox = $this->mailbox($co->id);
        $this->down = true;

        (new FetchMailbox($mailbox->id))->handle(app(MailboxService::class));

        $this->assertSame('failed', MailboxRun::withoutGlobalScopes()->where('mailbox_id', $mailbox->id)->value('status'));
        $this->assertSame('failing', $mailbox->fresh()->status);
        Bus::assertDispatched(FetchMailbox::class, fn ($j) => $j->attempt === 2);
    }

    public function test_secrets_never_reach_the_browser_and_other_companies_cannot_see_the_mailbox(): void
    {
        [$co, $admin] = $this->tenant('Secret Co');
        [, $otherAdmin] = $this->tenant('Nosy Co');
        $mailbox = $this->mailbox($co->id);

        $json = json_encode($this->actingAs($admin)->get('/sales/mailboxes')->assertOk()->viewData('page')['props']);
        $this->assertStringContainsString('Vector daily', $json);
        $this->assertStringNotContainsString('client_secret":"s', $json);

        $this->actingAs($otherAdmin)->post("/sales/mailboxes/{$mailbox->uuid}/run")->assertNotFound();
    }
}
