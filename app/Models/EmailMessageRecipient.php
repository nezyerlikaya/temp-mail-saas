<?php

namespace App\Models;

use App\Enums\EmailRecipientType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'email_message_id',
    'type',
    'email',
    'name',
    'local_part',
    'domain',
])]
class EmailMessageRecipient extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }

    protected function casts(): array
    {
        return [
            'type' => EmailRecipientType::class,
        ];
    }
}
