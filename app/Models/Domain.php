<?php

namespace App\Models;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'domain',
    'status',
    'type',
    'tier',
    'priority',
    'health_score',
    'assignment_strategy',
    'metadata',
    'last_checked_at',
])]
class Domain extends Model
{
    public function assignments(): HasMany
    {
        return $this->hasMany(DomainAssignment::class);
    }

    public function isActive(): bool
    {
        return $this->status === DomainStatus::Active;
    }

    public function isHealthy(): bool
    {
        return $this->health_score >= (int) config('domains-pool.health_thresholds.healthy', 80);
    }

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'type' => DomainType::class,
            'tier' => DomainTier::class,
            'assignment_strategy' => DomainAssignmentStrategy::class,
            'priority' => 'integer',
            'health_score' => 'integer',
            'metadata' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }
}
