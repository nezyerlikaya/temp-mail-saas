<?php

namespace App\Jobs;

use App\Enums\InboundIntakeStatus;
use App\Models\InboundMailIntake;
use App\Services\Mail\EmailMessageStorageService;
use App\Services\Mail\InboundMailIntakeService;
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
    ): void {
        $intake = InboundMailIntake::query()->find($this->intakeId);

        if ($intake === null || $intake->isProcessed() || $intake->status === InboundIntakeStatus::Rejected) {
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
        }
    }
}
