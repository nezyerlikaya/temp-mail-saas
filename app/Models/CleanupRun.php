<?php

namespace App\Models;

use App\Enums\CleanupRunStatus;
use App\Enums\CleanupRunType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'type',
    'status',
    'dry_run',
    'messages_scanned',
    'messages_expired',
    'messages_deleted',
    'intakes_deleted',
    'attachments_affected',
    'started_at',
    'finished_at',
    'error_message',
])]
class CleanupRun extends Model
{
    public function isRunning(): bool
    {
        return $this->status === CleanupRunStatus::Running;
    }

    public function isCompleted(): bool
    {
        return $this->status === CleanupRunStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === CleanupRunStatus::Failed;
    }

    protected function casts(): array
    {
        return [
            'type' => CleanupRunType::class,
            'status' => CleanupRunStatus::class,
            'dry_run' => 'boolean',
            'messages_scanned' => 'integer',
            'messages_expired' => 'integer',
            'messages_deleted' => 'integer',
            'intakes_deleted' => 'integer',
            'attachments_affected' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
