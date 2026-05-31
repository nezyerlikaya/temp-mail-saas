<?php

namespace App\Services\Mail;

use App\Contracts\Mail\InboundProviderContract;
use App\Enums\InboundIntakeStatus;
use App\Enums\InboundProvider;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\InboundMailIntake;
use App\Services\Mail\Providers\LocalInboundProvider;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InboundMailIntakeService extends Service
{
    public function create(array $payload, array $headers = [], ?string $sourceIp = null, ?string $provider = null): InboundMailIntake
    {
        $providerContract = $this->provider($provider);
        $providerName = $providerContract->provider();
        $providerMessageId = $payload['provider_message_id'] ?? $payload['provider_id'] ?? null;
        $intakeKey = $payload['intake_key'] ?? null;

        if ($duplicate = $this->duplicate($providerName, $providerMessageId, $intakeKey)) {
            return $duplicate;
        }

        return DB::transaction(function () use ($payload, $headers, $sourceIp, $providerContract, $providerName, $providerMessageId, $intakeKey): InboundMailIntake {
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
        $provider = $provider ?? (string) config('inbound.default_provider', InboundProvider::Local->value);

        return match ($provider) {
            InboundProvider::Local->value => app(LocalInboundProvider::class),
            default => throw ValidationException::withMessages([
                'provider' => 'Inbound provider is not configured.',
            ]),
        };
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
}
