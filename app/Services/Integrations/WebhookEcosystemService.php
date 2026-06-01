<?php

namespace App\Services\Integrations;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookStatus;
use App\Models\OutboundWebhook;
use App\Models\WebhookDelivery;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class WebhookEcosystemService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $active = OutboundWebhook::query()->where('status', WebhookStatus::Active)->count();
        $total = OutboundWebhook::query()->count();
        $failed = WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Failed)->count();
        $pending = WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Pending)->count();
        $withSubscriptions = OutboundWebhook::query()->whereNotNull('subscribed_events')->count();
        $checks = [
            $this->check('webhook_readiness', ! (bool) config('integrations.ecosystem.webhooks.require_enabled', true) || (bool) config('integrations.webhooks.enabled', true), 'Webhook foundation is enabled.', 'Webhook foundation needs review.', 'blocked'),
            $this->check('delivery_readiness', ! (bool) config('integrations.ecosystem.webhooks.require_delivery_hashes', true) || (Schema::hasColumn('webhook_deliveries', 'payload_hash') && ! Schema::hasColumn('webhook_deliveries', 'payload')), 'Webhook deliveries store hashes only.', 'Webhook delivery storage needs review.', 'blocked'),
            $this->check('event_subscription_readiness', ! (bool) config('integrations.ecosystem.webhooks.require_event_subscriptions', true) || $total === 0 || $withSubscriptions > 0, 'Webhook event subscriptions are available.', 'Webhook event subscriptions need review.', 'warning'),
            $this->check('webhook_lifecycle', $failed < (int) config('integrations.ecosystem.webhooks.failed_delivery_warning_count', 1), 'Webhook lifecycle is healthy.', 'Failed webhook deliveries need review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'webhook_review_completed',
            $summary['status'] === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'ecosystem-intelligence',
            'Webhook ecosystem review recorded.',
            [
                'status' => $summary['status'],
                'webhook_count' => $total,
                'active_count' => $active,
                'pending_delivery_count' => $pending,
                'failed_delivery_count' => $failed,
            ],
        );

        return [
            ...$summary,
            'webhook_count' => $total,
            'active_count' => $active,
            'pending_delivery_count' => $pending,
            'failed_delivery_count' => $failed,
            'subscription_count' => $withSubscriptions,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocked')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocked')->values()->all(),
            'checks' => $checks,
        ];
    }
}
