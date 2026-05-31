<?php

namespace App\Models;

use App\Enums\SystemHealthStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'domain',
    'status',
    'score',
    'message',
    'checked_at',
])]
class DomainHealthCheck extends Model
{
    public function isHealthy(): bool
    {
        return $this->status === SystemHealthStatus::Healthy;
    }

    public function isWarning(): bool
    {
        return $this->status === SystemHealthStatus::Warning;
    }

    public function isCritical(): bool
    {
        return $this->status === SystemHealthStatus::Critical;
    }

    protected function casts(): array
    {
        return [
            'status' => SystemHealthStatus::class,
            'score' => 'integer',
            'checked_at' => 'datetime',
        ];
    }
}
