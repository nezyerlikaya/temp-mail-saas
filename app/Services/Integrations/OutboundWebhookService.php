<?php

namespace App\Services\Integrations;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookStatus;
use App\Models\Organization;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Service;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OutboundWebhookService extends Service
{
    public function createWebhook(
        string $url,
        array $events,
        ?string $secret = null,
        ?User $user = null,
        ?Organization $organization = null,
        WebhookStatus|string $status = WebhookStatus::Active,
    ): OutboundWebhook {
        $this->verifyConfiguration($url, $events);

        return OutboundWebhook::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'organization_id' => $organization?->id,
            'url' => $url,
            'status' => $status,
            'secret_hash' => $secret !== null ? Hash::make($secret) : null,
            'subscribed_events' => $this->normalizeEvents($events),
        ]);
    }

    public function rotateSecret(OutboundWebhook $webhook): string
    {
        $secret = Str::random(48);

        $webhook->update([
            'secret_hash' => Hash::make($secret),
        ]);

        return $secret;
    }

    public function verifyConfiguration(string $url, array $events): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'url' => 'A valid HTTP or HTTPS webhook URL is required.',
            ]);
        }

        if ($this->normalizeEvents($events) === []) {
            throw ValidationException::withMessages([
                'events' => 'At least one webhook event subscription is required.',
            ]);
        }

        return true;
    }

    public function queueDelivery(OutboundWebhook $webhook, string $eventName, array $payload = []): WebhookDelivery
    {
        return WebhookDelivery::query()->create([
            'outbound_webhook_id' => $webhook->id,
            'event_name' => $eventName,
            'status' => WebhookDeliveryStatus::Pending,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
    }

    public function recordDelivery(WebhookDelivery $delivery, WebhookDeliveryStatus|string $status, ?int $responseCode = null): WebhookDelivery
    {
        $delivery->update([
            'status' => $status,
            'response_code' => $responseCode,
            'delivered_at' => $status === WebhookDeliveryStatus::Delivered || $status === WebhookDeliveryStatus::Delivered->value
                ? now()
                : null,
        ]);

        if ($delivery->outboundWebhook !== null) {
            $delivery->outboundWebhook->update([
                'last_delivery_at' => $delivery->delivered_at,
            ]);
        }

        return $delivery->fresh();
    }

    private function normalizeEvents(array $events): array
    {
        return collect($events)
            ->filter(fn (mixed $event): bool => is_string($event) && trim($event) !== '')
            ->map(fn (string $event): string => Str::lower(trim($event)))
            ->unique()
            ->values()
            ->all();
    }
}
