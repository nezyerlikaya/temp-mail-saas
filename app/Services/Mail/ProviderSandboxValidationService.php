<?php

namespace App\Services\Mail;

use App\Enums\InboundIntakeStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

final class ProviderSandboxValidationService extends Service
{
    public function __construct(
        private readonly ProviderRegistryService $providers,
        private readonly InboundMailIntakeService $intakes,
        private readonly EmailMessageStorageService $messages,
        private readonly PublicInboxMessageService $inbox,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function validate(?string $provider = null, ?string $fixture = null, bool $runAll = false): array
    {
        if (! (bool) config('mail-providers.sandbox.enabled', true)) {
            return $this->blocked('sandbox_disabled', 'Provider sandbox validation is disabled.');
        }

        $fixtures = $runAll ? $this->fixtures($provider) : [$fixture ?? "{$provider}-valid.json"];
        $results = collect($fixtures)
            ->filter()
            ->map(fn (string $path): array => $this->validateFixture($path, $provider))
            ->values()
            ->all();

        return [
            'status' => collect($results)->contains(fn (array $result): bool => $result['status'] === 'blocker') ? 'blocker' : 'passed',
            'results' => $results,
            'passed' => collect($results)->where('status', 'passed')->values()->all(),
            'blockers' => collect($results)->where('status', 'blocker')->values()->all(),
        ];
    }

    public function validateFixture(string $fixture, ?string $expectedProvider = null): array
    {
        try {
            $data = $this->fixtureData($fixture);
            $provider = $this->normalizeProvider($expectedProvider ?? (string) $data['provider']);
            $this->applySandboxSigningKey($provider);

            if (! $this->providerAllowed($provider)) {
                return $this->fixtureResult($fixture, $provider, 'blocker', 'Provider is not allowed for sandbox validation.');
            }

            $payload = Arr::wrap($data['payload'] ?? []);
            $headers = Arr::wrap($data['headers'] ?? []);
            $payload = $this->preparePayload($provider, $payload, (string) ($data['case'] ?? 'valid'));
            $headers = $this->prepareHeaders($provider, $headers, (string) ($data['case'] ?? 'valid'));
            $providerContract = $this->providers->resolve($provider);
            $signatureValid = $providerContract->verifySignature($headers, $payload);
            $expectedValid = ($data['case'] ?? 'valid') === 'valid';

            if ($signatureValid !== $expectedValid) {
                $this->record($provider, $signatureValid ? 'sandbox_provider_failed' : 'sandbox_signature_rejected');

                return $this->fixtureResult($fixture, $provider, 'blocker', 'Sandbox signature simulation did not match fixture expectation.');
            }

            if (! $signatureValid) {
                $this->record($provider, 'sandbox_signature_rejected');

                return $this->fixtureResult($fixture, $provider, 'passed', 'Invalid fixture was rejected safely.', [
                    'signature_valid' => false,
                ]);
            }

            $normalized = $providerContract->normalizePayload($payload);
            $missing = $this->missingNormalizedFields($normalized);

            if ($missing !== []) {
                $this->record($provider, 'sandbox_provider_failed');

                return $this->fixtureResult($fixture, $provider, 'blocker', 'Normalized payload is missing required fields.', [
                    'missing_fields' => $missing,
                ]);
            }

            Queue::fake();
            $before = InboundMailIntake::query()->count();
            $intake = $this->intakes->create($payload, $headers, '127.0.0.1', $provider);
            $duplicate = InboundMailIntake::query()->count() === $before;

            if ($duplicate) {
                $this->record($provider, 'sandbox_duplicate_detected');
            }

            if ($intake->status !== InboundIntakeStatus::Queued && ! $intake->isProcessed()) {
                $this->record($provider, 'sandbox_provider_failed');

                return $this->fixtureResult($fixture, $provider, 'blocker', 'Sandbox intake was not queued.', [
                    'intake_status' => $intake->status->value,
                ]);
            }

            if (! $intake->isProcessed()) {
                (new ProcessInboundMailIntake($intake->id))->handle($this->intakes, $this->messages);
            }

            $message = EmailMessage::query()->where('provider_id', $normalized['provider_id'])->first();

            if (! $message instanceof EmailMessage) {
                $this->record($provider, 'sandbox_provider_failed');

                return $this->fixtureResult($fixture, $provider, 'blocker', 'Sandbox message was not stored.');
            }

            $visible = $this->inbox->list($normalized['mailbox_address'])->contains('uuid', $message->uuid);
            $this->record($provider, $visible ? 'sandbox_provider_validated' : 'sandbox_provider_failed');

            return $this->fixtureResult($fixture, $provider, $visible ? 'passed' : 'blocker', $visible ? 'Sandbox mail flow reached inbox visibility.' : 'Sandbox message was not visible in inbox.', [
                'signature_valid' => true,
                'queued' => true,
                'stored' => true,
                'visible' => $visible,
                'duplicate' => $duplicate,
            ]);
        } catch (Throwable $exception) {
            $this->record((string) ($expectedProvider ?? 'unknown'), 'sandbox_provider_failed');

            return $this->fixtureResult($fixture, (string) ($expectedProvider ?? 'unknown'), 'blocker', 'Sandbox validation failed safely.');
        }
    }

    public function simulateExpiredFixture(string $provider): array
    {
        $provider = $this->normalizeProvider($provider);
        $fixture = "{$provider}-valid.json";
        $data = $this->fixtureData($fixture);
        $payload = $this->preparePayload($provider, Arr::wrap($data['payload'] ?? []), 'valid', now()->subSeconds((int) config('mail-providers.sandbox.replay_window_seconds', 300) + 60)->timestamp);
        $headers = $this->prepareHeaders($provider, Arr::wrap($data['headers'] ?? []), 'valid', (string) ($payload['timestamp'] ?? $payload['Timestamp'] ?? time()));

        return [
            'provider' => $provider,
            'signature_valid' => $this->providers->resolve($provider)->verifySignature($headers, $payload),
        ];
    }

    private function fixtures(?string $provider = null): array
    {
        $base = base_path('tests/Fixtures/mail-providers');
        $files = collect(File::files($base))->map(fn ($file): string => $file->getFilename())->all();

        if ($provider === null) {
            return $files;
        }

        $provider = $this->normalizeProvider($provider);

        return array_values(array_filter($files, fn (string $file): bool => str_starts_with($file, $provider.'-')));
    }

    private function fixtureData(string $fixture): array
    {
        $path = str_contains($fixture, DIRECTORY_SEPARATOR) ? $fixture : base_path('tests/Fixtures/mail-providers/'.$fixture);

        return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function preparePayload(string $provider, array $payload, string $case, int|string|null $timestamp = null): array
    {
        $timestamp ??= $provider === 'ses' ? now()->toIso8601String() : time();
        $payload = $this->replaceToken($payload, 'SANDBOX_TIMESTAMP', $timestamp);

        if ($case === 'valid' && (bool) config('mail-providers.sandbox.accept_test_signatures', true)) {
            $signature = $this->signature($provider, $payload);
            $payload = $this->replaceToken($payload, 'SANDBOX_SIGNATURE', $signature);
        }

        return $payload;
    }

    private function prepareHeaders(string $provider, array $headers, string $case, int|string|null $timestamp = null): array
    {
        $timestamp ??= time();
        $headers = $this->replaceToken($headers, 'SANDBOX_TIMESTAMP', $timestamp);

        if ($case === 'valid' && (bool) config('mail-providers.sandbox.accept_test_signatures', true)) {
            $headers = $this->replaceToken($headers, 'SANDBOX_SIGNATURE', $provider === 'postmark' ? $this->key($provider) : '');
        }

        return $headers;
    }

    private function signature(string $provider, array $payload): string
    {
        return match ($provider) {
            'mailgun' => hash_hmac('sha256', (string) $payload['timestamp'].(string) $payload['token'], $this->key($provider)),
            'ses' => hash_hmac('sha256', (string) ($payload['mail']['messageId'] ?? $payload['MessageId']).'|'.(string) $payload['Timestamp'], $this->key($provider)),
            default => $this->key($provider),
        };
    }

    private function key(string $provider): string
    {
        return (string) config("mail-providers.sandbox.test_signing_keys.{$provider}", 'sandbox-test-key');
    }

    private function applySandboxSigningKey(string $provider): void
    {
        $key = $this->key($provider);

        config(["mail-providers.providers.{$provider}.signing_key" => $key]);

        if ($provider === 'ses') {
            config(['mail-providers.providers.amazon_ses.signing_key' => $key]);
        }
    }

    private function replaceToken(array $value, string $token, int|string $replacement): array
    {
        return collect($value)
            ->map(fn (mixed $item): mixed => is_array($item)
                ? $this->replaceToken($item, $token, $replacement)
                : ($item === $token ? (string) $replacement : $item))
            ->all();
    }

    private function missingNormalizedFields(array $normalized): array
    {
        $required = [
            'mailbox_address',
            'from_email',
            'from_name',
            'subject',
            'text_body',
            'html_body',
            'recipients',
            'attachments',
            'received_at',
            'intake_source',
            'provider_id',
            'intake_key',
        ];

        return array_values(array_filter($required, fn (string $field): bool => ! array_key_exists($field, $normalized)));
    }

    private function providerAllowed(string $provider): bool
    {
        $allowed = array_map([$this, 'normalizeProvider'], (array) config('mail-providers.sandbox.allowed_providers', []));

        return in_array($provider, $allowed, true);
    }

    private function normalizeProvider(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }

    private function fixtureResult(string $fixture, string $provider, string $status, string $message, array $metadata = []): array
    {
        return [
            'fixture' => basename($fixture),
            'provider' => $provider,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata,
        ];
    }

    private function blocked(string $name, string $message): array
    {
        $result = [
            'fixture' => $name,
            'provider' => 'sandbox',
            'status' => 'blocker',
            'message' => $message,
            'metadata' => [],
        ];

        return [
            'status' => 'blocker',
            'results' => [$result],
            'passed' => [],
            'blockers' => [$result],
        ];
    }

    private function record(string $provider, string $eventType): void
    {
        if (! (bool) config('mail-providers.sandbox.observability_enabled', true)) {
            return;
        }

        $this->operations->log(
            OperationCategory::Mail,
            $eventType,
            str_contains($eventType, 'failed') || str_contains($eventType, 'rejected') ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'provider-sandbox',
            'Provider sandbox validation event recorded.',
            ['provider' => $provider],
        );
    }
}
