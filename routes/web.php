<?php

use App\Services\Core\FoundationService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
]))->name('health');

Route::get('/status', fn (FoundationService $foundation) => response()->json(
    $foundation->status()
))->name('status');

/*
|--------------------------------------------------------------------------
| Reserved Route Spaces
|--------------------------------------------------------------------------
|
| /admin and /api are intentionally left unimplemented in STEP01. Future
| modules may register them without changing the current public routes.
|
*/
