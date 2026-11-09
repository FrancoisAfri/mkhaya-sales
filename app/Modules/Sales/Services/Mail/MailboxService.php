<?php

namespace App\Modules\Sales\Services\Mail;

use App\Modules\Sales\Jobs\ProcessImport;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Models\Mailbox;
use App\Modules\Sales\Models\MailboxRun;
use App\Modules\Sales\Services\ImportEngine;
use App\Modules\Sales\Services\SalesNotifier;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Retrieves the authorised daily report and hands it to the import pipeline (SRS §5.3 steps 1–12).
 * Only messages matching the mailbox's rules are opened; a message or file already processed is
 * never imported again (§5.4).
 */
class MailboxService
{
    public function __construct(private MailConnector $connector, private ImportEngine $engine, private SalesNotifier $notifier) {}

    public function run(Mailbox $mailbox, string $trigger = 'schedule', ?int $userId = null, int $attempt = 1): MailboxRun
    {
        $run = MailboxRun::create([
            'company_id' => $mailbox->company_id, 'mailbox_id' => $mailbox->id, 'trigger' => $trigger,
            'attempt' => $attempt, 'triggered_by' => $userId, 'started_at' => now(),
        ]);
        $mailbox->update(['last_run_at' => now()]);

        try {
            $client = $this->connector->for($mailbox);
            $messages = $client->fetchSince(now()->subDays(max(1, $mailbox->lookback_days))->startOfDay(), $mailbox->sender && str_contains($mailbox->sender, '@') && ! str_starts_with($mailbox->sender, '@') ? $mailbox->sender : null);
            $mailbox->update(['last_connected_at' => now(), 'status' => 'healthy', 'last_error' => null]);

            $matching = collect($messages)->filter(fn ($m) => $this->matches($mailbox, $m))
                ->sortBy(fn ($m) => $m['date'] ?? '')->values();
            $run->emails_seen = $matching->count();

            $imported = 0;
            $duplicates = 0;
            foreach ($matching as $m) {
                $messageId = (string) ($m['message_id'] ?? '');
                if ($messageId !== '' && MailboxRun::where('mailbox_id', $mailbox->id)->where('message_id', $messageId)->where('status', 'imported')->exists()) {
                    $duplicates++;
                    continue;
                }
                $attachments = is_callable($m['attachments']) ? ($m['attachments'])() : $m['attachments'];
                foreach ($attachments as $a) {
                    if (! $this->fileAllowed($mailbox, $a['name'])) {
                        continue;
                    }
                    $run->fill(['message_id' => $messageId ?: null, 'subject' => Str::limit($m['subject'] ?? '', 250, ''), 'sender' => $m['from_email'] ?? null,
                        'received_at' => isset($m['date']) ? Carbon::parse($m['date']) : null, 'attachment_name' => $a['name']]);
                    $mailbox->update(['last_email_at' => $run->received_at ?? now()]);

                    if (ImportBatch::where('mailbox_id', $mailbox->id)->where('file_hash', hash('sha256', $a['bytes']))->whereIn('status', ['staged', 'committed', 'committing'])->exists()) {
                        $duplicates++;
                        continue;
                    }

                    $batch = $this->engine->receive('sales', $a['name'], $a['bytes'], [
                        'layout_id' => $mailbox->layout_id, 'source' => $mailbox->isRepository() ? 'repository' : 'email', 'mailbox_id' => $mailbox->id, 'uploaded_by' => null,
                    ]);
                    $run->import_batch_id = $batch->id;
                    ProcessImport::run($batch, 'stage', null, $mailbox->auto_commit);
                    $imported++;
                    $mailbox->update(['last_import_at' => now()]);
                }
            }

            $run->status = $imported ? 'imported' : ($duplicates ? 'duplicate' : 'no_email');
        } catch (\Throwable $e) {
            $run->status = 'failed';
            $run->error = Str::limit($e->getMessage(), 2000);
            $mailbox->update(['status' => 'failing', 'last_error' => $run->error]);
            $this->notifier->alert($mailbox->company_id, 'integrations.manage', "Mailbox \"{$mailbox->name}\" failed",
                $run->error, url('/sales/mailboxes'), "mailbox-failed:{$mailbox->id}:" . now()->toDateString());
        }

        $run->finished_at = now();
        $run->save();

        return $run;
    }

    /** Sender, subject — the email-level rules (§5.2). */
    public function matches(Mailbox $mailbox, array $m): bool
    {
        $from = strtolower((string) ($m['from_email'] ?? ''));
        if ($mailbox->sender) {
            $rule = strtolower(trim($mailbox->sender));
            $ok = str_starts_with($rule, '@') ? str_ends_with($from, $rule) : $from === $rule;
            if (! $ok) {
                return false;
            }
        }
        if ($mailbox->subject_pattern) {
            $p = $mailbox->subject_pattern;
            $subject = (string) ($m['subject'] ?? '');
            $ok = (str_starts_with($p, '/') && strlen($p) > 2 && @preg_match($p, '') !== false)
                ? (bool) preg_match($p, $subject)
                : str_contains(mb_strtolower($subject), mb_strtolower($p));
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    public function fileAllowed(Mailbox $mailbox, string $name): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = array_filter(array_map('trim', explode(',', strtolower($mailbox->extensions ?: 'xlsx,xls,csv'))));
        if (! in_array($ext, $allowed, true)) {
            return false;
        }

        return ! $mailbox->filename_pattern || Str::is(strtolower($mailbox->filename_pattern), strtolower($name));
    }
}
