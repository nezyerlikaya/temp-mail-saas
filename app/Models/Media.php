<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'disk',
    'directory',
    'filename',
    'original_filename',
    'extension',
    'mime_type',
    'size',
    'checksum',
    'visibility',
    'width',
    'height',
    'uploaded_by_user_id',
    'uploaded_by_staff_id',
    'status',
    'storage_driver',
    'storage_path',
])]
class Media extends Model
{
    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function uploadedByStaff(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'uploaded_by_staff_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPublic(): bool
    {
        return $this->visibility === MediaVisibility::Public;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === MediaVisibility::Private;
    }

    protected function casts(): array
    {
        return [
            'visibility' => MediaVisibility::class,
            'status' => MediaStatus::class,
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
        ];
    }
}
