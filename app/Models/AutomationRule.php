<?php

namespace App\Models;

use App\Enums\AutomationActionType;
use App\Enums\AutomationRuleStatus;
use App\Enums\AutomationTriggerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'name',
    'description',
    'trigger_type',
    'condition_group',
    'action_type',
    'priority',
    'status',
    'metadata',
])]
class AutomationRule extends Model
{
    public function executions(): HasMany
    {
        return $this->hasMany(AutomationExecution::class);
    }

    public function isActive(): bool
    {
        return $this->status === AutomationRuleStatus::Active;
    }

    protected function casts(): array
    {
        return [
            'trigger_type' => AutomationTriggerType::class,
            'condition_group' => 'array',
            'action_type' => AutomationActionType::class,
            'priority' => 'integer',
            'status' => AutomationRuleStatus::class,
            'metadata' => 'array',
        ];
    }
}
