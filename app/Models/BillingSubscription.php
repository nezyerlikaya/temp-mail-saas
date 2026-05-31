<?php

namespace App\Models;

use App\Enums\BillingProvider;
use App\Enums\BillingSubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'billing_customer_id',
    'plan_id',
    'provider',
    'provider_subscription_id',
    'status',
    'interval',
    'trial_ends_at',
    'current_period_starts_at',
    'current_period_ends_at',
    'cancels_at',
    'canceled_at',
    'metadata',
])]
#[Hidden(['provider_subscription_id'])]
class BillingSubscription extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class, 'billing_customer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            BillingSubscriptionStatus::Active,
            BillingSubscriptionStatus::Trialing,
        ], true);
    }

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'status' => BillingSubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancels_at' => 'datetime',
            'canceled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
