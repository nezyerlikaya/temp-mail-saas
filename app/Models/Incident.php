<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'category',
    'severity',
    'status',
    'title',
    'summary',
    'detected_at',
    'resolved_at',
    'metadata',
])]
class Incident extends Model
{
    public function isOpen(): bool
    {
        return $this->status === IncidentStatus::Open;
    }

    public function isAcknowledged(): bool
    {
        return $this->status === IncidentStatus::Acknowledged;
    }

    public function isResolved(): bool
    {
        return $this->status === IncidentStatus::Resolved;
    }

    public function isCritical(): bool
    {
        return $this->severity === IncidentSeverity::Critical;
    }

    protected function casts(): array
    {
        return [
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
