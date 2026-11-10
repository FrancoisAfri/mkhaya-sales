<?php

use App\Core\AI\Controllers\AICopilotController;
use App\Core\Auth\Controllers\AuthController;
use App\Core\Security\AdminController;
use App\Modules\Dashboard\Controller\DashbaordController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// CACHE CLEAR ROUTE
Route::middleware(['auth', 'role:administrator'])->group(function () {
    Route::get('cache-clear', function () {
        Artisan::call('optimize:clear');
        request()->session()->flash('success', 'Successfully cache cleared.');

        return redirect()->back();
    })->name('cache.clear');

    // STORAGE LINKED ROUTE
    Route::get('storage-link', [AdminController::class, 'storageLink'])->name('storage.link');
});


require __DIR__.'/core/auth.php';

// ── LANGUAGE SWITCH (works for guests too — public portals/support pages) ──────
// Persists to the user record when logged in, otherwise just to the session so
// unauthenticated visitors can switch language without being bounced to login.
Route::middleware(['web'])->patch('/user/locale', function (\Illuminate\Http\Request $request) {
    $supported = ['en', 'af', 'zu', 'xh', 'nso', 'st', 'tn', 'ss', 've', 'ts', 'nr', 'fr'];
    $locale = $request->validate(['locale' => ['required', 'string', \Illuminate\Validation\Rule::in($supported)]])['locale'];

    // Persist for server-side use (emails, notifications) — logged-in users
    // get it remembered, guests keep it for the session. Fire-and-forget:
    // the UI has already switched client-side, so return 204 (no reload).
    if ($user = $request->user()) {
        $user->update(['locale' => $locale]);
    }
    $request->session()->put('locale', $locale);

    return response()->noContent();
})->name('user.locale');

// ── AUTHENTICATED ROUTES ────────────────────
Route::middleware(['auth', 'auth.lock', 'check.banned', 'prevent-back-history', 'crud.permissions'])->group(function () {

    // Landing page: the user's BI dashboard (falls back to the platform home without a company).
    Route::get('/', [\App\Modules\Sales\Http\Controllers\BIController::class, 'home'])->name('dashboard');
    Route::get('/home', [DashbaordController::class, 'index'])->name('dashboard.legacy');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // ── Notifications ──────────────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', function (string $id) {
            auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
            return redirect()->back();
        })->name('read');
        Route::post('/read-all', function () {
            auth()->user()->unreadNotifications->markAsRead();
            return redirect()->back();
        })->name('read-all');
    });

    Route::middleware('auth')->prefix('ai-copilot')->group(function () {
        Route::post('/chat', [AICopilotController::class, 'chat']);
        Route::get('/sessions', [AICopilotController::class, 'sessions']);
        Route::get('/history/{sessionId}', [AICopilotController::class, 'history']);
        Route::post('/new-session', [AICopilotController::class, 'newSession']);
        Route::delete('/sessions/{sessionId}', [AICopilotController::class, 'deleteSession']);
        Route::get('/quick-stats', [AICopilotController::class, 'quickStats']);
    });


    // Dashboard quick access
    Route::post('quick-access/store', [DashbaordController::class, 'storeQuickAccess'])->name('quick-access.store');
    Route::put('quick-access/update/{id}', [DashbaordController::class, 'updateQuickAccess'])->name('quick-access.update');
    Route::delete('quick-access/delete/{id}', [DashbaordController::class, 'destroyQuickAccess'])->name('quick-access.delete');
    Route::post('/quick-access/user/add', [DashbaordController::class, 'addToUser']);
    Route::match(['post', 'delete'], '/quick-access/user/remove', [DashbaordController::class, 'removeFromUser']);

    require __DIR__.'/modules/hubs.php';
    require __DIR__.'/modules/sales.php';
    require __DIR__.'/core/category.php';
    require __DIR__.'/core/module.php';
    require __DIR__.'/core/ribbon.php';
    require __DIR__.'/core/profile.php';

    // Platform super-admin — tenant management (top-level, not under settings/).
    require __DIR__.'/settings/tenancy.php';

    Route::prefix('settings')->name('settings.')->group(function () {
        require __DIR__.'/settings/setup.php';
        require __DIR__.'/settings/user-access.php';
        require __DIR__.'/settings/audit.php';
        require __DIR__.'/settings/roles.php';
        require __DIR__.'/settings/permissions.php';
        require __DIR__.'/settings/organization.php';
        require __DIR__.'/settings/position.php';
        require __DIR__.'/settings/platform-admin.php';
        require __DIR__.'/settings/theme.php';
    });

    Route::fallback(function () {
        return Inertia::render('Pages/Found/404');
    });
});

// Read-only dashboard share links — no login; the token is the key (gap 8).
Route::get('/share/{token}', [\App\Modules\Sales\Http\Controllers\BIShareController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:60,1,share.show')->name('share.show');
Route::post('/share/{token}/query', [\App\Modules\Sales\Http\Controllers\BIShareController::class, 'query'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:600,1,share.query')->name('share.query');

Route::post('/auth/sso/discover', [\App\Core\Auth\Controllers\SsoController::class, 'discover'])->name('sso.discover');
Route::get('/auth/sso/redirect', [\App\Core\Auth\Controllers\SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('/auth/sso/callback', [\App\Core\Auth\Controllers\SsoController::class, 'callback'])->name('sso.callback');
