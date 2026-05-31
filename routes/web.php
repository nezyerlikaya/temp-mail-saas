<?php

use App\Services\System\HealthCheckService;
use App\Http\Controllers\DashboardController;
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
