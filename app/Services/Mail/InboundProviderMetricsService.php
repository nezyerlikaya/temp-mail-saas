<?php

namespace App\Services\Mail;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\OperationsEvent;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class InboundProviderMetricsService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function intake(string $provider): OperationsEvent
    {
        return $this->record($provider, 'provider_intake_received', OperationSeverity::Info);
    }

    public function rejection(string $provider): OperationsEvent
    {
        return $this->record($provider, 'provider_intake_rejected', OperationSeverity::Warning);
    }

    public function failure(string $provider): OperationsEvent
    {
        return $this->record($provider, 'provider_intake_failed', OperationSeverity::Warning);
    }

    public function webhookReceived(string $provider): OperationsEvent
    {
        return $this->record($provider, 'webhook_received', OperationSeverity::Info);
    }

    public function webhookVerified(string $provider): OperationsEvent
    {
        return $this->record($provider, 'webhook_verified', OperationSeverity::Info);
    }

    public function webhookRejected(string $provider): OperationsEvent
    {
        return $this->record($provider, 'webhook_rejected', OperationSeverity::Warning);
    }

    public function webhookDuplicate(string $provider): OperationsEvent
    {
        return $this->record($provider, 'webhook_duplicate', OperationSeverity::Info);
    }

    public function webhookProcessed(string $provider): OperationsEvent
    {
        return $this->record($provider, 'webhook_processed', OperationSeverity::Info);
    }

    private function record(string $provider, string $eventType, OperationSeverity $severity): OperationsEvent
    {
        return $this->operations->log(
            OperationCategory::Mail,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'inbound-provider',
            'Provider intake metric recorded.',
            ['provider' => $provider],
        );
    }
}
