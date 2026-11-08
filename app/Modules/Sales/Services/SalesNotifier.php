<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Notifications\SalesAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * In-app + email alerts (SRS NOT-001). The same unresolved issue is not repeated within a day.
 */
class SalesNotifier
{
    public function importFinished(ImportBatch $batch): void
    {
        $recipients = $this->recipients($batch->company_id, 'imports.historical', $batch->uploaded_by);
        $url = url("/sales/imports/{$batch->uuid}");

        $msg = match ($batch->status) {
            'committed' => ["Import completed: {$batch->file_name}", "{$batch->inserted_rows} added, {$batch->updated_rows} updated, {$batch->duplicate_rows} duplicates skipped."],
            'failed' => ["Import failed: {$batch->file_name}", (string) $batch->error],
            'staged' => $batch->invalid_rows > 0
                ? ["Import needs attention: {$batch->file_name}", "{$batch->invalid_rows} rows have errors and nothing was saved yet."]
                : ["Import ready to confirm: {$batch->file_name}", "{$batch->valid_rows} rows passed validation."],
            default => null,
        };
        if ($msg) {
            $this->send($recipients, $msg[0], $msg[1], $url, "import:{$batch->id}:{$batch->status}");
        }

        $newMasters = $batch->status === 'committed'
            ? \App\Modules\Sales\Models\Product::where('needs_review', true)->count() + \App\Modules\Sales\Models\Customer::where('needs_review', true)->count()
            : 0;
        if ($newMasters > 0) {
            $this->send($this->recipients($batch->company_id, 'products.manage'), 'New products or customers need mapping',
                "{$newMasters} records were created from sales files and are waiting for review.", url('/sales/master/products?needs_review=1'),
                "review:{$batch->company_id}:" . now()->toDateString());
        }
    }

    /** @return bool whether it was sent (false when nobody may receive it, or it was already raised) */
    public function alert(int $companyId, string $permission, string $title, string $body, string $url, string $dedupeKey, int $quietDays = 1): bool
    {
        return $this->send($this->recipients($companyId, $permission), $title, $body, $url, $dedupeKey, $quietDays);
    }

    private function send($users, string $title, string $body, string $url, string $dedupeKey, int $quietDays = 1): bool
    {
        if ($users->isEmpty() || ! Cache::add("sales-alert:{$dedupeKey}", 1, now()->addDays($quietDays))) {
            return false;
        }
        Notification::send($users, new SalesAlert($title, $body, $url));

        return true;
    }

    private function recipients(int $companyId, string $permission, ?int $alsoUserId = null)
    {
        return User::where('company_id', $companyId)->where('status', 1)->get()
            ->filter(fn (User $u) => $u->id === $alsoUserId || $u->can($permission))
            ->values();
    }
}
