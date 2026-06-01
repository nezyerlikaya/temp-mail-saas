<?php

namespace App\Models;

use App\Enums\SupportCategory;
use App\Enums\SupportPriority;
use App\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'organization_id',
    'category',
    'priority',
    'status',
    'subject',
    'message',
    'metadata',
    'first_response_at',
    'resolved_at',
])]
class SupportRequest extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [SupportStatus::Resolved, SupportStatus::Closed], true);
    }

    protected function casts(): array
    {
        return [
            'category' => SupportCategory::class,
            'priority' => SupportPriority::class,
            'status' => SupportStatus::class,
            'metadata' => 'array',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
