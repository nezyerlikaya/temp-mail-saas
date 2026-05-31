<?php

namespace App\Models;

use App\Enums\AccountTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'description',
    'is_active',
    'is_system',
    'sort_order',
])]
class Plan extends Model
{
    public function assignments(): HasMany
    {
        return $this->hasMany(UserPlanAssignment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_plan_assignments')
            ->withPivot(['assigned_by_staff_id', 'starts_at', 'expires_at', 'notes'])
            ->withTimestamps();
    }

    public function isFree(): bool
    {
        return $this->slug === AccountTier::Free->value;
    }

    public function isMember(): bool
    {
        return $this->slug === AccountTier::Member->value;
    }

    public function isPremium(): bool
    {
        return $this->slug === AccountTier::Premium->value;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
