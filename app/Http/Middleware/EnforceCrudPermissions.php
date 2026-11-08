<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Symfony\Component\HttpFoundation\Response;

class EnforceCrudPermissions
{
    /**
     * Routes that should bypass CRUD permission checks.
     * These are auth/session endpoints that should always remain reachable.
     */
    private const EXEMPT_ROUTE_NAMES = [
        'auth.logout',
        'lock.session',
        'login.unlock',
        'lock.screen',
        'auth.password.change',
        'auth.password.update',
        // Personal per-user preferences — any authenticated user may save their own.
        'settings.theme',
        'settings.theme.update',
        /*
         * Choosing which of the tenant's businesses you are working in.
         *
         * A POST, so the derived permission is the bare 'create' — which says "may create finance
         * records" and has nothing to do with this. Somebody with read-only access to Accounting
         * still has to be able to look at the other business's books, and refusing the switch
         * would leave them permanently inside whichever one they happen to have selected.
         *
         * Nothing is written but the selection itself, and CurrentEntity::set() refuses any entity
         * that is not this tenant's, so the only thing this exemption opens is the tenant's own
         * list of businesses.
         */
        'finance.accounting.entities.switch',
        // Dismissing your own notification is the same category, and was not treated as one. The
        // derived names are 'notifications.read' — which exists nowhere — and the generic 'create',
        // which no role in the catalogue holds, so marking a notification read returned 403 for
        // every user who was not a platform admin. It failed silently: the bell posts and re-reads
        // the list, so a refused request looks exactly like a notification that will not go away.
        'notifications.read',
        'notifications.read-all',
    ];

    /**
     * Route name prefixes whose routes are already protected by role middleware
     * and do not need an additional CRUD permission check.
     */
    private const EXEMPT_ROUTE_PREFIXES = [
        // Sales Intelligence routes carry their own permission middleware (SRS §4.2 permissions).
        'sales.',
        'hubs.',
    ];

    /**
     * Route name segments commonly used as CRUD-ish actions.
     */
    private const ACTION_SEGMENTS = [
        'index',
        'show',
        'create',
        'store',
        'edit',
        'update',
        'destroy',
        'delete',
    ];

    public function handle(Request $request, Closure $next, string ...$explicitPermissions): Response
    {
        $routeName = $request->route()?->getName();

        // The exemption lists exist because the permission name this middleware *derives* from a
        // route name is a guess, and a wrong guess locks people out of a module they are entitled
        // to. A permission named on the route itself is not a guess — it is the module stating its
        // own rule — so an exempt prefix must never suppress it. Otherwise adding
        // 'crud.permissions:procgov.plan.view' to an exempt route would silently drop the check
        // and open the route to every authenticated user.
        if ($explicitPermissions === []) {
            if ($routeName !== null && in_array($routeName, self::EXEMPT_ROUTE_NAMES, true)) {
                return $next($request);
            }

            if ($routeName !== null) {
                foreach (self::EXEMPT_ROUTE_PREFIXES as $prefix) {
                    if (str_starts_with($routeName, $prefix)) {
                        return $next($request);
                    }
                }
            }
        }

        $user = $request->user();

        if (! $user) {
            return $this->deny($request, 'Unauthenticated.', 401);
        }

        // Platform-level admins bypass all module CRUD gates.
        if ($user->is_platform_admin || $user->is_master_admin) {
            return $next($request);
        }

        // Resellers are platform-level actors with their own portal and no tenant CRUD
        // permissions. Let their portal routes through (the 'reseller' middleware guards
        // them) and funnel every other route to the portal instead of a 403 they can
        // never satisfy. Auth/session routes are already exempted above, so logging out
        // still works.
        if ($user->hasRole('reseller')) {
            if ($routeName !== null && str_starts_with($routeName, 'reseller.')) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return $this->deny($request, 'Resellers use the reseller portal.', 403);
            }

            return redirect()->route('reseller.dashboard');
        }

        $candidatePermissions = $this->resolveCandidatePermissions(
            request: $request,
            routeName: $routeName,
            explicitPermissions: $explicitPermissions
        );

        if ($candidatePermissions === []) {
            return $next($request);
        }

        if (! $this->userHasAnyPermission($user, $candidatePermissions, derived: $explicitPermissions === [])) {
            $expected = implode(', ', $candidatePermissions);

            return $this->deny(
                $request,
                "You do not have permission to perform this action. Expected one of: [{$expected}]",
                403
            );
        }

        return $next($request);
    }

    private function resolveCandidatePermissions(
        Request $request,
        ?string $routeName,
        array $explicitPermissions
    ): array {
        // Route-level override: ->middleware('crud.permissions:policies.approve')
        // Supports pipe/comma separated values for "any of" checks.
        if ($explicitPermissions !== []) {
            return $this->normalizePermissions($explicitPermissions);
        }

        $action = $this->actionFromMethod($request->method());
        if ($action === null) {
            return [];
        }

        $permissions = [];

        // Most specific check: exact route name can be used as a permission key.
        if ($routeName) {
            $permissions[] = $routeName;

            $base = $this->baseResourceFromRouteName($routeName);
            if ($base !== null) {
                $permissions[] = $base.'.'.$action;
            }
        }

        // Generic action permission (dynamic role/permission setup friendly).
        $permissions[] = $action;

        // Backward compatibility with legacy 3-permission model.
        if (in_array($action, ['create', 'update'], true)) {
            $permissions[] = 'modify';
        }

        // Backward compatibility for installations using 'update' as writable permission.
        if ($action === 'create') {
            $permissions[] = 'update';
        }

        return $this->normalizePermissions($permissions);
    }

    private function actionFromMethod(string $method): ?string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD', 'OPTIONS' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => null,
        };
    }

    private function baseResourceFromRouteName(string $routeName): ?string
    {
        $parts = explode('.', $routeName);
        $last = end($parts);

        if ($last === false) {
            return null;
        }

        if (in_array($last, self::ACTION_SEGMENTS, true)) {
            array_pop($parts);
            if ($parts === []) {
                return null;
            }

            return implode('.', $parts);
        }

        return $routeName;
    }

    private function normalizePermissions(array $permissions): array
    {
        $flattened = [];

        foreach ($permissions as $permission) {
            foreach (preg_split('/[|,]/', (string) $permission) ?: [] as $item) {
                $item = trim($item);
                if ($item !== '') {
                    $flattened[] = $item;
                }
            }
        }

        return array_values(array_unique($flattened));
    }

    /**
     * A derived name is a guess, and most guesses ('tenders.discovery.index', then
     * 'tenders.discovery.view') name permissions nobody defined before the generic 'view' that
     * does exist. Asking the user about each one logged "permission checked but never defined"
     * twice per page view, which buries the warnings that point at a real missing seed. Guesses
     * that do not exist are skipped quietly; a permission named on the route is still asked, so a
     * missing one there is still logged.
     */
    private function userHasAnyPermission($user, array $permissions, bool $derived = false): bool
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $guard = $user instanceof \Illuminate\Database\Eloquent\Model
            ? \Spatie\Permission\Guard::getDefaultName($user)
            : config('auth.defaults.guard');

        foreach ($permissions as $permission) {
            if ($derived && $registrar->getPermissions(['name' => $permission, 'guard_name' => $guard])->isEmpty()) {
                continue;
            }

            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                // Dynamic candidates may not exist yet; continue to next fallback.
            }
        }

        return false;
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        abort($status, $message);
    }
}
