<?php

namespace App\Jobs;

use App\Enums\InboundIntakeStatus;
use App\Models\InboundMailIntake;
use App\Services\Mail\EmailMessageStorageService;
use App\Services\Mail\InboundMailIntakeService;
use App\Services\Mail\InboundProviderMetricsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessInboundMailIntake implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $intakeId)
    {
    }

    public function handle(
        InboundMailIntakeService $intakes,
        EmailMessageStorageService $messages,
        ?InboundProviderMetricsService $metrics = null,
    ): void {
        $metrics ??= app(InboundProviderMetricsService::class);
        $intake = InboundMailIntake::query()->find($this->intakeId);

        if ($intake === null || $intake->status !== InboundIntakeStatus::Queued) {
            return;
        }

        try {
            $intake->markProcessing();

            $provider = $intakes->provider($intake->provider->value);
            $normalized = $provider->normalizePayload($intake->payload_json ?? []);

            $intake->forceFill([
                'normalized_payload_json' => $normalized,
            ])->save();

            $messages->create($normalized);
            $intake->markProcessed();
        } catch (Throwable $exception) {
            $intake->markFailed($exception->getMessage());
            $metrics->failure($intake->provider->value);
        }
    }
}
