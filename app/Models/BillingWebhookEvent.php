<?php

namespace App\Models;

use App\Enums\BillingProvider;
use App\Enums\BillingWebhookStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'provider',
    'event_id',
    'event_type',
    'signature_valid',
    'status',
    'payload_hash',
    'processed_at',
    'failed_at',
    'error_message',
])]
class BillingWebhookEvent extends Model
{
    public function isProcessed(): bool
    {
        return $this->status === BillingWebhookStatus::Processed;
    }

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'signature_valid' => 'boolean',
            'status' => BillingWebhookStatus::class,
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
