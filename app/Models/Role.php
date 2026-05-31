<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function staffUsers(): BelongsToMany
    {
        return $this->belongsToMany(StaffUser::class, 'role_staff_user');
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
