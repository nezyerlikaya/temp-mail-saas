<?php

namespace App\Models;

use App\Enums\AutomationExecutionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'automation_rule_id',
    'trigger_source',
    'status',
    'result_summary',
    'started_at',
    'completed_at',
    'metadata',
])]
class AutomationExecution extends Model
{
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === AutomationExecutionStatus::Completed;
    }

    protected function casts(): array
    {
        return [
            'status' => AutomationExecutionStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
