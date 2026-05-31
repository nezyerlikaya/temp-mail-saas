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
