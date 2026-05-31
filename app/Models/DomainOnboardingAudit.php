<?php

namespace App\Models;

use App\Enums\DomainOnboardingState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'domain_id',
    'domain_name',
    'previous_state',
    'new_state',
    'reason',
    'review_type',
    'recommendation',
    'metadata',
])]
class DomainOnboardingAudit extends Model
{
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    protected function casts(): array
    {
        return [
            'previous_state' => DomainOnboardingState::class,
            'new_state' => DomainOnboardingState::class,
            'metadata' => 'array',
        ];
    }
}
