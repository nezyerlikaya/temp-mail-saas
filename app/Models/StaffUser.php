<?php

namespace App\Models;

use App\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'status',
    'last_login_at',
    'last_seen_at',
    'password_changed_at',
    'two_factor_enabled',
    'two_factor_confirmed_at',
])]
#[Hidden(['password', 'remember_token'])]
class StaffUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'staff_users';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_staff_user');
    }

    public function assignedPlanAssignments(): HasMany
    {
        return $this->hasMany(UserPlanAssignment::class, 'assigned_by_staff_id');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    public function isActive(): bool
    {
        return $this->status === StaffStatus::Active;
    }

    protected function casts(): array
    {
        return [
            'status' => StaffStatus::class,
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
