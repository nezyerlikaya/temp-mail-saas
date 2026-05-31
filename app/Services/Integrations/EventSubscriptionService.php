<?php

namespace App\Services\Integrations;

use App\Enums\WebhookStatus;
use App\Models\Organization;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EventSubscriptionService extends Service
{
    public function __construct(
        private readonly OutboundWebhookService $webhooks,
    ) {}

    public function updateSubscriptions(OutboundWebhook $webhook, array $events): OutboundWebhook
    {
        $webhook->update([
            'subscribed_events' => $this->normalizeEvents($events),
        ]);

        return $webhook->fresh();
    }

    public function subscriptionsFor(OutboundWebhook $webhook): array
    {
        return $webhook->subscribed_events ?? [];
    }

    public function resolveWebhooks(string $eventName, ?User $user = null, ?Organization $organization = null): Collection
    {
        $eventName = Str::lower(trim($eventName));

        return OutboundWebhook::query()
            ->where('status', WebhookStatus::Active)
            ->when($user !== null, fn ($query) => $query->where('user_id', $user->id))
            ->when($organization !== null, fn ($query) => $query->where('organization_id', $organization->id))
            ->get()
            ->filter(fn (OutboundWebhook $webhook): bool => $webhook->subscribesTo($eventName))
            ->values();
    }

    public function prepareDeliveries(string $eventName, array $payload = [], ?User $user = null, ?Organization $organization = null): Collection
    {
        return $this->resolveWebhooks($eventName, $user, $organization)
            ->map(fn (OutboundWebhook $webhook): WebhookDelivery => $this->webhooks->queueDelivery($webhook, $eventName, $payload));
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
