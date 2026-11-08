<?php

use App\Http\Middleware\AuditRequests;
use App\Http\Middleware\AuthLock;
use App\Http\Middleware\CheckBanned;
use App\Http\Middleware\EnforceCrudPermissions;
use App\Http\Middleware\EnsureCompanyOwner;
use App\Http\Middleware\EnsureMasterAdmin;
use App\Http\Middleware\EnsureModuleIsActive;
use App\Http\Middleware\EnsureModuleTier;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureReseller;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SecurityCheck;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackRequestId;
use App\Http\Middleware\TwoFactor;
use App\Http\Middleware\WatermarkInstance;
use App\Modules\Tenancy\Console\BackfillTenantReferenceCommand;
use App\Modules\Tenancy\Console\CreateCompanyCommand;
use App\Modules\Tenancy\Console\ExpireTrials;
use App\Modules\Tenancy\Console\TrialReminders;
use App\Modules\Tenancy\Console\RunSubscriptionBilling;
use App\Modules\Tenancy\Http\Middleware\ResolveCompany;
use App\Modules\Tenancy\Http\Middleware\ResolveCompanyFromToken;
use App\Modules\Tenancy\Http\Middleware\ResolvePublicPortalCompany;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        \App\Modules\Tenancy\Console\CreateCompanyCommand::class,
        \App\Modules\Tenancy\Console\RunSubscriptionBilling::class,
        \App\Modules\Tenancy\Console\ExpireTrials::class,
        \App\Modules\Tenancy\Console\PurgeArchivedCompanies::class,
        \App\Modules\Tenancy\Console\TrialReminders::class,
        \App\Modules\Tenancy\Console\BackfillTenantReferenceCommand::class,
        \App\Modules\Sales\Console\RunMailboxes::class,
        \App\Modules\Sales\Console\SalesDemo::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // Sales Intelligence: each mailbox runs at its own configured time (default 03:30 SAST).
        $schedule->command(\App\Modules\Sales\Console\RunMailboxes::class)->everyMinute()->withoutOverlapping();

        // Platform subscriptions and trials (carried over from the hub).
        $schedule->command(\App\Modules\Tenancy\Console\RunSubscriptionBilling::class)->monthlyOn(1, '02:00')->withoutOverlapping();
        $schedule->command(\App\Modules\Tenancy\Console\ExpireTrials::class)->dailyAt('00:30')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/tenancy-trials.log'));
        $schedule->command(\App\Modules\Tenancy\Console\PurgeArchivedCompanies::class)->dailyAt('03:40')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/tenancy-purge.log'));
        $schedule->command(\App\Modules\Tenancy\Console\TrialReminders::class)->dailyAt('06:00')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/tenancy-trials.log'));
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->group('api', [
            'throttle:api',
            // One correlation id per request, echoed on the response and stamped on PM audit rows.
            TrackRequestId::class,
            // Multi-tenancy for API traffic. Without this the group carried no tenant at all:
            // CompanyScope did not filter, and an unset app.company_id makes the RLS policy permit
            // every row — so API endpoints were tenant-isolated only as far as each controller
            // remembered to filter. The tenant comes from the token's user, never a parameter.
            //
            // MUST run before SubstituteBindings: route model binding queries the database, so a
            // binding resolved before the tenant is pinned would happily load another tenant's
            // record by id.
            ResolveCompanyFromToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->web(remove: [
            // Re-inserted below, after the tenant is pinned. Laravel puts SubstituteBindings at the
            // end of the default web group, which is *before* anything appended here — so with
            // ResolveCompany appended in the usual way, every `{project}`, `{invoice}`, `{employee}`
            // binding on the site resolved with no active company: CompanyScope inactive, so a
            // foreign uuid in the URL loaded that tenant's record and the page rendered it. The
            // only thing standing between that and a leak was whatever each controller happened to
            // re-check. Same ordering bug as the api group above, three positions apart.
            SubstituteBindings::class,
        ], append: [
            TrackRequestId::class,
            // A browser's own background request (a service worker checking /sw.js) must never
            // become the page somebody is sent to after signing in or unlocking.
            \App\Http\Middleware\ForgetNonPageIntendedUrl::class,
            // Remembers the campaign a visitor arrived through, once, before anything else can
            // touch the session. UTMs were being stamped on every outbound link with nothing
            // reading them coming back, so attribution was built at one end only.
            // Multi-tenancy: pin the request to the authenticated user's company so
            // the CompanyScope filters data to that tenant. No-op for guests.
            ResolveCompany::class,
            SubstituteBindings::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetLocale::class,
            AuditRequests::class,
            WatermarkInstance::class,
            // Enforces the password-expiry policy (change every N months) and
            // account-lock. Self-guards: skips guests + the password-change /
            // logout / 2FA routes, so it's safe on public pages.
            SecurityCheck::class,
        ]);

        // Payment gateways post server-to-server notifications without a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'conferences/payment/*/notify',
            'conferences/payment/*/webhook',
            'pay/notify/*',
            // Ad platforms and tenant websites post conversions server to server, with no session
            // and therefore no CSRF token. The 48-character action token is the authorisation.
            'marketing/conversion/*',
            // AI Studio assistants embedded on a company's website: the visitor has no session here (and
            // third-party cookies are blocked in iframes). The assistant's public token, the allowed origins
            // and a per-conversation token are the authorisation; nothing private is reachable.
            'ai-studio/assistant/*',
            // Lead-form webhooks from Meta and Google: the HMAC signature / google_key is the authorisation.
            'marketing/leads/*',
            // Same for the sending provider's bounce and complaint notifications.
            'mail-events/*',
            // RFC 8058 one-click unsubscribe: the mail client posts this on the person's behalf
            // and carries no CSRF token. The per-recipient token is the authorisation.
            'outreach/unsubscribe/*',
            'crm/u/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'crud.permissions' => EnforceCrudPermissions::class,
            'module.active' => EnsureModuleIsActive::class,
            'module.tier' => EnsureModuleTier::class,
            // Reading a tenant's businesses added together, for one GET. See the middleware.
            // §12: gates one capability inside Procurement Governance, on top of the module gate.
            'platform.admin' => EnsurePlatformAdmin::class,
            'reseller' => EnsureReseller::class,
            'company.owner' => EnsureCompanyOwner::class,
            'company.resolve' => ResolveCompany::class,
            'portal.company' => ResolvePublicPortalCompany::class,
            'master.admin' => EnsureMasterAdmin::class,
            'auth.lock' => AuthLock::class,
            '2fa' => TwoFactor::class,
            'prevent-back-history' => PreventBackHistory::class,
            'check.banned' => CheckBanned::class,
            // Project Management API: refuse a request that resolved no tenant, and make a
            // mutation safe to retry when the client sends an Idempotency-Key.
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A platform administrator has no company of their own; creating a workflow, form or
        // calendar without switching into one gets a message, not a 500.
        $exceptions->render(function (App\Modules\Tenancy\Exceptions\TenantContextMissingException $e, Request $request) {
            if (! $request->is('workflow/*') || $request->expectsJson() || $request->isMethod('GET')) {
                return null;
            }

            return back()->with('error', 'Switch into a company first: workflows, forms and calendars belong to a company.');
        });

        // 419 "Page Expired" — never show it. A CSRF token only goes stale
        // because the session rotated underneath the open tab (idle lock,
        // session lifetime elapsed, login elsewhere, `php artisan ...` restart
        // in dev). The only useful recovery is to reload the page the user was
        // on, which mints a fresh token and lands them wherever the session
        // state now says they belong (e.g. the lock screen or login).
        //
        // Hooked on HttpException rather than TokenMismatchException because
        // Laravel's handler converts the latter to a 419 HttpException before
        // render callbacks run; returning null falls through to normal handling
        // for every other HTTP status.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            // Reload the page the request came from. Only trust a same-host
            // referer — an attacker-supplied one would turn this into an open
            // redirect — and fall back to the session's previous URL, then home.
            // (url()->previous() is not used as the fallback — it trusts the
            // referer too, which would defeat the host check.)
            $referer = (string) $request->headers->get('referer');
            $fallback = ($request->hasSession() ? $request->session()->previousUrl() : null) ?: url('/');
            $target = $referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()
                ? $referer
                : $fallback;

            // Inertia XHR: 409 + X-Inertia-Location tells the client to do a
            // full page visit — a real browser reload — instead of popping the
            // error-response modal that renders Laravel's 419 page.
            if ($request->header('X-Inertia')) {
                return Inertia::location($target);
            }

            // Plain XHR / fetch (axios interceptor in app.ts reloads on this).
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Your session expired. Reloading…'),
                    'reload' => true,
                ], 419);
            }

            return redirect()->to($target);
        });
    })
    ->create();
