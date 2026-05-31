<?php

use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\PublicInboxController;
use App\Services\Seo\RobotsService;
use App\Services\Seo\SitemapService;
use App\Services\System\HealthCheckService;
use App\Services\System\LocaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('install')
    ->name('installer.')
    ->middleware('installer.accessible')
    ->group(function (): void {
        Route::get('/', [InstallerController::class, 'index'])->name('index');
        Route::get('/requirements', [InstallerController::class, 'requirements'])->name('requirements');
        Route::get('/environment', [InstallerController::class, 'environment'])->name('environment');
        Route::get('/database', [InstallerController::class, 'database'])->name('database');
        Route::get('/finish', [InstallerController::class, 'finish'])->name('finish');
        Route::post('/finish', [InstallerController::class, 'complete'])->name('complete');
    });

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/health', fn (HealthCheckService $health) => response()->json(
    $health->report()
))->name('health');

Route::get('/status', fn (HealthCheckService $health) => view('pages.status', [
    'status' => $health->publicStatus(),
]))->name('status');

Route::get('/sitemap.xml', fn (SitemapService $sitemap) => response($sitemap->xml(), 200, [
    'Content-Type' => 'application/xml',
    'Cache-Control' => 'public, max-age=3600',
]))->name('sitemap');

Route::get('/robots.txt', fn (RobotsService $robots) => response($robots->content(), 200, [
    'Content-Type' => 'text/plain',
    'Cache-Control' => 'public, max-age=3600',
]))->name('robots');

Route::post('/billing/webhooks/{provider}', BillingWebhookController::class)
    ->middleware('throttle:billing-webhooks')
    ->name('billing.webhooks.handle');

Route::get('/inbox', [PublicInboxController::class, 'index'])->name('inbox.index');
Route::post('/inbox/generate', [PublicInboxController::class, 'generate'])
    ->middleware('throttle:inbox-mailbox-generation')
    ->name('inbox.generate');
Route::post('/inbox/rotate', [PublicInboxController::class, 'rotate'])
    ->middleware('throttle:inbox-mailbox-rotation')
    ->name('inbox.rotate');
Route::post('/inbox/forget', [PublicInboxController::class, 'forget'])
    ->middleware('throttle:inbox-mailbox-rotation')
    ->name('inbox.forget');
Route::get('/inbox/messages', [PublicInboxController::class, 'messages'])
    ->middleware('throttle:inbox-message-polling')
    ->name('inbox.messages');
Route::get('/inbox/messages/{uuid}', [PublicInboxController::class, 'show'])
    ->middleware('throttle:inbox-message-detail')
    ->name('inbox.messages.show');

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
