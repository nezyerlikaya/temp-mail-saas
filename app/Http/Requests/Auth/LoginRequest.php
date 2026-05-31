<?php

namespace App\Http\Requests\Auth;

use App\Enums\AbuseEventType;
use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use App\Enums\UserStatus;
use App\Services\Abuse\AbuseLoggerService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'status' => UserStatus::Active->value,
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), (int) config('auth_access.login.decay_seconds', 60));
            app(AbuseLoggerService::class)->log(
                AbuseEventType::LoginAttempt,
                AbuseSeverity::Low,
                AbuseStatus::Observed,
                'Failed login attempt.',
                request: $this,
            );

            throw ValidationException::withMessages([
                'email' => __('The provided credentials are incorrect.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        $maxAttempts = (int) config('auth_access.login.max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts)) {
            return;
        }

        event(new Lockout($this));
        app(AbuseLoggerService::class)->log(
            AbuseEventType::LoginAttempt,
            AbuseSeverity::Medium,
            AbuseStatus::Throttled,
            'Login cooldown applied.',
            request: $this,
            riskScore: 40,
        );

        throw ValidationException::withMessages([
            'email' => __('Too many login attempts. Please try again later.'),
        ]);
    }

    public function throttleKey(): string
    {
        return hash_hmac(
            'sha256',
            Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip()),
            (string) config('abuse.hash_salt', config('app.key', 'local-abuse-salt')),
        );
    }
}
