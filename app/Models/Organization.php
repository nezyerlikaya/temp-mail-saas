<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'status',
    'owner_user_id',
    'plan_id',
    'metadata',
])]
class Organization extends Model
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot(['role', 'status', 'invited_by_user_id', 'joined_at'])
            ->withTimestamps();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function billingCustomers(): HasMany
    {
        return $this->hasMany(BillingCustomer::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class);
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class);
    }

    public function outboundWebhooks(): HasMany
    {
        return $this->hasMany(OutboundWebhook::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'metadata' => 'array',
        ];
    }
}
