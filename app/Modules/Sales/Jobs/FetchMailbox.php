<?php

namespace App\Modules\Sales\Jobs;

use App\Modules\Sales\Models\Mailbox;
use App\Modules\Sales\Services\Mail\MailboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** One mailbox run; a failed connection is retried after 5 and then 15 minutes (SRS §5.4). */
class FetchMailbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $mailboxId, public string $trigger = 'schedule', public ?int $userId = null, public int $attempt = 1) {}

    public function handle(MailboxService $service): void
    {
        $mailbox = Mailbox::find($this->mailboxId);
        if (! $mailbox) {
            return;
        }
        $run = $service->run($mailbox, $this->attempt > 1 ? 'retry' : $this->trigger, $this->userId, $this->attempt);

        if ($run->status === 'failed' && $this->attempt < 3) {
            self::dispatch($this->mailboxId, 'retry', $this->userId, $this->attempt + 1)
                ->delay(now()->addMinutes($this->attempt === 1 ? 5 : 15));
        }
    }
}
