<?php

namespace App\Models;

use App\Enums\AbuseEventType;
use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'event_type',
    'severity',
    'status',
    'ip_hash',
    'session_hash',
    'user_id',
    'staff_user_id',
    'route_name',
    'endpoint',
    'method',
    'user_agent_hash',
    'risk_score',
    'reason',
    'metadata',
    'occurred_at',
])]
class AbuseEvent extends Model
{
    public function isBlocked(): bool
    {
        return $this->status === AbuseStatus::Blocked;
    }

    public function isThrottled(): bool
    {
        return $this->status === AbuseStatus::Throttled;
    }

    public function isCritical(): bool
    {
        return $this->severity === AbuseSeverity::Critical;
    }

    protected function casts(): array
    {
        return [
            'event_type' => AbuseEventType::class,
            'severity' => AbuseSeverity::class,
            'status' => AbuseStatus::class,
            'metadata' => 'array',
            'risk_score' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
