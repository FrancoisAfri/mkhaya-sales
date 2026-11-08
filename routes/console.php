<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Licensing heartbeat (remote revocation) ──────────────────────────────────
// No-op unless LICENSE_REMOTE is enabled; checks in with the authority hourly.
Schedule::command(\App\Console\Commands\License\LicenseHeartbeat::class)
    ->hourly()
    ->withoutOverlapping();

// ── Per-tenant storage metering ──────────────────────────────────────────────
// Nightly recompute of each company's database data footprint (files are counted
// live). Powers the storage meter / quota. Heavy scan → off-peak, one server.
Schedule::command(\App\Console\Commands\Tenancy\RecomputeStorageUsage::class)
    ->dailyAt('01:15')
    ->onOneServer()
    ->withoutOverlapping();


