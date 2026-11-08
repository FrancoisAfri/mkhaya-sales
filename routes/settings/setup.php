<?php

use App\Modules\Admin\Setup\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// Company identity, API keys (incl. AI providers) and SMS configuration.
Route::post('/sms-config', [SetupController::class, 'updateSmsConfig'])->name('sms-config.update');
Route::get('/setup', [SetupController::class, 'index'])->name('setup.list');
Route::post('/setup/update', [SetupController::class, 'updateCompany'])->name('setup.update');
Route::post('/setup/api-keys', [SetupController::class, 'updateApiKeys'])->name('setup.api-keys.update');
Route::get('/branding/logo', [\App\Http\Controllers\BrandingController::class, 'logo'])->name('branding.logo');
