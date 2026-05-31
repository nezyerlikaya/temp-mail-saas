<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'title',
    'slug',
    'excerpt',
    'content',
    'type',
    'status',
    'published_at',
    'author_staff_id',
    'meta_title',
    'meta_description',
    'featured_media_id',
    'locale',
])]
class Content extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'author_staff_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function isDraft(): bool
    {
        return $this->status === ContentStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    public function isArchived(): bool
    {
        return $this->status === ContentStatus::Archived;
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description ?: (string) $this->excerpt;
    }

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
