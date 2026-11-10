<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\User\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\SalesNotifier;
use App\Modules\Sales\Support\SalesCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Working together on dashboards (gap review 2026-11-10): version history and restore, certified
 * dashboards, comments with @mentions on a visual, and read-only share links that expire.
 */
class BICollaborationController extends Controller
{
    // ── Versions (gap 12) ───────────────────────────────────────────────────────────────────────

    /** Called before a dashboard's widgets change, so the previous layout can be restored. */
    public static function snapshot(object $dash, ?int $userId, ?string $note = null): void
    {
        $next = (int) DB::table('bi_dashboard_versions')->where('dashboard_id', $dash->id)->max('version') + 1;
        DB::table('bi_dashboard_versions')->insert(['company_id' => $dash->company_id, 'dashboard_id' => $dash->id, 'version' => $next,
            'name' => $dash->name, 'widgets' => $dash->widgets, 'saved_by' => $userId, 'note' => $note, 'created_at' => now()]);
        // Keep the last 30.
        $keep = DB::table('bi_dashboard_versions')->where('dashboard_id', $dash->id)->orderByDesc('version')->limit(30)->pluck('id');
        DB::table('bi_dashboard_versions')->where('dashboard_id', $dash->id)->whereNotIn('id', $keep)->delete();
    }

    public function versions(Request $request, string $uuid): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);
        $rows = DB::table('bi_dashboard_versions as v')->where('v.dashboard_id', $dash->id)->orderByDesc('v.version')->limit(30)
            ->get(['v.id', 'v.version', 'v.name', 'v.note', 'v.saved_by', 'v.created_at', 'v.widgets']);
        $names = $this->userNames($rows->pluck('saved_by')->all());

        return response()->json($rows->map(fn ($v) => ['id' => $v->id, 'version' => $v->version, 'name' => $v->name, 'note' => $v->note,
            'by' => $names[$v->saved_by] ?? '—', 'at' => $v->created_at, 'visuals' => count(json_decode($v->widgets, true) ?? [])]));
    }

    public function restore(Request $request, string $uuid, int $id): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);
        abort_unless($dash->owner_id === $request->user()->id || $request->user()->can('settings.manage'), 403);
        $v = DB::table('bi_dashboard_versions')->where('dashboard_id', $dash->id)->where('id', $id)->firstOrFail();
        self::snapshot($dash, $request->user()->id, "Before restoring version {$v->version}");
        DB::table('bi_dashboards')->where('id', $dash->id)->update(['widgets' => $v->widgets, 'name' => $v->name, 'certified' => false, 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Certified = checked by an administrator: shown with a badge and trusted for decisions. */
    public function certify(Request $request, string $uuid): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        $dash = $this->dashboard($request, $uuid);
        $on = $request->boolean('certified');
        DB::table('bi_dashboards')->where('id', $dash->id)->update(['certified' => $on, 'certified_by' => $on ? $request->user()->id : null,
            'certified_at' => $on ? now() : null, 'updated_at' => now()]);

        return response()->json(['ok' => true, 'certified' => $on]);
    }

    // ── Comments with @mentions (gap 9) ─────────────────────────────────────────────────────────

    public function comments(Request $request, string $uuid): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);
        $rows = DB::table('bi_comments')->where('dashboard_id', $dash->id)->orderBy('created_at')->limit(500)->get();
        $names = $this->userNames($rows->pluck('user_id')->all());

        return response()->json($rows->map(fn ($c) => ['id' => $c->id, 'widget_id' => $c->widget_id, 'body' => $c->body, 'by' => $names[$c->user_id] ?? '—',
            'mine' => $c->user_id === $request->user()->id, 'at' => $c->created_at, 'resolved' => (bool) $c->resolved_at]));
    }

    public function comment(Request $request, string $uuid, SalesNotifier $notifier): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'widget_id' => ['nullable', 'string', 'max:20']]);
        $mentioned = $this->mentions($data['body'], $request->user()->id);
        $id = DB::table('bi_comments')->insertGetId(['company_id' => SalesCompany::id(), 'dashboard_id' => $dash->id, 'widget_id' => $data['widget_id'] ?? null,
            'user_id' => $request->user()->id, 'body' => $data['body'], 'mentions' => json_encode($mentioned->pluck('id')->all()), 'created_at' => now(), 'updated_at' => now()]);

        $widget = collect(json_decode($dash->widgets, true) ?? [])->firstWhere('id', $data['widget_id'] ?? null);
        $notifier->toUsers($mentioned, "{$request->user()->name} mentioned you on “{$dash->name}”",
            ($widget ? "On “{$widget['title']}”: " : '') . Str::limit($data['body'], 300), url("/sales/bi/{$dash->uuid}?comment={$id}"), "bi-comment:{$id}");

        return response()->json(['ok' => true, 'id' => $id, 'notified' => $mentioned->pluck('name')]);
    }

    public function resolveComment(Request $request, string $uuid, int $id): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);
        DB::table('bi_comments')->where('dashboard_id', $dash->id)->where('id', $id)->update(['resolved_at' => $request->boolean('resolved', true) ? now() : null]);

        return response()->json(['ok' => true]);
    }

    /** People in this company the comment names, as @firstname, @"First Last" or @email. */
    private function mentions(string $body, int $authorId)
    {
        preg_match_all('/@"([^"]{2,60})"|@([\w.\-+]+@[\w.\-]+\.\w+)|@([\p{L}][\p{L}\'\-]{1,40})/u', $body, $m, PREG_SET_ORDER);
        if (! $m) {
            return collect();
        }
        $people = User::where('company_id', SalesCompany::id())->where('status', 1)->where('id', '!=', $authorId)->get();

        return collect($m)->map(function ($hit) use ($people) {
            $token = mb_strtolower(trim($hit[1] ?: $hit[2] ?: $hit[3]));

            return $people->first(fn ($u) => mb_strtolower($u->email) === $token || mb_strtolower($u->name) === $token
                || mb_strtolower(strtok($u->name, ' ') ?: '') === $token);
        })->filter()->unique('id')->values();
    }

    /** Names for the @ picker. */
    public function people(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('dashboards.view'), 403);

        return response()->json(User::where('company_id', SalesCompany::id())->where('status', 1)->get()
            ->map(fn ($u) => ['name' => $u->name, 'email' => $u->email])->sortBy('name')->values());
    }

    // ── Share links (gap 8) ─────────────────────────────────────────────────────────────────────

    public function shares(Request $request, string $uuid): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);

        return response()->json(DB::table('bi_shares')->where('dashboard_id', $dash->id)->whereNull('revoked_at')->where('expires_at', '>', now())
            ->orderByDesc('id')->get(['id', 'token', 'expires_at', 'views', 'last_viewed_at'])
            ->map(fn ($s) => (array) $s + ['url' => url("/share/{$s->token}")]));
    }

    public function share(Request $request, string $uuid): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);
        abort_unless($dash->owner_id === $request->user()->id || $request->user()->can('settings.manage') || $request->user()->can('reports.export'), 403);
        $data = $request->validate(['days' => ['required', 'integer', 'between:1,90'], 'filters' => ['nullable', 'array']]);
        $token = Str::random(48);
        DB::table('bi_shares')->insert(['company_id' => SalesCompany::id(), 'dashboard_id' => $dash->id, 'token' => $token, 'created_by' => $request->user()->id,
            'filters' => json_encode($data['filters'] ?? []), 'expires_at' => now()->addDays($data['days']), 'created_at' => now(), 'updated_at' => now()]);
        activity('sales')->causedBy($request->user())->withProperties(['dashboard' => $dash->name, 'days' => $data['days']])->log('dashboard share link created');

        return response()->json(['url' => url("/share/{$token}"), 'expires_at' => now()->addDays($data['days'])->toDateString()]);
    }

    public function revoke(Request $request, string $uuid, int $id): JsonResponse
    {
        $dash = $this->dashboard($request, $uuid);
        DB::table('bi_shares')->where('dashboard_id', $dash->id)->where('id', $id)->update(['revoked_at' => now()]);

        return response()->json(['ok' => true]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    private function dashboard(Request $request, string $uuid): object
    {
        abort_unless($request->user()->can('dashboards.view'), 403);
        $u = $request->user();
        $roles = $u->getRoleNames()->all();

        return DB::table('bi_dashboards')->where('company_id', SalesCompany::id())->where('uuid', $uuid)
            ->where(fn ($q) => $q->where('owner_id', $u->id)->orWhereNull('owner_id')->orWhere('visibility', 'organisation')
                ->orWhere(fn ($s) => $s->where('visibility', 'shared')->where(fn ($x) => $x->whereJsonContains('shared_users', $u->id)
                    ->orWhere(fn ($r) => array_map(fn ($role) => $r->orWhereJsonContains('shared_roles', $role), $roles ?: ['__none__'])))))
            ->firstOrFail();
    }

    private function userNames(array $ids): array
    {
        return User::whereIn('id', array_filter(array_unique($ids)))->get()->mapWithKeys(fn ($u) => [$u->id => $u->name])->all();
    }
}
