<?php

use App\Modules\Sales\Http\Controllers\MasterDataController;
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
});
