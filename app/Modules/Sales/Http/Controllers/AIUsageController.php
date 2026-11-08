<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Support\SalesCompany;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Services\AiQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** AI usage monitoring for the organisation (SRS §16.3 limits, §16.4 tracking). */
class AIUsageController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage') || $request->user()->can('audit.view'), 403);
        $c = SalesCompany::id();
        $from = $request->query('month') ? \Carbon\Carbon::parse($request->query('month') . '-01') : now()->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $base = fn () => DB::table('ai_usage_log as a')->where('a.company_id', $c)->whereBetween('a.created_at', [$from, $to]);
        $group = fn (string $col) => $base()->selectRaw("coalesce({$col}, 'unknown') as name, count(*) as requests, sum(total_tokens) as tokens, sum(cost_usd) as cost, sum(case when success then 1 else 0 end) as ok")
            ->groupBy('name')->orderByDesc('tokens')->limit(20)->get();
        $company = Company::find($c);

        return Inertia::render('Modules/Sales/AI/Usage', [
            'month' => $from->format('Y-m'),
            'totals' => $base()->selectRaw('count(*) as requests, coalesce(sum(total_tokens),0) as tokens, coalesce(sum(cost_usd),0) as cost, coalesce(sum(case when success then 1 else 0 end),0) as ok, count(distinct user_id) as users')->first(),
            'quota' => $company ? app(AiQuota::class)->snapshot($company) : null,
            'byUser' => $base()->leftJoin('users as u', 'u.id', '=', 'a.user_id')->selectRaw("coalesce(u.email, 'system') as name, count(*) as requests, sum(a.total_tokens) as tokens, sum(a.cost_usd) as cost, sum(case when a.success then 1 else 0 end) as ok")
                ->groupBy('name')->orderByDesc('tokens')->limit(20)->get(),
            'byCategory' => $group('a.category'),
            'byModel' => $base()->selectRaw("coalesce(provider, '—') || ' · ' || coalesce(model_used, 'unknown') as name, count(*) as requests, sum(total_tokens) as tokens, sum(cost_usd) as cost, sum(case when success then 1 else 0 end) as ok")->groupBy('name')->orderByDesc('tokens')->limit(20)->get(),
            'recent' => $base()->leftJoin('users as u', 'u.id', '=', 'a.user_id')->orderByDesc('a.id')->limit(50)
                ->get(['a.created_at', 'u.email', 'a.category', 'a.provider', 'a.model_used', 'a.total_tokens', 'a.cost_usd', 'a.success', 'a.error_message', 'a.input_summary']),
            'usdToZar' => (float) config('services.ai.usd_zar', 18.5),
        ]);
    }
}
