<?php

namespace App\Services\Mail;

use App\Contracts\Mail\InboundProviderContract;
use App\Enums\InboundIntakeStatus;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\InboundMailIntake;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InboundMailIntakeService extends Service
{
    public function __construct(
        private readonly ProviderRegistryService $providers,
        private readonly InboundProviderMetricsService $metrics,
    ) {}

    public function create(array $payload, array $headers = [], ?string $sourceIp = null, ?string $provider = null): InboundMailIntake
    {
        $providerContract = $this->provider($provider);
        $providerName = $providerContract->provider();
        $providerMessageId = $payload['provider_message_id'] ?? $payload['provider_id'] ?? null;
        $intakeKey = $payload['intake_key'] ?? $this->payloadIntakeKey($providerName, $payload);

        if ($duplicate = $this->duplicate($providerName, $providerMessageId, $intakeKey)) {
            return $duplicate;
        }

        return DB::transaction(function () use ($payload, $headers, $sourceIp, $providerContract, $providerName, $providerMessageId, $intakeKey): InboundMailIntake {
            $this->metrics->intake($providerName);

            $intake = InboundMailIntake::query()->create([
                'uuid' => (string) Str::uuid(),
                'provider' => $providerName,
                'provider_message_id' => $providerMessageId,
                'intake_key' => $intakeKey,
                'signature_valid' => false,
                'status' => InboundIntakeStatus::Received->value,
                'source_ip_hash' => $this->hashSourceIp($sourceIp),
                'headers_json' => $headers,
                'payload_json' => $payload,
                'normalized_payload_json' => null,
            ]);

            $signatureValid = $providerContract->verifySignature($headers, $payload);

            $intake->forceFill([
                'signature_valid' => $signatureValid,
                'signature_checked_at' => now(),
                'status' => $signatureValid ? InboundIntakeStatus::Verified->value : InboundIntakeStatus::Rejected->value,
                'error_message' => $signatureValid ? null : 'Inbound signature verification failed.',
            ])->save();

            if (! $signatureValid) {
                $this->metrics->rejection($providerName);

                return $intake->refresh();
            }

            $intake->markQueued();

            ProcessInboundMailIntake::dispatch($intake->id)
                ->onConnection(config('inbound.queue.connection', config('queue.default')))
                ->onQueue(config('inbound.queue.name', 'inbound-mail'));

            return $intake->refresh();
        });
    }

    public function provider(?string $provider = null): InboundProviderContract
    {
        return $this->providers->resolve($provider ?? (string) config('inbound.default_provider', 'local'));
    }

    public function hashSourceIp(?string $sourceIp): ?string
    {
        if (blank($sourceIp)) {
            return null;
        }

        return hash_hmac('sha256', $sourceIp, (string) config('inbound.storage.source_ip_hash_salt', 'local-inbound'));
    }

    private function duplicate(string $provider, ?string $providerMessageId, ?string $intakeKey): ?InboundMailIntake
    {
        if (filled($providerMessageId)) {
            $intake = InboundMailIntake::query()
                ->where('provider', $provider)
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if ($intake !== null) {
                return $intake;
            }
        }

        if (filled($intakeKey)) {
            return InboundMailIntake::query()
                ->where('provider', $provider)
                ->where('intake_key', $intakeKey)
                ->first();
        }

        return null;
    }

    private function payloadIntakeKey(string $provider, array $payload): string
    {
        $normalized = $this->sortPayload($payload);

        return 'payload:'.hash('sha256', $provider.'|'.json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    private function sortPayload(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortPayload($value);
            }
        }

        return $payload;
    }
}
