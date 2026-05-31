<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Reserved for future API endpoints. STEP01 intentionally exposes no API
| surface to preserve backwards-compatible route design.
|
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['app.installed', 'api.key'])
    ->group(function (): void {
        Route::get('/ping', fn () => response()->json([
            'ok' => true,
            'scope' => 'api-foundation',
        ]))->name('ping');
    });
