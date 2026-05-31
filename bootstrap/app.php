<?php

use App\Console\Commands\CleanupExpiredMailCommand;
use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\EnsureInstallerAccessible;
use App\Http\Middleware\EnsureStaffHasPermission;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CleanupExpiredMailCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'app.installed' => EnsureApplicationInstalled::class,
            'installer.accessible' => EnsureInstallerAccessible::class,
            'staff.active' => EnsureStaffIsActive::class,
            'staff.permission' => EnsureStaffHasPermission::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
