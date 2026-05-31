<?php

namespace App\Providers;

use App\Enums\AbuseEventType;
use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use App\Models\StaffUser;
use App\Services\Abuse\AbuseLoggerService;
use App\Services\Abuse\AbuseSignalService;
use App\Services\Abuse\RateLimitProfileService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
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

        $this->registerRateLimiter('inbox-mailbox-generation', AbuseEventType::MailboxGeneration);
        $this->registerRateLimiter('inbox-mailbox-rotation', AbuseEventType::MailboxRotation);
        $this->registerRateLimiter('inbox-message-polling', AbuseEventType::InboxPolling);
        $this->registerRateLimiter('inbox-message-detail', AbuseEventType::MessageDetail);
        $this->registerRateLimiter('auth-login-attempts', AbuseEventType::LoginAttempt);
        $this->registerRateLimiter('auth-registration-attempts', AbuseEventType::RegistrationAttempt);

        RateLimiter::for('billing-webhooks', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip() ?: 'billing-webhook');
        });
    }

    private function registerRateLimiter(string $name, AbuseEventType $type): void
    {
        RateLimiter::for($name, function (Request $request) use ($type): Limit {
            $profile = app(RateLimitProfileService::class)->for($type, $request->user());
            $key = app(AbuseSignalService::class)->limiterKey($request);

            return Limit::perMinute($profile['per_minute'])
                ->by($key)
                ->response(function () use ($request, $type): JsonResponse {
                    app(AbuseLoggerService::class)->log(
                        $type,
                        AbuseSeverity::Medium,
                        AbuseStatus::Throttled,
                        'Request cooldown applied.',
                        request: $request,
                        riskScore: 40,
                    );

                    return response()->json([
                        'message' => 'Too many requests. Please wait and try again.',
                    ], 429);
                });
        });
    }
}
