<?php

namespace App\Models;

use App\Enums\InboundIntakeStatus;
use App\Enums\InboundProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'provider',
    'provider_message_id',
    'intake_key',
    'signature_valid',
    'signature_checked_at',
    'status',
    'source_ip_hash',
    'headers_json',
    'payload_json',
    'normalized_payload_json',
    'error_message',
    'queued_at',
    'processed_at',
    'failed_at',
])]
class InboundMailIntake extends Model
{
    public function isVerified(): bool
    {
        return $this->signature_valid === true && in_array($this->status, [
            InboundIntakeStatus::Verified,
            InboundIntakeStatus::Queued,
            InboundIntakeStatus::Processing,
            InboundIntakeStatus::Processed,
        ], true);
    }

    public function isQueued(): bool
    {
        return $this->status === InboundIntakeStatus::Queued;
    }

    public function isProcessed(): bool
    {
        return $this->status === InboundIntakeStatus::Processed;
    }

    public function isFailed(): bool
    {
        return $this->status === InboundIntakeStatus::Failed;
    }

    public function markQueued(): bool
    {
        return $this->forceFill([
            'status' => InboundIntakeStatus::Queued->value,
            'queued_at' => now(),
        ])->save();
    }

    public function markProcessing(): bool
    {
        return $this->forceFill([
            'status' => InboundIntakeStatus::Processing->value,
        ])->save();
    }

    public function markProcessed(): bool
    {
        return $this->forceFill([
            'status' => InboundIntakeStatus::Processed->value,
            'processed_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $message): bool
    {
        return $this->forceFill([
            'status' => InboundIntakeStatus::Failed->value,
            'error_message' => str($message)->limit(500)->toString(),
            'failed_at' => now(),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'provider' => InboundProvider::class,
            'status' => InboundIntakeStatus::class,
            'signature_valid' => 'boolean',
            'signature_checked_at' => 'datetime',
            'headers_json' => 'array',
            'payload_json' => 'array',
            'normalized_payload_json' => 'array',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
