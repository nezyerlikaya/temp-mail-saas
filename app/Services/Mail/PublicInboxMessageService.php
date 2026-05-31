<?php

namespace App\Services\Mail;

use App\DTOs\Mail\PublicInboxMessageData;
use App\DTOs\Mail\PublicInboxMessageDetailData;
use App\Enums\EmailMessageStatus;
use App\Models\EmailMessage;
use App\Services\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicInboxMessageService extends Service
{
    public function list(?string $mailbox): Collection
    {
        if ($mailbox === null) {
            return collect();
        }

        return $this->visibleQuery($mailbox)
            ->withCount('attachments')
            ->latest('received_at')
            ->limit(max(1, (int) config('performance.thresholds.inbox_poll_limit', 50)))
            ->get()
            ->map(fn (EmailMessage $message): array => $this->toListData($message)->toArray());
    }

    public function show(?string $mailbox, string $uuid): ?array
    {
        if ($mailbox === null) {
            return null;
        }

        $message = $this->visibleQuery($mailbox)
            ->with('attachments')
            ->where('uuid', $uuid)
            ->first();

        return $message instanceof EmailMessage
            ? $this->toDetailData($message)->toArray()
            : null;
    }

    private function visibleQuery(string $mailbox): Builder
    {
        return EmailMessage::query()
            ->where('mailbox_address', $mailbox)
            ->where('is_quarantined', false)
            ->whereNotIn('status', [
                EmailMessageStatus::Quarantined->value,
                EmailMessageStatus::Expired->value,
                EmailMessageStatus::Deleted->value,
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    private function toListData(EmailMessage $message): PublicInboxMessageData
    {
        return new PublicInboxMessageData(
            uuid: $message->uuid,
            from_name: $message->from_name,
            from_email: $message->from_email,
            subject: $message->subject,
            received_at: $message->received_at,
            has_attachments: ((int) ($message->attachments_count ?? 0)) > 0,
        );
    }

    private function toDetailData(EmailMessage $message): PublicInboxMessageDetailData
    {
        return new PublicInboxMessageDetailData(
            uuid: $message->uuid,
            from_name: $message->from_name,
            from_email: $message->from_email,
            subject: $message->subject,
            text_body: $message->text_body,
            sanitized_html_body: $message->sanitized_html_body,
            received_at: $message->received_at,
            attachments: $message->attachments->map(fn ($attachment): array => [
                'uuid' => $attachment->uuid,
                'filename' => $attachment->safe_filename ?: $attachment->original_filename,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'status' => $attachment->status?->value,
                'scan_status' => $attachment->scan_status?->value,
            ])->all(),
        );
    }
}
