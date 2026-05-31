<?php

namespace App\Models;

use App\Enums\EmailAttachmentScanStatus;
use App\Enums\EmailAttachmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'email_message_id',
    'media_id',
    'original_filename',
    'safe_filename',
    'mime_type',
    'size',
    'checksum',
    'storage_disk',
    'storage_path',
    'scan_status',
    'status',
])]
class EmailAttachment extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function isStored(): bool
    {
        return $this->status === EmailAttachmentStatus::Stored;
    }

    public function isClean($attributes = null): bool
    {
        if ($attributes !== null) {
            return parent::isClean($attributes);
        }

        return $this->scan_status === EmailAttachmentScanStatus::Clean;
    }

    public function isSuspicious(): bool
    {
        return in_array($this->scan_status, [
            EmailAttachmentScanStatus::Suspicious,
            EmailAttachmentScanStatus::Infected,
        ], true);
    }

    protected function casts(): array
    {
        return [
            'status' => EmailAttachmentStatus::class,
            'scan_status' => EmailAttachmentScanStatus::class,
            'size' => 'integer',
        ];
    }
}
