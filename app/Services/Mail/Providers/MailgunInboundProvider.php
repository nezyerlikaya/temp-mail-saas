<?php

namespace App\Services\Mail\Providers;

use App\Contracts\Mail\InboundProviderContract;
use App\Contracts\Mail\InboundSignatureVerifierContract;
use App\Enums\InboundProvider;
use App\Services\Mail\Providers\Concerns\NormalizesProviderPayloads;

final class MailgunInboundProvider implements InboundProviderContract, InboundSignatureVerifierContract
{
    use NormalizesProviderPayloads;

    public function provider(): string
    {
        return InboundProvider::Mailgun->value;
    }

    public function verifySignature(array $headers, string|array|null $payload): bool
    {
        $data = is_array($payload) ? $payload : [];
        $timestamp = (string) ($data['timestamp'] ?? $headers['timestamp'] ?? $headers['X-Mailgun-Timestamp'] ?? '');
        $token = (string) ($data['token'] ?? $headers['token'] ?? $headers['X-Mailgun-Token'] ?? '');
        $signature = (string) ($data['signature'] ?? $headers['signature'] ?? $headers['X-Mailgun-Signature'] ?? '');
        $key = (string) config('mail-providers.providers.mailgun.signing_key', config('inbound.providers.mailgun.signing_key', ''));

        if ($key === '' || $timestamp === '' || $token === '' || $signature === '' || $this->timestampExpired($timestamp)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.$token, $key), $signature);
    }

    public function verify(array $headers, string|array|null $payload): bool
    {
        return $this->verifySignature($headers, $payload);
    }

    public function normalizePayload(array $payload): array
    {
        $recipient = $payload['recipient'] ?? $payload['To'] ?? null;

        return [
            'mailbox_address' => $recipient,
            'from_email' => $payload['sender'] ?? $payload['From'] ?? null,
            'from_name' => $payload['from_name'] ?? null,
            'subject' => $payload['subject'] ?? $payload['Subject'] ?? null,
            'text_body' => $payload['body-plain'] ?? $payload['stripped-text'] ?? null,
            'html_body' => $payload['body-html'] ?? $payload['stripped-html'] ?? null,
            'recipients' => $this->recipientsFrom($payload['recipients'] ?? $recipient, $recipient),
            'attachments' => $this->safeAttachments($payload['attachments'] ?? []),
            'received_at' => $payload['timestamp'] ?? now(),
            'intake_source' => 'provider',
            'provider_id' => $payload['Message-Id'] ?? $payload['message-id'] ?? null,
            'intake_key' => $payload['intake_key'] ?? null,
        ];
    }

    private function timestampExpired(string $timestamp): bool
    {
        return ! is_numeric($timestamp)
            || abs(time() - (int) $timestamp) > (int) config('mail-providers.signature_tolerance_seconds', 300);
    }
}
