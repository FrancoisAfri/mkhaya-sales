<?php

use App\Modules\Sales\Http\Controllers\AIUsageController;
use App\Modules\Sales\Http\Controllers\BICollaborationController;
use App\Modules\Sales\Http\Controllers\BIController;
use App\Modules\Sales\Http\Controllers\DashboardController;
use App\Modules\Sales\Http\Controllers\ImportController;
use App\Modules\Sales\Http\Controllers\InsightsController;
use App\Modules\Sales\Http\Controllers\LayoutController;
use App\Modules\Sales\Http\Controllers\MailboxController;
use App\Modules\Sales\Http\Controllers\MasterDataController;
use App\Modules\Sales\Http\Controllers\ReportController;
use App\Modules\Sales\Http\Controllers\ReportScheduleController;
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
    Route::get('lookup/{resource}', [MasterDataController::class, 'lookup'])->middleware('throttle:240,1,sales.lookup')->name('lookup');
    Route::get('master/{resource}/export', [MasterDataController::class, 'export'])->name('master.export');
    Route::post('master/{resource}', [MasterDataController::class, 'store'])->name('master.store');
    Route::put('master/{resource}/{id}', [MasterDataController::class, 'update'])->whereNumber('id')->name('master.update');
    Route::delete('master/{resource}/{id}', [MasterDataController::class, 'destroy'])->whereNumber('id')->name('master.destroy');
    Route::post('master/{resource}/{id}/aliases', [MasterDataController::class, 'addAlias'])->whereNumber('id')->name('master.aliases.store');
    Route::delete('master/{resource}/{id}/aliases/{alias}', [MasterDataController::class, 'removeAlias'])->whereNumber('id')->name('master.aliases.destroy');

    // Data Import Centre (phase 3)
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template/{target}', [ImportController::class, 'template'])->name('imports.template');
    Route::post('imports', [ImportController::class, 'store'])->middleware('throttle:30,1,sales.imports.store')->name('imports.store');
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
    Route::post('mailboxes/{mailbox}/test', [MailboxController::class, 'test'])->middleware('throttle:10,1,sales.mailboxes.test')->name('mailboxes.test');
    Route::post('mailboxes/{mailbox}/run', [MailboxController::class, 'run'])->middleware('throttle:10,1,sales.mailboxes.run')->name('mailboxes.run');

    // Dashboards, forecast and reports (phase 6)
    Route::get('dashboard/{view?}', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('forecast', [DashboardController::class, 'forecast'])->name('forecast');
    Route::get('insights', [InsightsController::class, 'index'])->name('insights');
    Route::get('ai-usage', [AIUsageController::class, 'index'])->name('ai-usage');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export/{format}', [ReportController::class, 'export'])->middleware('throttle:30,1,sales.reports.export')->name('reports.export');
    Route::post('reports/saved', [ReportController::class, 'save'])->name('reports.save');
    Route::delete('reports/saved/{id}', [ReportController::class, 'destroy'])->whereNumber('id')->name('reports.saved.destroy');
    Route::get('reports/schedules', [ReportScheduleController::class, 'index'])->name('reports.schedules');
    Route::post('reports/schedules', [ReportScheduleController::class, 'store'])->name('reports.schedules.store');
    Route::put('reports/schedules/{id}', [ReportScheduleController::class, 'update'])->whereNumber('id')->name('reports.schedules.update');
    Route::delete('reports/schedules/{id}', [ReportScheduleController::class, 'destroy'])->whereNumber('id')->name('reports.schedules.destroy');
    Route::post('reports/schedules/{id}/send', [ReportScheduleController::class, 'send'])->whereNumber('id')->middleware('throttle:10,1,sales.reports.schedules.send')->name('reports.schedules.send');

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
    Route::post('bi/query', [BIController::class, 'query'])->middleware('throttle:600,1,sales.bi.query')->name('bi.query');
    Route::post('bi/ask', [BIController::class, 'ask'])->middleware('throttle:30,1,sales.bi.ask')->name('bi.ask');
    // Working together on dashboards (gaps 8, 9, 12).
    Route::get('bi/people', [BICollaborationController::class, 'people'])->name('bi.people');
    Route::get('bi/{uuid}/versions', [BICollaborationController::class, 'versions'])->name('bi.versions');
    Route::post('bi/{uuid}/versions/{id}/restore', [BICollaborationController::class, 'restore'])->whereNumber('id')->name('bi.versions.restore');
    Route::post('bi/{uuid}/certify', [BICollaborationController::class, 'certify'])->name('bi.certify');
    Route::get('bi/{uuid}/comments', [BICollaborationController::class, 'comments'])->name('bi.comments');
    Route::post('bi/{uuid}/comments', [BICollaborationController::class, 'comment'])->middleware('throttle:60,1,sales.bi.comment')->name('bi.comments.store');
    Route::post('bi/{uuid}/comments/{id}/resolve', [BICollaborationController::class, 'resolveComment'])->whereNumber('id')->name('bi.comments.resolve');
    Route::get('bi/{uuid}/shares', [BICollaborationController::class, 'shares'])->name('bi.shares');
    Route::post('bi/{uuid}/shares', [BICollaborationController::class, 'share'])->middleware('throttle:20,1,sales.bi.share')->name('bi.shares.store');
    Route::delete('bi/{uuid}/shares/{id}', [BICollaborationController::class, 'revoke'])->whereNumber('id')->name('bi.shares.revoke');
    Route::get('bi/status', [BIController::class, 'status'])->name('bi.status');
    Route::post('bi/measures', [BIController::class, 'saveMeasure'])->name('bi.measures');
    Route::get('bi/{uuid}', [BIController::class, 'show'])->whereUuid('uuid')->name('bi.show');
    Route::put('bi/{uuid}', [BIController::class, 'update'])->whereUuid('uuid')->name('bi.update');
    Route::delete('bi/{uuid}', [BIController::class, 'destroy'])->whereUuid('uuid')->name('bi.destroy');
    Route::post('bi/{uuid}/bookmarks', [BIController::class, 'saveBookmark'])->whereUuid('uuid')->name('bi.bookmarks');
});
