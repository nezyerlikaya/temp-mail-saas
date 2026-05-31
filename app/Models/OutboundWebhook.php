<?php

namespace App\Models;

use App\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'user_id',
    'organization_id',
    'url',
    'status',
    'secret_hash',
    'subscribed_events',
    'last_delivery_at',
])]
#[Hidden(['secret_hash'])]
class OutboundWebhook extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === WebhookStatus::Active;
    }

    public function subscribesTo(string $eventName): bool
    {
        return in_array($eventName, $this->subscribed_events ?? [], true);
    }

    protected function casts(): array
    {
        return [
            'status' => WebhookStatus::class,
            'subscribed_events' => 'array',
            'last_delivery_at' => 'datetime',
        ];
    }
}
