<?php

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Jobs\FetchMailbox;
use App\Modules\Sales\Models\Mailbox;
use App\Modules\Sales\Services\SalesNotifier;
use App\Modules\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Runs every minute: starts each mailbox whose own time (e.g. 03:30 SAST) has come, and warns when
 * a day's report has not arrived by its deadline (SRS §5.3, §17.1 "Daily report missing").
 */
class RunMailboxes extends Command
{
    protected $signature = 'sales:mailboxes';

    protected $description = 'Start due mailbox retrievals and report missing daily reports';

    public function handle(TenantContext $tenants, SalesNotifier $notifier): int
    {
        $now = CarbonImmutable::now();
        foreach (Mailbox::withoutGlobalScopes()->where('enabled', true)->get() as $mailbox) {
            $tenants->run((int) $mailbox->company_id, function () use ($mailbox, $now, $notifier) {
                if ($mailbox->isDue($now)) {
                    $mailbox->update(['last_scheduled_on' => $now->setTimezone($mailbox->timezone)->toDateString()]);
                    FetchMailbox::dispatch($mailbox->id, 'schedule');
                    $this->line("Started {$mailbox->name} (company {$mailbox->company_id})");
                }
                $this->checkMissing($mailbox, $now, $notifier);
            });
        }

        return self::SUCCESS;
    }

    private function checkMissing(Mailbox $mailbox, CarbonImmutable $now, SalesNotifier $notifier): void
    {
        $local = $now->setTimezone($mailbox->timezone);
        [$h, $m] = array_map('intval', explode(':', $mailbox->run_time));
        $deadline = $local->startOfDay()->setTime($h, $m)->addMinutes($mailbox->missing_after_minutes);
        if (! $mailbox->runsOn($local) || $local->lessThan($deadline)) {
            return;
        }
        $importedToday = $mailbox->last_import_at && $mailbox->last_import_at->setTimezone($mailbox->timezone)->toDateString() === $local->toDateString();
        if (! $importedToday) {
            $notifier->alert($mailbox->company_id, 'imports.historical', "Daily report missing — {$mailbox->name}",
                "No report was received by {$deadline->format('H:i')}. Load the file manually in the Import Centre if it arrives another way.",
                url('/sales/imports'), "report-missing:{$mailbox->id}:" . $local->toDateString());
        }
    }
}
