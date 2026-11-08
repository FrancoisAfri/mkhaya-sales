<?php

use App\Modules\Sales\Http\Controllers\BIController;
use App\Modules\Sales\Http\Controllers\DashboardController;
use App\Modules\Sales\Http\Controllers\ImportController;
use App\Modules\Sales\Http\Controllers\InsightsController;
use App\Modules\Sales\Http\Controllers\LayoutController;
use App\Modules\Sales\Http\Controllers\MailboxController;
use App\Modules\Sales\Http\Controllers\MasterDataController;
use App\Modules\Sales\Http\Controllers\ReportController;
use App\Modules\Sales\Http\Controllers\SalesRecordController;
use App\Modules\Sales\Http\Controllers\TargetController;
use Illuminate\Support\Facades\Route;

/*
 * Vector Sales Intelligence. Every route is named sales.* and checks its own SRS §4.2 permission.
 * Required inside the authenticated group in routes/web.php.
 */
Route::prefix('sales')->name('sales.')->middleware(\App\Modules\Sales\Http\Middleware\RequireSalesCompany::class)->group(function () {
    // Master data (phase 2)
    Route::get('master/{resource}', [MasterDataController::class, 'index'])->name('master.index');
    Route::get('lookup/{resource}', [MasterDataController::class, 'lookup'])->middleware('throttle:240,1')->name('lookup');
    Route::get('master/{resource}/export', [MasterDataController::class, 'export'])->name('master.export');
    Route::post('master/{resource}', [MasterDataController::class, 'store'])->name('master.store');
    Route::put('master/{resource}/{id}', [MasterDataController::class, 'update'])->whereNumber('id')->name('master.update');
    Route::delete('master/{resource}/{id}', [MasterDataController::class, 'destroy'])->whereNumber('id')->name('master.destroy');
    Route::post('master/{resource}/{id}/aliases', [MasterDataController::class, 'addAlias'])->whereNumber('id')->name('master.aliases.store');
    Route::delete('master/{resource}/{id}/aliases/{alias}', [MasterDataController::class, 'removeAlias'])->whereNumber('id')->name('master.aliases.destroy');

    // Data Import Centre (phase 3)
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template/{target}', [ImportController::class, 'template'])->name('imports.template');
    Route::post('imports', [ImportController::class, 'store'])->middleware('throttle:30,1')->name('imports.store');
    Route::get('imports/{batch}', [ImportController::class, 'show'])->name('imports.show');
    Route::put('imports/{batch}', [ImportController::class, 'update'])->name('imports.update');
    Route::post('imports/{batch}/source-totals', [ImportController::class, 'sourceTotals'])->name('imports.source-totals');
    Route::post('imports/{batch}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
    Route::post('imports/{batch}/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');
    Route::post('imports/{batch}/reprocess', [ImportController::class, 'reprocess'])->name('imports.reprocess');
    Route::get('imports/{batch}/errors', [ImportController::class, 'errors'])->name('imports.errors');
    Route::get('imports/{batch}/file', [ImportController::class, 'download'])->name('imports.file');

    // File layouts — any report format, no code (phase 3)
    Route::get('layouts', [LayoutController::class, 'index'])->name('layouts.index');
    Route::post('layouts/inspect', [LayoutController::class, 'inspect'])->name('layouts.inspect');
    Route::post('layouts', [LayoutController::class, 'store'])->name('layouts.store');
    Route::put('layouts/{layout:uuid}', [LayoutController::class, 'update'])->name('layouts.update');
    Route::delete('layouts/{layout:uuid}', [LayoutController::class, 'destroy'])->name('layouts.destroy');

    // Sales records and corrections (phase 4)
    Route::get('records', [SalesRecordController::class, 'index'])->name('records.index');
    Route::get('records/export', [SalesRecordController::class, 'export'])->name('records.export');
    Route::get('records/{id}', [SalesRecordController::class, 'show'])->whereNumber('id')->name('records.show');
    Route::post('records/{id}/adjust', [SalesRecordController::class, 'adjust'])->whereNumber('id')->name('records.adjust');
    Route::post('adjustments/{id}/decide', [SalesRecordController::class, 'decide'])->whereNumber('id')->name('adjustments.decide');

    // Mailbox setup and automated daily retrieval (phase 5)
    Route::get('mailboxes', [MailboxController::class, 'index'])->name('mailboxes.index');
    Route::post('mailboxes', [MailboxController::class, 'store'])->name('mailboxes.store');
    Route::put('mailboxes/{mailbox}', [MailboxController::class, 'update'])->name('mailboxes.update');
    Route::post('mailboxes/{mailbox}/test', [MailboxController::class, 'test'])->middleware('throttle:10,1')->name('mailboxes.test');
    Route::post('mailboxes/{mailbox}/run', [MailboxController::class, 'run'])->middleware('throttle:10,1')->name('mailboxes.run');

    // Dashboards, forecast and reports (phase 6)
    Route::get('dashboard/{view?}', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('forecast', [DashboardController::class, 'forecast'])->name('forecast');
    Route::get('insights', [InsightsController::class, 'index'])->name('insights');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export/{format}', [ReportController::class, 'export'])->middleware('throttle:30,1')->name('reports.export');
    Route::post('reports/saved', [ReportController::class, 'save'])->name('reports.save');
    Route::delete('reports/saved/{id}', [ReportController::class, 'destroy'])->whereNumber('id')->name('reports.saved.destroy');

    // Targets and the approvals matrix (phase 7)
    Route::get('targets', [TargetController::class, 'index'])->name('targets.index');
    Route::get('targets/approvals', [TargetController::class, 'approvals'])->name('targets.approvals');
    Route::get('targets/matrix', [TargetController::class, 'matrix'])->name('targets.matrix');
    Route::get('targets/alerts', [TargetController::class, 'alerts'])->name('targets.alerts');
    Route::post('targets/alerts', [TargetController::class, 'saveAlerts'])->name('targets.alerts.save');
    Route::post('targets/matrix', [TargetController::class, 'saveRule'])->name('targets.matrix.store');
    Route::put('targets/matrix/{id}', [TargetController::class, 'saveRule'])->whereNumber('id')->name('targets.matrix.update');
    Route::delete('targets/matrix/{id}', [TargetController::class, 'deleteRule'])->whereNumber('id')->name('targets.matrix.destroy');
    Route::post('targets', [TargetController::class, 'store'])->name('targets.store');
    Route::get('targets/{target}', [TargetController::class, 'show'])->name('targets.show');
    Route::put('targets/{target}', [TargetController::class, 'update'])->name('targets.update');
    Route::post('targets/{target}/submit', [TargetController::class, 'submit'])->name('targets.submit');
    Route::post('targets/{target}/decide', [TargetController::class, 'decide'])->name('targets.decide');
    Route::post('targets/{target}/revise', [TargetController::class, 'revise'])->name('targets.revise');
    Route::post('targets/{target}/allocate', [TargetController::class, 'allocate'])->name('targets.allocate');
    Route::post('targets/{target}/archive', [TargetController::class, 'archive'])->name('targets.archive');

    // BI dashboards and builder
    Route::get('bi', [BIController::class, 'index'])->name('bi.index');
    Route::post('bi', [BIController::class, 'store'])->name('bi.store');
    Route::post('bi/query', [BIController::class, 'query'])->middleware('throttle:600,1')->name('bi.query');
    Route::get('bi/status', [BIController::class, 'status'])->name('bi.status');
    Route::post('bi/measures', [BIController::class, 'saveMeasure'])->name('bi.measures');
    Route::get('bi/{uuid}', [BIController::class, 'show'])->whereUuid('uuid')->name('bi.show');
    Route::put('bi/{uuid}', [BIController::class, 'update'])->whereUuid('uuid')->name('bi.update');
    Route::delete('bi/{uuid}', [BIController::class, 'destroy'])->whereUuid('uuid')->name('bi.destroy');
    Route::post('bi/{uuid}/bookmarks', [BIController::class, 'saveBookmark'])->whereUuid('uuid')->name('bi.bookmarks');
});
