<?php

namespace App\Modules\Sales\Services;

use App\Core\User\Models\User;
use App\Modules\Sales\Mail\ScheduledReportMail;
use App\Modules\Sales\Services\BI\BIQueryService;
use App\Modules\Sales\Support\SalesCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Scheduled report emails (Release 3): a saved report, a standard report or a dashboard summary,
 * built as the schedule's owner (so their data scopes apply) and mailed with the file attached.
 */
class ReportScheduler
{
    public const PERIODS = ['yesterday' => 'Yesterday', 'last_7_days' => 'Last 7 days', 'mtd' => 'Month to date', 'last_month' => 'Last month', 'qtd' => 'Quarter to date', 'ytd' => 'Year to date'];

    public function __construct(private ReportExporter $exporter) {}

    /** Is this schedule due at this minute (Africa/Johannesburg)? */
    public static function due(object $s, CarbonImmutable $now): bool
    {
        if (! $s->is_active || $s->send_time !== $now->format('H:i')) {
            return false;
        }
        if ($s->last_sent_at && CarbonImmutable::parse($s->last_sent_at)->gt($now->subMinutes(50))) {
            return false;
        }

        return match ($s->frequency) {
            'daily' => true,
            'weekdays' => $now->isWeekday(),
            'weekly' => (int) $s->weekday === $now->dayOfWeekIso,
            'monthly' => min((int) $s->month_day, $now->daysInMonth) === $now->day,
            default => false,
        };
    }

    public static function range(string $period, CarbonImmutable $today): array
    {
        $y = $today->subDay();
        [$from, $to] = match ($period) {
            'yesterday' => [$y, $y],
            'last_7_days' => [$today->subDays(7), $y],
            'mtd' => [$today->startOfMonth(), $today],
            'qtd' => [$today->firstOfQuarter(), $today],
            'ytd' => [$today->startOfYear(), $today],
            default => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
        };

        return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    /** Build and send one schedule now. Runs inside the schedule's company. */
    public function send(object $s, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('Africa/Johannesburg');
        $user = User::find($s->user_id);
        $f = self::range($s->period, $now) + array_filter((array) json_decode($s->filters ?? '[]', true));
        $file = null;
        try {
            if ($s->source === 'dashboard') {
                $file = $this->exporter->dashboardPdf($s->source_ref, $f, $user);
                $title = $s->name;
            } else {
                $report = $this->exporter->definition($s->source, $s->source_ref);
                $file = $this->exporter->file($report, $f, $user, $s->format);
                $title = $s->name ?: $report['title'];
            }
            $lines = app(BIQueryService::class)->run(['kind' => 'narrative'], $f, $user)['lines'] ?? [];
            $company = DB::table('companies')->where('id', SalesCompany::id())->value('name');
            $recipients = array_values(array_filter((array) json_decode($s->recipients, true), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
            Mail::to($recipients)->send(new ScheduledReportMail($title, "{$f['from']} to {$f['to']}", $company, $lines, $file));
            DB::table('report_schedules')->where('id', $s->id)->update(['last_sent_at' => now(), 'last_status' => 'sent', 'last_error' => null, 'updated_at' => now()]);

            return ['ok' => true, 'recipients' => count($recipients), 'file' => $file['name']];
        } catch (\Throwable $e) {
            report($e);
            DB::table('report_schedules')->where('id', $s->id)->update(['last_sent_at' => now(), 'last_status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            if ($file && is_file($file['path'])) {
                @unlink($file['path']);
            }
        }
    }
}
