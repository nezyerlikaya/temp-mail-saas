<?php

namespace App\Providers;

use App\Models\StaffUser;
use Illuminate\Support\Facades\Gate;
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
    }
}
