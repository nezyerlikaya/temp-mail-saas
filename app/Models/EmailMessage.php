<?php

namespace App\Models;

use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\RetentionTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'mailbox_address',
    'recipient_local_part',
    'recipient_domain',
    'from_email',
    'from_name',
    'subject',
    'message_id_header',
    'in_reply_to',
    'references_header',
    'text_body',
    'html_body',
    'sanitized_html_body',
    'status',
    'parse_status',
    'intake_source',
    'provider_id',
    'intake_key',
    'is_quarantined',
    'quarantine_reason',
    'abuse_score',
    'retention_tier',
    'expires_at',
    'received_at',
    'processed_at',
    'failed_at',
])]
class EmailMessage extends Model
{
    use SoftDeletes;

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailMessageRecipient::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isQuarantined(): bool
    {
        return $this->is_quarantined === true || $this->status === EmailMessageStatus::Quarantined;
    }

    public function isProcessed(): bool
    {
        return $this->status === EmailMessageStatus::Processed;
    }

    public function markProcessed(): bool
    {
        return $this->forceFill([
            'status' => EmailMessageStatus::Processed->value,
            'parse_status' => EmailParseStatus::Parsed->value,
            'processed_at' => now(),
            'failed_at' => null,
        ])->save();
    }

    public function markFailed(): bool
    {
        return $this->forceFill([
            'status' => EmailMessageStatus::Failed->value,
            'parse_status' => EmailParseStatus::Failed->value,
            'failed_at' => now(),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'status' => EmailMessageStatus::class,
            'parse_status' => EmailParseStatus::class,
            'retention_tier' => RetentionTier::class,
            'is_quarantined' => 'boolean',
            'abuse_score' => 'integer',
            'expires_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
