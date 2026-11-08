<?php

use App\Core\Auth\Controllers\AuthController;
use App\Core\Permission\Controllers\PermissionController;
use App\Core\Role\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'crud.permissions', 'permission:manage roles'])->group(function () {
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
});

Route::middleware(['auth:sanctum', 'crud.permissions', 'permission:manage permissions'])->group(function () {
    Route::get('permissions', [PermissionController::class, 'index']);
});
