<?php

namespace App\Models;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'user_id',
    'role',
    'status',
    'invited_by_user_id',
    'joined_at',
])]
class OrganizationMember extends Model
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationMemberStatus::Active;
    }

    public function isOwner(): bool
    {
        return $this->role === OrganizationMemberRole::Owner;
    }

    protected function casts(): array
    {
        return [
            'role' => OrganizationMemberRole::class,
            'status' => OrganizationMemberStatus::class,
            'joined_at' => 'datetime',
        ];
    }
}
