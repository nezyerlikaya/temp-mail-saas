<?php

use App\Services\System\HealthCheckService;
use App\Services\System\LocaleService;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/health', fn (HealthCheckService $health) => response()->json(
    $health->report()
))->name('health');

Route::get('/status', fn (HealthCheckService $health) => view('pages.status', [
    'status' => $health->publicStatus(),
]))->name('status');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::get('/admin', function () {
    abort(403, 'Admin area reserved.');
})->name('admin.index');

Route::post('/locale', function (Request $request, LocaleService $locales) {
    $request->validate([
        'locale' => ['required', 'string', 'max:16'],
    ]);

    if (! $locales->storeLocaleInSession($request, $request->string('locale')->toString())) {
        return back()->withErrors([
            'locale' => 'The selected locale is not available.',
        ]);
    }

    $locales->setApplicationLocale($request->string('locale')->toString());

    return back();
})->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Reserved Route Spaces
|--------------------------------------------------------------------------
|
| /admin and /api are intentionally left unimplemented in STEP01. Future
| modules may register them without changing the current public routes.
|
*/

require __DIR__.'/auth.php';
