<?php

namespace App\Services\Mail\Providers;

use App\Contracts\Mail\InboundProviderContract;
use App\Contracts\Mail\InboundSignatureVerifierContract;
use App\Enums\InboundProvider;
use App\Services\Mail\Providers\Concerns\NormalizesProviderPayloads;

final class PostmarkInboundProvider implements InboundProviderContract, InboundSignatureVerifierContract
{
    use NormalizesProviderPayloads;

    public function provider(): string
    {
        return InboundProvider::Postmark->value;
    }

    public function verifySignature(array $headers, string|array|null $payload): bool
    {
        $expected = (string) config('mail-providers.providers.postmark.signing_key', config('inbound.providers.postmark.signing_key', ''));
        $provided = (string) ($headers['X-Postmark-Webhook-Token'] ?? $headers['x-postmark-webhook-token'] ?? '');

        if ($expected === '' || $provided === '') {
            return false;
        }

        $timestamp = $headers['X-Postmark-Timestamp'] ?? $headers['x-postmark-timestamp'] ?? null;

        if ($timestamp !== null && $this->timestampExpired((string) $timestamp)) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public function verify(array $headers, string|array|null $payload): bool
    {
        return $this->verifySignature($headers, $payload);
    }

    public function normalizePayload(array $payload): array
    {
        $recipient = $payload['OriginalRecipient'] ?? $payload['To'] ?? null;

        return [
            'mailbox_address' => $recipient,
            'from_email' => $payload['From'] ?? null,
            'from_name' => $payload['FromName'] ?? null,
            'subject' => $payload['Subject'] ?? null,
            'text_body' => $payload['TextBody'] ?? null,
            'html_body' => $payload['HtmlBody'] ?? null,
            'recipients' => $this->recipientsFrom($payload['Recipients'] ?? $payload['ToFull'] ?? $recipient, $recipient),
            'attachments' => $this->safeAttachments($payload['Attachments'] ?? []),
            'received_at' => $payload['Date'] ?? now(),
            'intake_source' => 'provider',
            'provider_id' => $payload['MessageID'] ?? null,
            'intake_key' => $payload['intake_key'] ?? null,
        ];
    }

    private function timestampExpired(string $timestamp): bool
    {
        return ! is_numeric($timestamp)
            || abs(time() - (int) $timestamp) > (int) config('mail-providers.signature_tolerance_seconds', 300);
    }
}
