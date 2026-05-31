<?php

namespace App\Services\Mail;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class MailReceptionTraceService extends Service
{
    public function __construct(
        private readonly PublicInboxMessageService $inbox,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function trace(
        ?string $intakeUuid = null,
        ?string $providerMessageId = null,
        ?string $messageUuid = null,
        ?string $mailboxAddress = null,
    ): array {
        $intake = $this->findIntake($intakeUuid, $providerMessageId);
        $message = $this->findMessage($messageUuid, $providerMessageId, $mailboxAddress, $intake);

        if (! $intake instanceof InboundMailIntake && ! $message instanceof EmailMessage) {
            $this->record('first_mail_trace_failed', false);

            return [
                'status' => 'missing',
                'intake' => null,
                'message' => null,
                'lifecycle' => $this->lifecycle(null, null),
            ];
        }

        $this->record('first_mail_trace_completed', true);

        return [
            'status' => $intake instanceof InboundMailIntake && $message instanceof EmailMessage ? 'complete' : 'partial',
            'intake' => $intake instanceof InboundMailIntake ? $this->safeIntake($intake) : null,
            'message' => $message instanceof EmailMessage ? $this->safeMessage($message) : null,
            'lifecycle' => $this->lifecycle($intake, $message),
        ];
    }

    public function byIntakeUuid(string $uuid): array
    {
        return $this->trace(intakeUuid: $uuid);
    }

    public function byProviderMessageId(string $providerMessageId): array
    {
        return $this->trace(providerMessageId: $providerMessageId);
    }

    public function byMessageUuid(string $uuid): array
    {
        return $this->trace(messageUuid: $uuid);
    }

    public function byMailbox(string $mailbox): array
    {
        return $this->trace(mailboxAddress: $mailbox);
    }

    private function findIntake(?string $uuid, ?string $providerMessageId): ?InboundMailIntake
    {
        if (filled($uuid)) {
            return InboundMailIntake::query()->where('uuid', $uuid)->first();
        }

        if (blank($providerMessageId)) {
            return null;
        }

        return InboundMailIntake::query()
            ->where('provider_message_id', $providerMessageId)
            ->get()
            ->first()
            ?? InboundMailIntake::query()
                ->latest()
                ->get()
                ->first(fn (InboundMailIntake $intake): bool => $this->intakeProviderId($intake) === $providerMessageId);
    }

    private function findMessage(
        ?string $uuid,
        ?string $providerMessageId,
        ?string $mailboxAddress,
        ?InboundMailIntake $intake,
    ): ?EmailMessage {
        if (filled($uuid)) {
            return EmailMessage::query()->where('uuid', $uuid)->first();
        }

        $providerId = $providerMessageId ?: ($intake instanceof InboundMailIntake ? $this->intakeProviderId($intake) : null);
        $intakeKey = $intake?->intake_key;

        if (filled($providerId)) {
            $message = EmailMessage::query()->where('provider_id', $providerId)->latest()->first();

            if ($message instanceof EmailMessage) {
                return $message;
            }
        }

        if (filled($intakeKey)) {
            $message = EmailMessage::query()->where('intake_key', $intakeKey)->latest()->first();

            if ($message instanceof EmailMessage) {
                return $message;
            }
        }

        if (filled($mailboxAddress)) {
            $visible = $this->inbox->list($mailboxAddress)->first();

            if (is_array($visible) && filled($visible['uuid'] ?? null)) {
                return EmailMessage::query()->where('uuid', $visible['uuid'])->first();
            }

            return EmailMessage::query()->where('mailbox_address', $mailboxAddress)->latest('received_at')->first();
        }

        return null;
    }

    private function intakeProviderId(InboundMailIntake $intake): ?string
    {
        return $intake->provider_message_id
            ?? ($intake->normalized_payload_json['provider_id'] ?? null)
            ?? ($intake->payload_json['provider_message_id'] ?? null)
            ?? ($intake->payload_json['provider_id'] ?? null)
            ?? ($intake->payload_json['Message-Id'] ?? null)
            ?? ($intake->payload_json['MessageID'] ?? null)
            ?? ($intake->payload_json['MessageId'] ?? null);
    }

    private function safeIntake(InboundMailIntake $intake): array
    {
        return [
            'uuid' => $intake->uuid,
            'provider' => $intake->provider->value,
            'status' => $intake->status->value,
            'signature_valid' => $intake->signature_valid,
            'has_headers' => ! empty($intake->headers_json),
            'has_payload' => ! empty($intake->payload_json),
            'has_normalized_payload' => ! empty($intake->normalized_payload_json),
            'queued_at' => $intake->queued_at?->toISOString(),
            'processed_at' => $intake->processed_at?->toISOString(),
            'failed_at' => $intake->failed_at?->toISOString(),
        ];
    }

    private function safeMessage(EmailMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'mailbox_address' => $message->mailbox_address,
            'status' => $message->status->value,
            'parse_status' => $message->parse_status->value,
            'retention_tier' => $message->retention_tier->value,
            'is_quarantined' => $message->isQuarantined(),
            'is_expired' => $message->isExpired(),
            'visible_in_public_inbox' => $this->visible($message),
            'has_text_body' => filled($message->text_body),
            'has_sanitized_html_body' => filled($message->sanitized_html_body),
            'attachments_count' => $message->attachments()->count(),
            'received_at' => $message->received_at?->toISOString(),
            'processed_at' => $message->processed_at?->toISOString(),
        ];
    }

    private function lifecycle(?InboundMailIntake $intake, ?EmailMessage $message): array
    {
        return [
            'provider_intake' => $intake instanceof InboundMailIntake,
            'inbound_intake' => $intake instanceof InboundMailIntake && $intake->signature_valid,
            'queued_job' => $intake instanceof InboundMailIntake && $intake->queued_at !== null,
            'email_message_storage' => $message instanceof EmailMessage,
            'public_inbox_visibility' => $message instanceof EmailMessage && $this->visible($message),
        ];
    }

    private function visible(EmailMessage $message): bool
    {
        return $this->inbox->list($message->mailbox_address)
            ->contains(fn (array $item): bool => ($item['uuid'] ?? null) === $message->uuid);
    }

    private function record(string $eventType, bool $completed): void
    {
        $this->operations->log(
            OperationCategory::Mail,
            $eventType,
            $completed ? OperationSeverity::Info : OperationSeverity::Warning,
            OperationStatus::Detected,
            'first-real-mail-trace',
            'First real mail trace event recorded.',
            ['completed' => $completed],
        );
    }
}
