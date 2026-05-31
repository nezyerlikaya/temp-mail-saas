<?php

namespace App\Providers;

use App\Models\StaffUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::guessPolicyNamesUsing(function (string $modelClass): string {
            return 'App\\Policies\\'.class_basename($modelClass).'Policy';
        });

        Gate::define('staff-permission', function (mixed $user, string $permission): bool {
            return $user instanceof StaffUser
                && $user->isActive()
                && $user->hasPermission($permission);
        });

        RateLimiter::for('inbox-mailbox-actions', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('inbox-message-polling', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('inbox-message-detail', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
