<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\User\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\BI\BIQueryService;
use App\Modules\Sales\Services\SalesAnalytics;
use App\Modules\Sales\Services\SalesQuery;
use App\Modules\Tenancy\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only share links (gap 8): anyone with the link sees the dashboard until it expires or is
 * revoked. Every number is computed as the person who made the link, so their data scopes apply —
 * a link never shows more than its creator could see. No editing, no drill-through to records.
 */
class BIShareController extends Controller
{
    public function show(string $token, TenantContext $tenants): Response
    {
        [$share, $dash, $creator] = $this->resolve($token);
        DB::table('bi_shares')->where('id', $share->id)->update(['views' => $share->views + 1, 'last_viewed_at' => now()]);

        return $tenants->run((int) $share->company_id, fn () => Inertia::render('Modules/Sales/BI/Shared', [
            'token' => $token,
            'dashboard' => ['name' => $dash->name, 'widgets' => json_decode($dash->widgets, true), 'certified' => (bool) $dash->certified],
            'company' => DB::table('companies')->where('id', $share->company_id)->value('name'),
            'filters' => json_decode($share->filters ?? '[]', true) ?: [],
            'expires' => substr((string) $share->expires_at, 0, 10),
            'sharedBy' => $creator->name,
            'lastSale' => app(SalesAnalytics::class)->lastSaleDate(),
        ]));
    }

    public function query(Request $request, string $token, TenantContext $tenants): JsonResponse
    {
        [$share, , $creator] = $this->resolve($token);
        $data = $request->validate(['config' => ['required', 'array'], 'filters' => ['nullable', 'array']]);
        // Viewers may narrow within the link's own filters, never widen them.
        $filters = array_merge($data['filters'] ?? [], json_decode($share->filters ?? '[]', true) ?: []);

        return $tenants->run((int) $share->company_id, fn () => response()->json(
            app(BIQueryService::class)->run($data['config'], app(SalesQuery::class)->filters(new Request($filters)), $creator)));
    }

    private function resolve(string $token): array
    {
        $share = DB::table('bi_shares')->where('token', $token)->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($share, 404, 'This link has expired or was withdrawn.');
        $dash = DB::table('bi_dashboards')->where('id', $share->dashboard_id)->first();
        $creator = User::where('id', $share->created_by)->where('status', 1)->first();
        abort_unless($dash && $creator, 404, 'This link has expired or was withdrawn.');

        return [$share, $dash, $creator];
    }
}
