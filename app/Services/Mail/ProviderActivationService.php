<?php

namespace App\Services\Mail;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\ProviderActivationAudit;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProviderActivationService extends Service
{
    public function __construct(
        private readonly ProviderSafetyCheckService $safety,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function states(): array
    {
        $providers = (array) config('mail-providers.activation.readiness.providers', ['mailgun', 'postmark', 'ses']);

        return collect($providers)
            ->mapWithKeys(fn (string $provider): array => [$this->normalize($provider) => $this->state($this->normalize($provider))])
            ->all();
    }

    public function readiness(?string $provider = null): array
    {
        $safety = $this->safety->report($provider);
        $state = $provider !== null ? [$this->normalize($provider) => $this->state($this->normalize($provider))] : $this->states();

        return [
            'states' => $state,
            'ready' => $safety['blockers'] === [],
            'passed' => $safety['passed'],
            'warnings' => $safety['warnings'],
            'blockers' => $safety['blockers'],
            'checks' => $safety['checks'],
        ];
    }

    public function transition(
        string $provider,
        string $newState,
        ?string $reason = null,
        ?string $performedBy = null,
        array $metadata = [],
    ): ProviderActivationAudit {
        $provider = $this->normalize($provider);
        $previous = $this->state($provider);

        $this->record('provider_activation_requested', $provider);

        if (! in_array($newState, $this->allowedStates(), true)) {
            $this->record('provider_activation_blocked', $provider);

            throw ValidationException::withMessages([
                'state' => 'Provider activation state is not supported.',
            ]);
        }

        if (in_array($newState, ['ready', 'active'], true) && ! $this->safety->providerReady($provider)) {
            $this->record('provider_activation_blocked', $provider);

            throw ValidationException::withMessages([
                'provider' => 'Provider has activation blockers.',
            ]);
        }

        config(["mail-providers.activation.states.{$provider}" => $newState]);

        $audit = ProviderActivationAudit::query()->create([
            'provider' => $provider,
            'previous_state' => $previous,
            'new_state' => $newState,
            'reason' => $reason !== null ? Str::limit($reason, 255, '') : null,
            'performed_by' => $performedBy !== null ? Str::limit($performedBy, 255, '') : null,
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);

        $this->record(match ($newState) {
            'ready' => 'provider_activation_ready',
            'active' => 'provider_activation_completed',
            'suspended' => 'provider_activation_suspended',
            default => 'provider_activation_requested',
        }, $provider);

        return $audit;
    }

    private function state(string $provider): string
    {
        return (string) config("mail-providers.activation.states.{$provider}", 'inactive');
    }

    private function allowedStates(): array
    {
        return (array) config('mail-providers.activation.allowed_states', ['inactive', 'staging', 'ready', 'active', 'suspended']);
    }

    private function normalize(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => str_contains(Str::lower((string) $key), 'secret')
                || str_contains(Str::lower((string) $key), 'token')
                || str_contains(Str::lower((string) $key), 'password')
                || str_contains(Str::lower((string) $key), 'credential'))
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->sanitizeMetadata($value) : (is_scalar($value) || $value === null ? $value : null))
            ->all();
    }

    private function record(string $eventType, string $provider): void
    {
        if (! (bool) config('mail-providers.activation.readiness.metrics_enabled', true)) {
            return;
        }

        $this->operations->log(
            OperationCategory::Mail,
            $eventType,
            str_contains($eventType, 'blocked') ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'provider-activation',
            'Provider activation readiness event recorded.',
            ['provider' => $provider],
        );
    }
}
