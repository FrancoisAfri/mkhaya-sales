<?php

use App\Modules\Sales\Http\Controllers\ImportController;
use App\Modules\Sales\Http\Controllers\LayoutController;
use App\Modules\Sales\Http\Controllers\MasterDataController;
use App\Modules\Sales\Http\Controllers\SalesRecordController;
use Illuminate\Support\Facades\Route;

/*
 * Vector Sales Intelligence. Every route is named sales.* and checks its own SRS §4.2 permission.
 * Required inside the authenticated group in routes/web.php.
 */
Route::prefix('sales')->name('sales.')->group(function () {
    // Master data (phase 2)
    Route::get('master/{resource}', [MasterDataController::class, 'index'])->name('master.index');
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
});
