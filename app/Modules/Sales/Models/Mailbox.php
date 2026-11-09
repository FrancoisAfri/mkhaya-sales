<?php

namespace App\Modules\Sales\Models;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class Mailbox extends SalesModel
{
    protected $hidden = ['credentials'];

    protected $casts = [
        'credentials' => 'encrypted:array', 'run_days' => 'array', 'auto_commit' => 'boolean', 'enabled' => 'boolean',
        'last_scheduled_on' => 'date', 'last_run_at' => 'datetime', 'last_connected_at' => 'datetime',
        'last_email_at' => 'datetime', 'last_import_at' => 'datetime', 'token_expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function layout() { return $this->belongsTo(ReportLayout::class, 'layout_id'); }
    public function runs() { return $this->hasMany(MailboxRun::class); }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return parent::getActivitylogOptions()->logExcept(['credentials', 'last_run_at', 'last_connected_at', 'last_email_at', 'last_import_at', 'last_scheduled_on', 'status', 'last_error', 'token_expires_at']);
    }

    /** The next scheduled run, in the mailbox's own time zone. */
    public function nextRun(?CarbonImmutable $now = null): ?CarbonImmutable
    {
        if (! $this->enabled) {
            return null;
        }
        $now = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone);
        [$h, $m] = array_map('intval', explode(':', $this->run_time));
        for ($i = 0; $i < 8; $i++) {
            $at = $now->startOfDay()->addDays($i)->setTime($h, $m);
            if ($at->greaterThan($now) && $this->runsOn($at)) {
                return $at;
            }
        }

        return null;
    }

    public function runsOn(CarbonImmutable $day): bool
    {
        return empty($this->run_days) || in_array($day->isoWeekday(), array_map('intval', $this->run_days), true);
    }

    /** Is the scheduled run due now (and not yet done today)? */
    public function isDue(?CarbonImmutable $now = null): bool
    {
        $local = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone);
        [$h, $m] = array_map('intval', explode(':', $this->run_time));
        $at = $local->startOfDay()->setTime($h, $m);

        return $this->enabled && $this->runsOn($local) && $local->greaterThanOrEqualTo($at)
            && $local->diffInMinutes($at, true) < 180
            && (! $this->last_scheduled_on || $this->last_scheduled_on->toDateString() !== $local->toDateString());
    }

    /** Method 2: files collected from a secure central repository rather than email. */
    public function isRepository(): bool
    {
        return in_array($this->provider, ['sftp', 'sharepoint'], true);
    }
}
