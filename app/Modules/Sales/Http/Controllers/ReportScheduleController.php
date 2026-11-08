<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\ReportScheduler;
use App\Modules\Sales\Support\SalesCompany;
use App\Modules\Sales\Support\StandardReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Email schedules for saved reports, standard reports and dashboards (Release 3). */
class ReportScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorise($request);
        $c = SalesCompany::id();
        $owners = \App\Core\User\Models\User::whereIn('id', DB::table('report_schedules')->where('company_id', $c)->distinct()->pluck('user_id'))->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);

        return Inertia::render('Modules/Sales/Reports/Schedules', [
            'schedules' => DB::table('report_schedules')->where('company_id', $c)
                ->when(! $request->user()->can('settings.manage'), fn ($q) => $q->where('user_id', $request->user()->id))
                ->orderBy('name')->get()
                ->map(fn ($s) => ['recipients' => json_decode($s->recipients, true), 'owner' => $owners[$s->user_id] ?? '—'] + (array) $s),
            'sources' => [
                'standard' => collect(StandardReports::all())->map(fn ($r, $k) => ['value' => $k, 'title' => $r['title']])->values(),
                'saved' => DB::table('saved_reports')->where('company_id', $c)->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('shared', true))
                    ->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'title' => $s->name]),
                'dashboard' => DB::table('bi_dashboards')->where('company_id', $c)->orderBy('name')->get(['uuid', 'name'])->map(fn ($d) => ['value' => $d->uuid, 'title' => $d->name]),
            ],
            'periods' => collect(ReportScheduler::PERIODS)->map(fn ($t, $v) => ['value' => $v, 'title' => $t])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);
        DB::table('report_schedules')->insert($this->validated($request) + ['company_id' => SalesCompany::id(), 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Schedule saved. The report will be emailed at the time you chose.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $s = $this->find($request, $id);
        $data = $request->has('only_active') ? ['is_active' => $request->boolean('is_active')] : $this->validated($request);
        DB::table('report_schedules')->where('id', $s->id)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'Schedule updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        DB::table('report_schedules')->where('id', $this->find($request, $id)->id)->delete();

        return back()->with('success', 'Schedule removed.');
    }

    /** Send now — to check the email and attachment look right. */
    public function send(Request $request, int $id, ReportScheduler $scheduler): RedirectResponse
    {
        $r = $scheduler->send($this->find($request, $id));

        return $r['ok'] ? back()->with('success', "Sent {$r['file']} to {$r['recipients']} recipient(s).") : back()->withErrors(['schedule' => 'Could not send: ' . $r['error']]);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'source' => ['required', Rule::in(['standard', 'saved', 'dashboard'])],
            'source_ref' => ['required', 'string', 'max:80'],
            'format' => ['required', Rule::in(['xlsx', 'csv', 'pdf'])],
            'period' => ['required', Rule::in(array_keys(ReportScheduler::PERIODS))],
            'frequency' => ['required', Rule::in(['daily', 'weekdays', 'weekly', 'monthly'])],
            'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:1,7'],
            'month_day' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,31'],
            'send_time' => ['required', 'date_format:H:i'],
            'recipients' => ['required', 'array', 'min:1', 'max:25'], 'recipients.*' => ['email'],
            'is_active' => ['boolean'],
        ]);
        $valid = match ($d['source']) {
            'standard' => array_key_exists($d['source_ref'], StandardReports::all()),
            'saved' => DB::table('saved_reports')->where('company_id', SalesCompany::id())->where('id', (int) $d['source_ref'])->exists(),
            'dashboard' => DB::table('bi_dashboards')->where('company_id', SalesCompany::id())->where('uuid', $d['source_ref'])->exists(),
        };
        abort_unless($valid, 422, 'That report or dashboard does not exist.');
        if ($d['source'] === 'dashboard') {
            $d['format'] = 'pdf';
        }
        $d['recipients'] = json_encode(array_values(array_unique(array_map('strtolower', $d['recipients']))));

        return $d + ['is_active' => true];
    }

    private function find(Request $request, int $id): object
    {
        $this->authorise($request);
        $s = DB::table('report_schedules')->where('company_id', SalesCompany::id())->where('id', $id)->first();
        abort_unless($s && ($s->user_id === $request->user()->id || $request->user()->can('settings.manage')), 404);

        return $s;
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()->can('sales.view') && $request->user()->can('reports.export'), 403);
    }
}
