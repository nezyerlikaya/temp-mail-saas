<?php

namespace App\Services\Mail;

use App\Enums\EmailAttachmentScanStatus;
use App\Enums\EmailAttachmentStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\EmailRecipientType;
use App\Enums\RetentionTier;
use App\Models\EmailMessage;
use App\Services\Service;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmailMessageStorageService extends Service
{
    public function __construct(private readonly EmailRetentionService $retention)
    {
    }

    public function create(array $data): EmailMessage
    {
        return DB::transaction(function () use ($data): EmailMessage {
            $tier = $this->retentionTier($data['retention_tier'] ?? null);

            $message = EmailMessage::query()->create([
                'uuid' => (string) Str::uuid(),
                'mailbox_address' => $data['mailbox_address'] ?? null,
                'recipient_local_part' => $data['recipient_local_part'] ?? $this->localPart($data['mailbox_address'] ?? null),
                'recipient_domain' => $data['recipient_domain'] ?? $this->domain($data['mailbox_address'] ?? null),
                'from_email' => $data['from_email'] ?? null,
                'from_name' => $data['from_name'] ?? null,
                'subject' => $data['subject'] ?? null,
                'message_id_header' => $data['message_id_header'] ?? null,
                'in_reply_to' => $data['in_reply_to'] ?? null,
                'references_header' => $data['references_header'] ?? null,
                'text_body' => $data['text_body'] ?? null,
                'html_body' => $data['html_body'] ?? null,
                'sanitized_html_body' => $data['sanitized_html_body'] ?? null,
                'status' => $this->messageStatus($data['status'] ?? null)->value,
                'parse_status' => $this->parseStatus($data['parse_status'] ?? null)->value,
                'intake_source' => $data['intake_source'] ?? config('inbound.storage.default_source', 'manual'),
                'provider_id' => $data['provider_id'] ?? null,
                'intake_key' => $data['intake_key'] ?? null,
                'is_quarantined' => (bool) ($data['is_quarantined'] ?? false),
                'quarantine_reason' => $data['quarantine_reason'] ?? null,
                'abuse_score' => (int) ($data['abuse_score'] ?? 0),
                'retention_tier' => $tier->value,
                'expires_at' => $data['expires_at'] ?? $this->retention->expirationFor($tier),
                'received_at' => $data['received_at'] ?? now(),
                'processed_at' => $data['processed_at'] ?? null,
                'failed_at' => $data['failed_at'] ?? null,
            ]);

            $this->attachRecipients($message, $data['recipients'] ?? []);
            $this->attachAttachmentMetadata($message, $data['attachments'] ?? []);

            return $message->refresh()->load(['recipients', 'attachments']);
        });
    }

    public function attachRecipients(EmailMessage $message, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $type = EmailRecipientType::tryFrom((string) ($recipient['type'] ?? ''));

            if ($type === null || blank($recipient['email'] ?? null)) {
                throw ValidationException::withMessages([
                    'recipients' => 'Each recipient requires a valid type and email.',
                ]);
            }

            $message->recipients()->create([
                'type' => $type->value,
                'email' => $recipient['email'],
                'name' => $recipient['name'] ?? null,
                'local_part' => $recipient['local_part'] ?? $this->localPart($recipient['email']),
                'domain' => $recipient['domain'] ?? $this->domain($recipient['email']),
            ]);
        }
    }

    public function attachAttachmentMetadata(EmailMessage $message, array $attachments): void
    {
        $max = (int) config('inbound.storage.max_attachment_metadata_count', 25);

        if (count($attachments) > $max) {
            throw ValidationException::withMessages([
                'attachments' => "Attachment metadata count may not exceed {$max}.",
            ]);
        }

        foreach ($attachments as $attachment) {
            $message->attachments()->create([
                'uuid' => (string) Str::uuid(),
                'media_id' => $attachment['media_id'] ?? null,
                'original_filename' => $attachment['original_filename'] ?? null,
                'safe_filename' => $attachment['safe_filename'] ?? $this->safeFilename($attachment['original_filename'] ?? null),
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
                'checksum' => $attachment['checksum'] ?? null,
                'storage_disk' => $attachment['storage_disk'] ?? null,
                'storage_path' => $attachment['storage_path'] ?? null,
                'scan_status' => $this->attachmentScanStatus($attachment['scan_status'] ?? null)->value,
                'status' => $this->attachmentStatus($attachment['status'] ?? null)->value,
            ]);
        }
    }

    private function retentionTier(RetentionTier|string|null $tier): RetentionTier
    {
        return $tier instanceof RetentionTier
            ? $tier
            : (RetentionTier::tryFrom((string) $tier) ?? $this->retention->defaultTier());
    }

    private function messageStatus(EmailMessageStatus|string|null $status): EmailMessageStatus
    {
        return $status instanceof EmailMessageStatus
            ? $status
            : (EmailMessageStatus::tryFrom((string) $status) ?? EmailMessageStatus::Received);
    }

    private function parseStatus(EmailParseStatus|string|null $status): EmailParseStatus
    {
        return $status instanceof EmailParseStatus
            ? $status
            : (EmailParseStatus::tryFrom((string) $status) ?? EmailParseStatus::Pending);
    }

    private function attachmentStatus(EmailAttachmentStatus|string|null $status): EmailAttachmentStatus
    {
        return $status instanceof EmailAttachmentStatus
            ? $status
            : (EmailAttachmentStatus::tryFrom((string) $status) ?? EmailAttachmentStatus::Pending);
    }

    private function attachmentScanStatus(EmailAttachmentScanStatus|string|null $status): EmailAttachmentScanStatus
    {
        return $status instanceof EmailAttachmentScanStatus
            ? $status
            : (EmailAttachmentScanStatus::tryFrom((string) $status) ?? EmailAttachmentScanStatus::Pending);
    }

    private function localPart(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        return Str::before($email, '@');
    }

    private function domain(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        return Str::after($email, '@');
    }

    private function safeFilename(?string $filename): ?string
    {
        if (blank($filename)) {
            return null;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $safe = Str::slug($name);

        return $extension !== '' ? "{$safe}.{$extension}" : $safe;
    }
}
