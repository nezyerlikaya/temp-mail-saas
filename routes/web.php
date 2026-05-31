<?php

use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\Admin\OperationsCenterController;
use App\Http\Controllers\Admin\LocalizationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\MailProviderWebhookController;
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
    ->middleware(['app.installed', 'throttle:billing-webhooks'])
    ->name('billing.webhooks.handle');

Route::post('/webhooks/mailgun', MailProviderWebhookController::class)
    ->defaults('provider', 'mailgun')
    ->middleware(['app.installed', 'throttle:billing-webhooks'])
    ->name('webhooks.mailgun');
Route::post('/webhooks/postmark', MailProviderWebhookController::class)
    ->defaults('provider', 'postmark')
    ->middleware(['app.installed', 'throttle:billing-webhooks'])
    ->name('webhooks.postmark');
Route::post('/webhooks/ses', MailProviderWebhookController::class)
    ->defaults('provider', 'ses')
    ->middleware(['app.installed', 'throttle:billing-webhooks'])
    ->name('webhooks.ses');

Route::get('/inbox', [PublicInboxController::class, 'index'])
    ->middleware('app.installed')
    ->name('inbox.index');
Route::post('/inbox/generate', [PublicInboxController::class, 'generate'])
    ->middleware(['app.installed', 'throttle:inbox-mailbox-generation'])
    ->name('inbox.generate');
Route::post('/inbox/rotate', [PublicInboxController::class, 'rotate'])
    ->middleware(['app.installed', 'throttle:inbox-mailbox-rotation'])
    ->name('inbox.rotate');
Route::post('/inbox/forget', [PublicInboxController::class, 'forget'])
    ->middleware(['app.installed', 'throttle:inbox-mailbox-rotation'])
    ->name('inbox.forget');
Route::get('/inbox/messages', [PublicInboxController::class, 'messages'])
    ->middleware(['app.installed', 'throttle:inbox-message-polling'])
    ->name('inbox.messages');
Route::get('/inbox/messages/{uuid}', [PublicInboxController::class, 'show'])
    ->middleware(['app.installed', 'throttle:inbox-message-detail'])
    ->name('inbox.messages.show');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['app.installed', 'auth'])
    ->name('dashboard');

Route::get('/admin/login', fn () => redirect()->route('login'))
    ->middleware('app.installed')
    ->name('admin.login');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['app.installed', 'staff.active'])
    ->group(function (): void {
        Route::get('/', [OperationsCenterController::class, 'dashboard'])
            ->middleware('staff.permission:operations.view')
            ->name('index');
        Route::get('/operations', [OperationsCenterController::class, 'dashboard'])
            ->middleware('staff.permission:operations.view')
            ->name('operations');
        Route::get('/health', [OperationsCenterController::class, 'health'])
            ->middleware('staff.permission:health.view')
            ->name('health');
        Route::get('/queue', [OperationsCenterController::class, 'queue'])
            ->middleware('staff.permission:queue.view')
            ->name('queue');
        Route::get('/domains', [OperationsCenterController::class, 'domains'])
            ->middleware('staff.permission:domains.view')
            ->name('domains');
        Route::get('/abuse', [OperationsCenterController::class, 'abuse'])
            ->middleware('staff.permission:abuse.view')
            ->name('abuse');
        Route::get('/billing', [OperationsCenterController::class, 'billing'])
            ->middleware('staff.permission:billing.view')
            ->name('billing');
        Route::get('/audit', [OperationsCenterController::class, 'audit'])
            ->middleware('staff.permission:audit.view')
            ->name('audit');
        Route::get('/localization', [LocalizationController::class, 'index'])
            ->middleware('staff.permission:localization.view')
            ->name('localization');
        Route::get('/localization/languages', [LocalizationController::class, 'languages'])
            ->middleware('staff.permission:localization.view')
            ->name('localization.languages');
        Route::post('/localization/languages', [LocalizationController::class, 'storeLanguage'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.languages.store');
        Route::get('/localization/languages/{language}/edit', [LocalizationController::class, 'editLanguage'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.languages.edit');
        Route::put('/localization/languages/{language}', [LocalizationController::class, 'updateLanguage'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.languages.update');
        Route::patch('/localization/languages/{language}/activate', [LocalizationController::class, 'activate'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.languages.activate');
        Route::patch('/localization/languages/{language}/deactivate', [LocalizationController::class, 'deactivate'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.languages.deactivate');
        Route::patch('/localization/languages/{language}/default', [LocalizationController::class, 'makeDefault'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.languages.default');
        Route::delete('/localization/languages/{language}', [LocalizationController::class, 'destroyLanguage'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.languages.destroy');
        Route::get('/localization/translations', [LocalizationController::class, 'translations'])
            ->middleware('staff.permission:localization.view')
            ->name('localization.translations');
        Route::put('/localization/translations', [LocalizationController::class, 'updateTranslations'])
            ->middleware('staff.permission:localization.manage')
            ->name('localization.translations.update');
        Route::get('/localization/import', [LocalizationController::class, 'importForm'])
            ->middleware('staff.permission:localization.import')
            ->name('localization.import');
        Route::post('/localization/import', [LocalizationController::class, 'import'])
            ->middleware('staff.permission:localization.import')
            ->name('localization.import.store');
        Route::get('/localization/export', [LocalizationController::class, 'export'])
            ->middleware('staff.permission:localization.export')
            ->name('localization.export');
    });

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
