<?php

namespace App\Modules\Sales\Jobs;

use App\Core\User\Models\User;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Services\ImportEngine;
use App\Modules\Sales\Services\SalesNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stage (validate) or commit an import in the background (SRS §22.3). The tenant travels with the
 * job (stamped on the payload by the platform), so the worker sees only this company's data.
 */
class ProcessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(public int $batchId, public string $step, public ?int $userId = null, public bool $autoCommit = false) {}

    public function handle(ImportEngine $engine, SalesNotifier $notifier): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);

        if ($this->step === 'stage') {
            $engine->stage($batch);
            $batch->refresh();
            // Automated (mailbox) imports commit themselves when every row is clean; anything
            // doubtful waits for a person (§5.3 step 10, §9.6).
            if ($this->autoCommit && $batch->status === 'staged' && $batch->invalid_rows === 0) {
                $engine->commit($batch, null);
            }
        } else {
            $engine->commit($batch, $this->userId ? User::find($this->userId) : null);
        }

        $notifier->importFinished($batch->refresh());
    }

    /** Small files run straight away so the person sees the preview at once; big ones queue. */
    public static function run(ImportBatch $batch, string $step, ?int $userId = null, bool $autoCommit = false): void
    {
        $job = new self($batch->id, $step, $userId, $autoCommit);
        $size = (int) (\Illuminate\Support\Facades\Storage::disk('documents')->size($batch->file_path) ?: 0);
        $size < 2 * 1024 * 1024 ? dispatch_sync($job) : dispatch($job)->onQueue('imports');
    }
}
