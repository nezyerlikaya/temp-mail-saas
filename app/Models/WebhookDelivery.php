<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'outbound_webhook_id',
    'event_name',
    'status',
    'response_code',
    'delivered_at',
    'payload_hash',
])]
class WebhookDelivery extends Model
{
    public function outboundWebhook(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhook::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === WebhookDeliveryStatus::Delivered;
    }

    protected function casts(): array
    {
        return [
            'status' => WebhookDeliveryStatus::class,
            'delivered_at' => 'datetime',
        ];
    }
}
