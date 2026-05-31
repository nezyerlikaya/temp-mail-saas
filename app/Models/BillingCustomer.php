<?php

namespace App\Models;

use App\Enums\BillingProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'user_id',
    'organization_id',
    'provider',
    'provider_customer_id',
    'email',
    'metadata',
])]
#[Hidden(['provider_customer_id'])]
class BillingCustomer extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(BillingSubscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'metadata' => 'array',
        ];
    }
}
