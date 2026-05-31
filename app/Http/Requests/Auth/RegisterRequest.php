<?php

namespace App\Http\Requests\Auth;

use App\Enums\AbuseEventType;
use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use App\Services\Abuse\AbuseLoggerService;
use App\Services\User\UsernameService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(UsernameService $usernames): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:32',
                'unique:users,username',
                function (string $attribute, mixed $value, Closure $fail) use ($usernames): void {
                    if (filled($value) && ! $usernames->isValid((string) $value)) {
                        $fail('The username must be 3-32 characters and may contain letters, numbers, dashes, and underscores. Reserved usernames are unavailable.');
                    }
                },
            ],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'website' => ['nullable', 'size:0'],
            'form_started_at' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('website')) {
                    app(AbuseLoggerService::class)->log(
                        AbuseEventType::RegistrationAttempt,
                        AbuseSeverity::High,
                        AbuseStatus::Blocked,
                        'Registration honeypot triggered.',
                        request: $this,
                        riskScore: 80,
                    );
                }

                $startedAt = (int) $this->input('form_started_at');
                $minimumSeconds = (int) config('auth_access.registration.minimum_submit_seconds', 2);

                if ($startedAt > 0 && now()->timestamp - $startedAt < $minimumSeconds) {
                    $validator->errors()->add('form', 'Please wait a moment and try again.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('username')) {
            $this->merge([
                'username' => app(UsernameService::class)->normalize($this->string('username')->toString()),
            ]);
        }
    }
}
