<?php

namespace App\Providers;

use App\Core\AI\Contracts\AIAgentInterface;
use App\Core\AI\Registry\AgentRegistry;
use App\Core\Compliance\Events\DataBreachReported;
use App\Core\Compliance\Listeners\NotifyOnDataBreach;
use App\Core\Permission\Repositories\EloquentPermissionRepository;
use App\Core\Permission\Repositories\PermissionRepositoryInterface;
use App\Core\Role\Repositories\EloquentRoleRepository;
use App\Core\Role\Repositories\RoleRepositoryInterface;
use App\Core\User\Repositories\EloquentUserRepository;
use App\Core\User\Repositories\UserRepositoryInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Memoises who is confined to which factories for the request; a global scope on a dozen
        // models reads it, so a fresh instance each time would be a query per query.


        // Multi-tenancy: one active-company holder per request.
        $this->app->singleton(\App\Modules\Tenancy\Services\CurrentCompany::class);
        $this->app->singleton(\App\Modules\Tenancy\Services\DemoMode::class);

        /*
         * One per request, as the company it sits beside is.
         *
         * CurrentEntity holds the trading entity this request is working in, including the
         * override `run()` pins for a console pass or a queued job. Resolved fresh each time, that
         * override would be set on one instance and read from another — the pin would appear to
         * work and change nothing.
         */

        // Project Management: one correlation id per request (stamped on every audit row), and one
        // permission evaluator so its per-request memoisation is actually shared.

        $this->app->singleton(AgentRegistry::class);

        // License verification is memoised per request — bind as a singleton.
        $this->app->singleton(\App\Core\License\LicenseService::class);

        // Marketplace adapter registry — providers register their adapters here.

        // Supplier due diligence: one provider registry per request, so a provider resolved once (or
        // faked in a test) is the same instance every later check sees.


        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            AIAgentInterface::class,
            fn() => app(AgentRegistry::class)->for('default')
        );

        $this->app->bind(
            RoleRepositoryInterface::class,
            EloquentRoleRepository::class
        );
        $this->app->bind(
            PermissionRepositoryInterface::class,
            EloquentPermissionRepository::class
        );

        // Make notification emails best-effort: a mail failure (SMTP down, over
        // limit, mis-configured) is logged and skipped instead of throwing, so
        // the database notification still saves and the request completes.
        // ChannelManager::createMailDriver() resolves MailChannel from the
        // container, so this swap applies to every `mail` notification app-wide.
        $this->app->bind(
            \Illuminate\Notifications\Channels\MailChannel::class,
            fn ($app) => new \App\Shared\Notifications\Channels\FaultTolerantMailChannel(
                $app->make(\Illuminate\Contracts\Mail\Factory::class),
                $app->make(\Illuminate\Mail\Markdown::class),
            )
        );

        // In-app notifications "must always go": deliver the database/broadcast
        // channels synchronously even for ShouldQueue notifications and with no
        // queue worker running. Mail and other channels are still queued.
        $this->app->singleton(
            \Illuminate\Notifications\ChannelManager::class,
            fn ($app) => new \App\Shared\Notifications\SyncInAppChannelManager($app)
        );
        $this->app->alias(
            \Illuminate\Notifications\ChannelManager::class,
            \Illuminate\Contracts\Notifications\Dispatcher::class
        );
        $this->app->alias(
            \Illuminate\Notifications\ChannelManager::class,
            \Illuminate\Contracts\Notifications\Factory::class
        );

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // Database-backed file storage: the 'database' filesystem driver stores
        // uploads in the tenant_files table (tenant-scoped by RLS) instead of disk.
        \Illuminate\Support\Facades\Storage::extend('database', function ($app, $config) {
            $adapter = new \App\Core\Storage\DatabaseFilesystemAdapter($config['disk_key'] ?? 'local');
            $flysystem = new \League\Flysystem\Filesystem($adapter, $config);

            return new \Illuminate\Filesystem\FilesystemAdapter($flysystem, $adapter, $config);
        });

        // Propagate the tenant to queued jobs: capture the active company on the
        // job payload at dispatch, and re-apply it (app scope + Postgres RLS) while
        // the job runs — so a job writes rows tagged to the right company instead
        // of NULL (invisible under RLS) and reads scoped to that tenant.
        \Illuminate\Support\Facades\Queue::createPayloadUsing(function () {
            return ['tenant_company_id' => app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id()];
        });
        //
        // What was active before the job is remembered and put back afterwards, rather than the
        // tenant simply being blanked. On a worker there is nothing to put back and the two are the
        // same — but on the sync connection the "job" runs inside somebody's request, and clearing
        // the tenant leaves the rest of that request writing rows with a null company, which row
        // level security then makes invisible.
        $tenantBeforeJob = [];

        Event::listen(\Illuminate\Queue\Events\JobProcessing::class, function ($event) use (&$tenantBeforeJob) {
            $tenantBeforeJob[spl_object_id($event->job)] = app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id();

            $cid = $event->job->payload()['tenant_company_id'] ?? null;
            app(\App\Modules\Tenancy\Services\TenantContext::class)->apply($cid !== null ? (int) $cid : null);
        });

        $restore = function ($event) use (&$tenantBeforeJob) {
            $key = spl_object_id($event->job);
            $before = $tenantBeforeJob[$key] ?? null;
            unset($tenantBeforeJob[$key]);

            app(\App\Modules\Tenancy\Services\TenantContext::class)->apply($before);
        };
        Event::listen(\Illuminate\Queue\Events\JobProcessed::class, $restore);
        Event::listen(\Illuminate\Queue\Events\JobFailed::class, $restore);

        // Delivery log for outbound notifications → powers the System monitor's
        // notification panel and per-company delivery reporting.
        $logNotification = function (string $channel, string $status, $notification, $notifiable, ?string $error) {
            if (! \Illuminate\Support\Facades\Schema::hasTable('notification_logs')) {
                return;
            }
            try {
                $cid = app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id();
                if (! $cid && is_object($notifiable) && isset($notifiable->company_id)) {
                    $cid = $notifiable->company_id;
                }
                \Illuminate\Support\Facades\DB::table('notification_logs')->insert([
                    'company_id'      => $cid,
                    'channel'         => \Illuminate\Support\Str::limit((string) $channel, 40, ''),
                    'status'          => $status,
                    'notification'    => $notification ? class_basename($notification) : null,
                    'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : null,
                    'notifiable_id'   => is_object($notifiable) && isset($notifiable->id) ? $notifiable->id : null,
                    'error'           => $error ? \Illuminate\Support\Str::limit($error, 500, '') : null,
                    'created_at'      => now(),
                ]);
            } catch (\Throwable) {
                // Logging must never break the actual notification flow.
            }
        };
        // SARS Connect: a mandate revoked or run out stops what was queued under it at once (spec §3.4).
        Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, function ($e) use ($logNotification) {
            $logNotification($e->channel, 'sent', $e->notification, $e->notifiable, null);
        });
        Event::listen(\Illuminate\Notifications\Events\NotificationFailed::class, function ($e) use ($logNotification) {
            $err = is_array($e->data ?? null) ? json_encode($e->data) : (string) ($e->data ?? 'failed');
            $logNotification($e->channel, 'failed', $e->notification, $e->notifiable, $err);
        });

        // Stamp every audit entry with the company it belongs to (active company,
        // else the causer's company) so the trail can be scoped per company.
        \Spatie\Activitylog\Models\Activity::creating(function ($activity) {
            // Guard: older migrations/seeders can log activity before the tenancy
            // migration adds activity_log.company_id — don't blow up in that window.
            if (! \Illuminate\Support\Facades\Schema::hasColumn('activity_log', 'company_id')) {
                return;
            }
            if (empty($activity->company_id)) {
                $cid = app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id();
                if (! $cid && $activity->causer_id) {
                    $cid = \App\Core\User\Models\User::whereKey($activity->causer_id)->value('company_id');
                }
                $activity->company_id = $cid;
            }
        });

        // Supply Chain & Procurement — policy guards

        // Supplier due diligence is a paid add-on, and the hub cards ask a Gate rather than an
        // entitlement service. Defined here so one answer serves the navigation, the routes and the
        // panels — a gate checked in three places is one that eventually differs in one of them.

        /*
         * Assets — every one of these was written and never registered.
         *
         * Laravel's discovery looks for App\Policies\{Model}Policy, and these live beside their
         * module, so nothing connected them: `$user->can('update', $asset)` never consulted
         * AssetPolicy at all. It answered false for anyone without a bare `update` permission —
         * and true for anyone with one, because Spatie's Gate::before grants any ability whose
         * name matches a held permission. Either way the policy's actual rules never ran, so a
         * disposed asset was editable and the module's authorisation was decorative.
         */
        // NOTE: AssetApproval is claimed by both AssetApprovalPolicy and AssetDisposalPolicy, and
        // a model can have only one. Left unregistered until somebody decides which governs it.

        // Operations & Support — Project Management

        // Manufacturing & Production (MFG-001/002). FactoryPolicyRules rather than FactoryPolicy,
        // because FactoryPolicy is a model here -- the tolerances a factory is configured with.
        // MFG-017: finished stock held for a sales line is let go as Finance delivers, invoices or
        // cancels the order, and when warehouse fulfilment takes the line over for picking.
        // Keep the reusable conference attendee repository in sync with new registrations.

        // Auto-generate baseline how-to content when a new ribbon (feature) ships,
        // so the Mkhaya AI bot / how-to page can answer about it immediately.
        \App\Core\Ribbon\Models\Ribbon::observe(\App\Core\Ribbon\Observers\RibbonObserver::class);

        // Fleet spending reaches the budget as it is recorded. An observer rather than four calls
        // across two controllers, because the write path that gets missed is the one that leaves a
        // budget quietly short.

        // A contract commits the budget the moment both parties have signed. Hung off the envelope
        // because signature completion happens inside ESign, but owned by the contract module so
        // ESign keeps knowing nothing about contracts or budgets.
        // Contract compliance at the points where money is promised or paid (orders, invoices, requisitions).

        // An employee added to a client's payroll earns leave from their first day, so they get a
        // leave profile the moment they are created — accrual is gated on that profile existing, and
        // without it they silently accrue nothing. Hung off the payroll employee so the Payroll
        // Bureau keeps knowing nothing about leave, and so every creation path is covered rather
        // than only the ones somebody remembered to wire.


        // Register Azure AD Socialite provider
        Event::listen(SocialiteWasCalled::class, \SocialiteProviders\Azure\AzureExtendSocialite::class . '@handle');

        // Default limiter for the `api` middleware group (referenced by
        // bootstrap/app.php as throttle:api). Keyed by authenticated user, else IP.
        RateLimiter::for('api', fn (Request $r) =>
            Limit::perMinute(120)->by($r->user()?->id ?: $r->ip())
        );

        RateLimiter::for('recruitment.cv-upload', fn (Request $r) =>
            Limit::perMinute(5)->by($r->user()?->id ?: $r->ip())
        );

        RateLimiter::for('recruitment.ai-scoring', fn (Request $r) =>
            Limit::perMinute(10)->by($r->user()?->id ?: $r->ip())
        );

        RateLimiter::for('esign.otp-request', fn (Request $r) =>
            Limit::perMinute(5)->by($r->ip())
        );

        RateLimiter::for('esign.otp-verify', fn (Request $r) =>
            Limit::perMinute(10)->by($r->ip())
        );

        // A customer answering a quote online: counted per link and visitor, so asking for a code
        // and typing it in are not spent by pages viewed elsewhere. Codes are sent more sparingly.
        RateLimiter::for('quote.answer', fn (Request $r) =>
            Limit::perMinute(10)->by('quote-answer|'.$r->route('token').'|'.$r->ip())
        );
        RateLimiter::for('quote.code', fn (Request $r) =>
            Limit::perMinutes(10, 4)->by('quote-code|'.$r->route('token').'|'.$r->ip())
        );

        /*
         * The candidate offer and contract-signing portals.
         *
         * Both were named on their routes and defined nowhere, and Laravel throws
         * MissingRateLimiterException rather than waving an unknown limiter through — so requesting
         * an OTP, accepting an offer and signing a contract all answered 500. The candidate side of
         * hiring could not be completed at all.
         *
         * Keyed by IP and the offer or signature request in the URL, not by IP alone: a recruitment
         * agency sending ten offers from one office would otherwise lock out its own candidates.
         * The allowances match the e-sign portal above, which is the same shape of flow.
         */
        RateLimiter::for('recruitment.otp-request', fn (Request $r) =>
            Limit::perHour(3)->by($r->ip() . '|' . $r->route('offerUuid', $r->route('signatureRequestUuid', '')))
        );

        RateLimiter::for('recruitment.otp-verify', fn (Request $r) =>
            Limit::perMinute(10)->by($r->ip() . '|' . $r->route('offerUuid', $r->route('signatureRequestUuid', '')))
        );

        Event::listen('auth.inactivity.logout', function ($user) {
            Log::info("User {$user->id} ({$user->name}) logged out due to inactivity.");
        });
        Event::listen(DataBreachReported::class, NotifyOnDataBreach::class);

        \App\Modules\Tenancy\Support\DemoGuards::register();

        // Mirror every outgoing email (raw Mailables / Mail::send) into an in-app
        // notification for recipients who are users. Notification-originated mail
        // is skipped — those already create their own in-app row.
        Event::listen(
            \Illuminate\Mail\Events\MessageSending::class,
            \App\Shared\Listeners\MirrorEmailToNotification::class
        );

        /*
         * Every outgoing email, written down: what it was, who it was for, and whether the
         * transport took it. Logged on the way in and completed on the way out, because a transport
         * that throws produces MessageSending and never MessageSent — so a failed send leaves a
         * record of itself without anything having to catch it.
         */
        Event::listen(
            \Illuminate\Mail\Events\MessageSending::class,
            [\App\Shared\Listeners\RecordEmailDelivery::class, 'sending']
        );
        Event::listen(
            \Illuminate\Mail\Events\MessageSent::class,
            [\App\Shared\Listeners\RecordEmailDelivery::class, 'sent']
        );

        $this->loadMigrationsFrom([
            database_path('migrations'),

            // Core tables first: users, roles, companies, base setup
            database_path('migrations/core'),

            // Compliance infrastructure (depends on users)
            database_path('migrations/compliance'),

            // HR depends on users/core
            database_path('migrations/hr'),

            // Security depends on users/roles/permissions
            database_path('migrations/security'),

            // Settings/configuration after core/security
            database_path('migrations/settings'),

            // Remaining modules
            database_path('migrations/ai'),
            database_path('migrations/assets'),
            database_path('migrations/audit'),
            database_path('migrations/documents'),
            database_path('migrations/leaves'),
            database_path('migrations/policies'),
            database_path('migrations/resources'),
            database_path('migrations/contentManagement'),
            database_path('migrations/recruitment'),
            database_path('migrations/recruitment_screening'),
            database_path('migrations/employee_reputation'),
            database_path('migrations/helpdesk'),
            database_path('migrations/tender_pursuit'),
            database_path('migrations/attendance'),
            database_path('migrations/fleet'),
            database_path('migrations/performance'),
            database_path('migrations/payroll'),

            // Leave Bureau builds on the payroll bureau's client companies and employees,
            // so it has to run after them.
            database_path('migrations/leave_bureau'),
            database_path('migrations/disciplinary'),
            database_path('migrations/lms'),
            database_path('migrations/governance'),
            database_path('migrations/offboarding'),

            // Finance modules
            database_path('migrations/finance'),
            database_path('migrations/claims'),
            database_path('migrations/petty_cash'),
            database_path('migrations/staff_loan'),

            // HR — Whistleblower
            database_path('migrations/whistleblower'),

            // Surveys & Recognition
            database_path('migrations/surveys'),
            database_path('migrations/recognition'),

            // E-Sign — standalone document signing module
            database_path('migrations/esign'),

            // Supply Chain & Procurement
            database_path('migrations/supplier_management'),
            database_path('migrations/contract_management'),
            database_path('migrations/budget_management'),
            database_path('migrations/procurement'),
            // Procurement Governance sits on top of the four above (it links to their records
            // without constraining to them), so it loads after them.
            database_path('migrations/procurement_governance'),
            database_path('migrations/marketplace'),

            // Operations & Support — Conferences
            database_path('migrations/conferences'),

            // Operations & Support — Project Management (depends on core + tenancy)
            database_path('migrations/project_management'),

            // Operations & Support — SHEQ (depends on core, tenancy, HR, LMS, Fleet, Assets)
            database_path('migrations/sheq'),

            // Operations & Support — Manufacturing & Production. Last because it extends the
            // finance item master (fin_stock_items, fin_warehouses) and the asset register.
            database_path('migrations/manufacturing'),

            // Workflow & Automation — the central engine every module can route work through.
            // Last: it references users, company units and HR, and modules call into it.
            database_path('migrations/workflow'),
        ]);


    }
}
