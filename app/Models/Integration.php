<?php

namespace App\Models;

use App\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'slug',
    'name',
    'description',
    'category',
    'status',
    'version',
    'metadata',
])]
class Integration extends Model
{
    public function userIntegrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class);
    }

    public function isActive(): bool
    {
        return $this->status === IntegrationStatus::Active;
    }

    public function isDeprecated(): bool
    {
        return $this->status === IntegrationStatus::Deprecated;
    }

    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'metadata' => 'array',
        ];
    }
}
