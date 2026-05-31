<?php

use App\Console\Commands\CleanupExpiredMailCommand;
use App\Console\Commands\OperationsCollectMetricsCommand;
use App\Console\Commands\OperationsHealthSummaryCommand;
use App\Console\Commands\SystemHealthCheckCommand;
use App\Console\Commands\SystemReadinessCheckCommand;
use App\Console\Commands\SystemReleaseStatusCommand;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\EnsureInstallerAccessible;
use App\Http\Middleware\EnsureStaffHasPermission;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CleanupExpiredMailCommand::class,
        OperationsCollectMetricsCommand::class,
        OperationsHealthSummaryCommand::class,
        SystemHealthCheckCommand::class,
        SystemReadinessCheckCommand::class,
        SystemReleaseStatusCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'app.installed' => EnsureApplicationInstalled::class,
            'installer.accessible' => EnsureInstallerAccessible::class,
            'staff.active' => EnsureStaffIsActive::class,
            'staff.permission' => EnsureStaffHasPermission::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'billing/webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
