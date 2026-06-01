<?php

namespace App\Models;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'type',
    'category',
    'priority',
    'status',
    'title',
    'message',
    'metadata',
])]
class UserFeedback extends Model
{
    protected $table = 'user_feedback';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status !== FeedbackStatus::Closed;
    }

    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'category' => FeedbackCategory::class,
            'priority' => FeedbackPriority::class,
            'status' => FeedbackStatus::class,
            'metadata' => 'array',
        ];
    }
}
