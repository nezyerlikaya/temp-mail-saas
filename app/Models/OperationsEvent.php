<?php

namespace App\Models;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'category',
    'event_type',
    'severity',
    'status',
    'source',
    'message',
    'metadata',
    'occurred_at',
])]
class OperationsEvent extends Model
{
    public function isCritical(): bool
    {
        return $this->severity === OperationSeverity::Critical;
    }

    public function isResolved(): bool
    {
        return $this->status === OperationStatus::Resolved;
    }

    public function isQueueEvent(): bool
    {
        return $this->category === OperationCategory::Queue;
    }

    protected function casts(): array
    {
        return [
            'category' => OperationCategory::class,
            'severity' => OperationSeverity::class,
            'status' => OperationStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
