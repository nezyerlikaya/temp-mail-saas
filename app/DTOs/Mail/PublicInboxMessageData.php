<?php

namespace App\DTOs\Mail;

use App\DTOs\DataTransferObject;
use Illuminate\Support\Carbon;

final readonly class PublicInboxMessageData extends DataTransferObject
{
    public function __construct(
        public string $uuid,
        public ?string $from_name,
        public ?string $from_email,
        public ?string $subject,
        public ?Carbon $received_at,
        public bool $has_attachments,
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'subject' => $this->subject,
            'received_at' => $this->received_at?->toIso8601String(),
            'has_attachments' => $this->has_attachments,
        ];
    }
}
