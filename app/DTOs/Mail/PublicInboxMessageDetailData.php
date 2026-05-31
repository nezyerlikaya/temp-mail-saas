<?php

namespace App\DTOs\Mail;

use App\DTOs\DataTransferObject;
use Illuminate\Support\Carbon;

final readonly class PublicInboxMessageDetailData extends DataTransferObject
{
    public function __construct(
        public string $uuid,
        public ?string $from_name,
        public ?string $from_email,
        public ?string $subject,
        public ?string $text_body,
        public ?string $sanitized_html_body,
        public ?Carbon $received_at,
        public array $attachments,
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'subject' => $this->subject,
            'text_body' => $this->text_body,
            'sanitized_html_body' => $this->sanitized_html_body,
            'received_at' => $this->received_at?->toIso8601String(),
            'attachments' => $this->attachments,
        ];
    }
}
