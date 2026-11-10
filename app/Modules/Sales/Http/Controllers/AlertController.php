<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\BI\BIQueryService;
use App\Modules\Sales\Services\BI\UserAlertService;
use App\Modules\Sales\Support\SalesCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** A person's own alerts on any number (gap 3). Each person sees and manages only their own. */
class AlertController extends Controller
{
    public function index(Request $request, BIQueryService $bi): Response
    {
        abort_unless($request->user()->can('dashboards.view'), 403);

        return Inertia::render('Modules/Sales/Alerts/Index', [
            'alerts' => DB::table('bi_alerts')->where('company_id', SalesCompany::id())->where('user_id', $request->user()->id)->orderBy('name')->get()
                ->map(fn ($a) => (array) $a + ['filters' => json_decode($a->filters ?? '[]', true), 'channels' => json_decode($a->channels, true)]),
            'measures' => collect($bi->catalogue())->only(['net_value', 'gross_value', 'quantity', 'discount', 'active_customers', 'active_products', 'transactions', 'avg_selling_price'])
                ->map(fn ($m, $k) => ['value' => $k, 'title' => $m['label']])->values(),
            'periods' => collect(UserAlertService::PERIODS)->map(fn ($t, $v) => ['value' => $v, 'title' => $t])->values(),
            'comparisons' => collect(UserAlertService::COMPARISONS)->map(fn ($t, $v) => ['value' => $v, 'title' => $t])->values(),
            'options' => app(\App\Modules\Sales\Services\SalesQuery::class)->filterOptions([]),
            'prefill' => $request->only(['measure', 'name']) + ['filters' => (array) json_decode((string) $request->query('filters', '[]'), true)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::table('bi_alerts')->insert($this->validated($request) + ['uuid' => (string) Str::uuid(), 'company_id' => SalesCompany::id(),
            'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Alert saved. You will be told when it fires.');
    }

    public function update(Request $request, string $uuid): RedirectResponse
    {
        $a = $this->find($request, $uuid);
        $data = $request->has('only_active') ? ['is_active' => $request->boolean('is_active')] : $this->validated($request);
        DB::table('bi_alerts')->where('id', $a->id)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'Alert updated.');
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        DB::table('bi_alerts')->where('id', $this->find($request, $uuid)->id)->delete();

        return back()->with('success', 'Alert removed.');
    }

    /** Check now, without waiting for the scheduled time; it does not notify. */
    public function test(Request $request, string $uuid, UserAlertService $alerts): RedirectResponse
    {
        $r = $alerts->check($this->find($request, $uuid), false);

        return back()->with($r['triggered'] ? 'warning' : 'success', ($r['triggered'] ? 'Would fire now — ' : 'Would not fire now — ') . $r['text']);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'measure' => ['required', Rule::in(array_keys(app(BIQueryService::class)->catalogue()))],
            'filters' => ['nullable', 'array'], 'filters.*' => ['nullable'],
            'period' => ['required', Rule::in(array_keys(UserAlertService::PERIODS))],
            'comparison' => ['required', Rule::in(array_keys(UserAlertService::COMPARISONS))],
            'operator' => ['required', Rule::in(['above', 'below'])],
            'threshold' => ['required', 'numeric', 'between:-1000000000,1000000000'],
            'frequency' => ['required', Rule::in(['daily', 'weekly'])],
            'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:1,7'],
            'run_time' => ['required', 'date_format:H:i'],
            'channels' => ['required', 'array', 'min:1'], 'channels.*' => [Rule::in(['in_app', 'email'])],
            'is_active' => ['boolean'],
        ]);
        $allowed = array_merge(\App\Modules\Sales\Services\SalesQuery::FILTERS, \App\Modules\Sales\Services\SalesQuery::TEXT_FILTERS);
        $d['filters'] = json_encode(array_filter(array_intersect_key($d['filters'] ?? [], array_flip($allowed)), fn ($v) => $v !== null && $v !== ''));
        $d['channels'] = json_encode(array_values(array_unique($d['channels'])));

        return $d + ['is_active' => true];
    }

    private function find(Request $request, string $uuid): object
    {
        abort_unless($request->user()->can('dashboards.view'), 403);

        return DB::table('bi_alerts')->where('company_id', SalesCompany::id())->where('user_id', $request->user()->id)->where('uuid', $uuid)->firstOrFail();
    }
}
