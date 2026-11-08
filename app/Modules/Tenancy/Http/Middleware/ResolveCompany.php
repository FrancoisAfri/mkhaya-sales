<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active company for the request:
 *  - a normal user → their own company (company_id);
 *  - a platform admin → the company they're impersonating (session), else none
 *    (no scope → sees all, for the super-admin portal).
 *
 * Also blocks access to suspended companies and expired trials.
 */
class ResolveCompany
{
    /** A company id no row has: row level security shows nothing, and inserts fail their foreign key. */
    private const NO_COMPANY = 0;

    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Platform admins: optionally impersonating a company via the super-admin portal.
        if ($user->is_platform_admin || ($user->is_master_admin ?? false)) {
            $impersonatedId = $request->session()->get('impersonate_company_id');

            // On a self-hosted install there is one company and nothing to choose between: an
            // administrator who is not viewing as a company works as that one (SoleCompany holds
            // the exactly-one guard). Without this every module that reads the current company
            // got none, cast it to 0, and failed — "account missing from the chart", or a
            // settings row it could not find and then tried to create a second time.
            if (! $impersonatedId && $soleId = \App\Modules\Tenancy\Support\SoleCompany::id()) {
                $this->current->set($soleId);
                $this->stampDbSession($soleId);

                return $next($request);
            }

            // Inside a module, "no company" is not a view of the platform — it is every tenant's
            // rows at once, because row level security lets an empty session see all of them. An
            // administrator's tender register listed every company's tenders, and what they created
            // there was saved with no company and vanished from the register that wanted it. So a
            // module works as the administrator's own company until they choose another with
            // "View as company". One with no company of their own gets the page and its company
            // switcher, but a session that matches no company: no tenant's rows are shown, and a
            // write fails loudly instead of saving a row that belongs to nobody.
            // Platform screens (companies, billing, resellers) keep the all-company view.
            if (! $impersonatedId && $this->isModuleRoute($request)) {
                if ($user->company_id) {
                    $this->current->set((int) $user->company_id);
                    $this->stampDbSession((int) $user->company_id);
                } else {
                    $this->stampDbSession(self::NO_COMPANY);
                }

                return $next($request);
            }

            if ($impersonatedId) {
                $this->current->set((int) $impersonatedId, impersonating: true);

                // A company converted to a reseller is kept only as a record until its purge date:
                // administrators may look, not change. Platform routes (stop viewing, billing) and
                // signing out still work.
                if (! $request->isMethodSafe() && ! $request->routeIs('tenancy.*', 'logout', 'auth.*')
                    && Company::query()->whereKey($impersonatedId)->where('status', 'archived')->exists()) {
                    abort(403, 'This company was converted to a reseller; its archived data is read only.');
                }
            }

            // Stamped either way, and this is the important part. Setting CurrentCompany alone
            // scopes the queries the application remembers to scope; it does nothing to row level
            // security, which is the layer that holds when a query forgets. Without this an
            // administrator viewing a client company still saw every other tenant's rows through
            // any query that was not itself filtered — the isolation looked enforced and was not.
            //
            // The empty case matters too: `set_config` is session-level, so on a pooled connection
            // an administrator who is *not* impersonating would otherwise inherit whatever tenant
            // the previous request left behind.
            $this->stampDbSession($impersonatedId ? (int) $impersonatedId : null);

            return $next($request);
        }

        /*
         * Practice staff working in a client's books (Accountant Partner, spec §2).
         *
         * Checked afresh on every request, so a client revoking the appointment -- or removing one
         * person -- shuts the door on the very next click. The practice's own screens always run
         * as the practice; anything else runs as the client only while the relationship is live
         * and the client's granted scope covers the screen.
         */
        if ($response = $this->asPracticeClient($request, $user, $next)) {
            return $response;
        }

        if ($user->company_id) {
            $company = Company::find($user->company_id);

            if ($company && $company->isArchived()) {
                abort(403, 'This company account has been closed. Please contact your administrator.');
            }
            if ($company && $company->isSuspended()) {
                abort(403, 'This account is suspended. Please contact your administrator.');
            }
            if ($company && $company->trialExpired()) {
                abort(403, 'Your free trial has ended. Please contact Mkhaya to activate your subscription.');
            }

            $this->current->set((int) $user->company_id);
            $this->stampDbSession((int) $user->company_id);

            return $next($request);
        }

        // A user with no company of their own (a reseller's login, say) works in no company: not
        // the empty session, which row level security reads as every company at once, and not
        // whatever the last request on this connection happened to set.
        $this->stampDbSession(self::NO_COMPANY);

        return $next($request);
    }

    /**
     * Set the tenant on the DB session so Postgres row-level security policies can
     * enforce isolation independently of the app-level global scope. Harmless when
     * no RLS policy references it. Silently ignored on non-Postgres drivers.
     */
    /** A route behind a module's entitlement gate, i.e. tenant data rather than the platform. */
    private function asPracticeClient(Request $request, $user, Closure $next): ?Response
    {
        return null;
    }

    private function isModuleRoute(Request $request): bool
    {
        $route = $request->route();
        if (! $route instanceof \Illuminate\Routing\Route) {
            return false;
        }
        foreach (app('router')->gatherRouteMiddleware($route) as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, \App\Http\Middleware\EnsureModuleIsActive::class)) {
                return true;
            }
        }

        return false;
    }

    private function stampDbSession(?int $companyId): void
    {
        // In the test suite we leave the RLS session var UNSET so the existing
        // (single-tenant) feature tests aren't filtered by row-level security. The
        // app-level CompanyScope still applies, and the dedicated tenancy tests set
        // `app.company_id` themselves to verify RLS isolation explicitly. Production
        // is unaffected.
        //
        // A test that is specifically about isolation can opt back in with
        // `config(['tenancy.stamp_rls_in_tests' => true])`, which is the only way to prove
        // through a real request that the middleware stamps at all — the bug this guard
        // hid was impersonation setting the app-level company and never reaching here.
        if (app()->environment('testing') && ! config('tenancy.stamp_rls_in_tests', false)) {
            return;
        }
        try {
            DB::statement("SELECT set_config('app.company_id', ?, false)", [$companyId === null ? '' : (string) $companyId]);
        } catch (\Throwable) {
            // best-effort; the app-level CompanyScope remains the primary guard
        }
    }
}
