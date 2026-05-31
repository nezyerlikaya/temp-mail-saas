<?php

namespace App\Models;

use App\Enums\SystemHealthStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'check_name',
    'status',
    'message',
    'metadata',
    'checked_at',
])]
class SystemHealthCheck extends Model
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
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
