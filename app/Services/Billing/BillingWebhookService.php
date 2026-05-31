<?php

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGatewayContract;
use App\Enums\BillingWebhookStatus;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\Providers\LocalBillingProvider;
use App\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class BillingWebhookService extends Service
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function handle(string $provider, Request $request): array
    {
        $gateway = $this->provider($provider);
        $payload = $request->getContent();
        $decoded = json_decode($payload, true) ?: [];
        $eventId = $decoded['id'] ?? null;
        $eventType = $decoded['type'] ?? 'unknown';
        $signature = $request->headers->get('X-Billing-Signature');
        $signatureValid = $gateway->verifyWebhook($payload, $signature);

        $event = $this->createEvent($provider, $eventId, $eventType, $payload, $signatureValid);

        if (! $signatureValid) {
            if (! $event->isProcessed()) {
                $event->forceFill(['status' => BillingWebhookStatus::Rejected->value])->save();
            }

            return ['ok' => false, 'status' => 'rejected'];
        }

        if ($event->wasRecentlyCreated === false && $event->isProcessed()) {
            return ['ok' => true, 'status' => 'duplicate'];
        }

        try {
            $event->forceFill(['status' => BillingWebhookStatus::Verified->value])->save();
            $normalized = $gateway->normalizeWebhookPayload($decoded);
            $customer = $this->billing->createOrUpdateCustomer($provider, $normalized['customer']);

            if (is_array($normalized['subscription'])) {
                $this->billing->createOrUpdateSubscription($customer, $provider, $normalized['subscription']);
            }

            if (is_array($normalized['invoice'])) {
                $this->billing->createOrUpdateInvoice($customer, $provider, $normalized['invoice']);
            }

            $event->forceFill([
                'status' => BillingWebhookStatus::Processed->value,
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return ['ok' => true, 'status' => 'processed'];
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => BillingWebhookStatus::Failed->value,
                'failed_at' => now(),
                'error_message' => Str::limit(class_basename($exception), 500, ''),
            ])->save();

            return ['ok' => false, 'status' => 'failed'];
        }
    }

    public function provider(string $provider): BillingGatewayContract
    {
        return match ($provider) {
            'local' => app(LocalBillingProvider::class),
            default => app(LocalBillingProvider::class),
        };
    }

    private function createEvent(
        string $provider,
        ?string $eventId,
        string $eventType,
        string $payload,
        bool $signatureValid,
    ): BillingWebhookEvent {
        if ($eventId !== null) {
            $existing = BillingWebhookEvent::query()
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->first();

            if ($existing instanceof BillingWebhookEvent) {
                return $existing;
            }
        }

        return BillingWebhookEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'signature_valid' => $signatureValid,
            'status' => BillingWebhookStatus::Received,
            'payload_hash' => hash('sha256', $payload),
        ]);
    }
}
