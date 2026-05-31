<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\MonitoringAlertStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'source',
    'alert_type',
    'severity',
    'status',
    'message',
    'triggered_at',
    'acknowledged_at',
    'resolved_at',
])]
class MonitoringAlert extends Model
{
    public function isActive(): bool
    {
        return $this->status === MonitoringAlertStatus::Active;
    }

    public function isAcknowledged(): bool
    {
        return $this->status === MonitoringAlertStatus::Acknowledged;
    }

    public function isResolved(): bool
    {
        return $this->status === MonitoringAlertStatus::Resolved;
    }

    public function isCritical(): bool
    {
        return $this->severity === IncidentSeverity::Critical;
    }

    protected function casts(): array
    {
        return [
            'severity' => IncidentSeverity::class,
            'status' => MonitoringAlertStatus::class,
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
