<?php

namespace App\Models;

use App\Enums\AccountTier;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'username',
    'display_name',
    'public_slug',
    'locale',
    'timezone',
    'avatar_disk',
    'avatar_path',
    'avatar_mime',
    'avatar_size',
    'avatar_hash',
    'avatar_updated_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function planAssignments(): HasMany
    {
        return $this->hasMany(UserPlanAssignment::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function billingCustomers(): HasMany
    {
        return $this->hasMany(BillingCustomer::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class);
    }

    public function outboundWebhooks(): HasMany
    {
        return $this->hasMany(OutboundWebhook::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['role', 'status', 'invited_by_user_id', 'joined_at'])
            ->withTimestamps();
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_user_id');
    }

    public function activePlan(): HasOneThrough
    {
        return $this->hasOneThrough(
            Plan::class,
            UserPlanAssignment::class,
            'user_id',
            'id',
            'id',
            'plan_id',
        )
            ->where('plans.is_active', true)
            ->where(function ($query): void {
                $query->whereNull('user_plan_assignments.starts_at')
                    ->orWhere('user_plan_assignments.starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('user_plan_assignments.expires_at')
                    ->orWhere('user_plan_assignments.expires_at', '>', now());
            })
            ->latest('user_plan_assignments.id');
    }

    public function isFree(): bool
    {
        return $this->planSlug() === AccountTier::Free->value;
    }

    public function isMember(): bool
    {
        return $this->planSlug() === AccountTier::Member->value;
    }

    public function isPremium(): bool
    {
        return $this->planSlug() === AccountTier::Premium->value;
    }

    public function planSlug(): string
    {
        return $this->activePlan()->value('slug')
            ?? $this->account_tier?->value
            ?? AccountTier::Free->value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'avatar_updated_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'api_access_enabled' => 'boolean',
            'status' => UserStatus::class,
            'account_tier' => AccountTier::class,
            'password' => 'hashed',
        ];
    }
}
