<?php

namespace App\Services\Mail\Providers;

use App\Contracts\Mail\InboundProviderContract;
use App\Contracts\Mail\InboundSignatureVerifierContract;
use App\Enums\InboundProvider;
use App\Services\Mail\Providers\Concerns\NormalizesProviderPayloads;

final class SesInboundProvider implements InboundProviderContract, InboundSignatureVerifierContract
{
    use NormalizesProviderPayloads;

    public function provider(): string
    {
        return InboundProvider::Ses->value;
    }

    public function verifySignature(array $headers, string|array|null $payload): bool
    {
        $data = is_array($payload) ? $payload : [];
        $key = (string) config('mail-providers.providers.ses.signing_key', config('mail-providers.providers.amazon_ses.signing_key', config('inbound.providers.ses.signing_key', '')));
        $messageId = (string) ($data['mail']['messageId'] ?? $data['MessageId'] ?? $headers['X-Amz-Sns-Message-Id'] ?? '');
        $timestamp = (string) ($data['Timestamp'] ?? $headers['X-Amz-Sns-Timestamp'] ?? '');
        $signature = (string) ($data['Signature'] ?? $headers['X-Amz-Sns-Signature'] ?? '');

        if ($key === '' || $messageId === '' || $timestamp === '' || $signature === '' || $this->timestampExpired($timestamp)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $messageId.'|'.$timestamp, $key), $signature);
    }

    public function verify(array $headers, string|array|null $payload): bool
    {
        return $this->verifySignature($headers, $payload);
    }

    public function normalizePayload(array $payload): array
    {
        $mail = $payload['mail'] ?? [];
        $receipt = $payload['receipt'] ?? [];
        $recipient = $mail['destination'][0] ?? $receipt['recipients'][0] ?? null;

        return [
            'mailbox_address' => $recipient,
            'from_email' => $mail['source'] ?? null,
            'from_name' => null,
            'subject' => $payload['subject'] ?? null,
            'text_body' => $payload['text_body'] ?? null,
            'html_body' => $payload['html_body'] ?? null,
            'recipients' => $this->recipientsFrom($mail['destination'] ?? $receipt['recipients'] ?? [], $recipient),
            'attachments' => [],
            'received_at' => $mail['timestamp'] ?? $payload['Timestamp'] ?? now(),
            'intake_source' => 'provider',
            'provider_id' => $mail['messageId'] ?? $payload['MessageId'] ?? null,
            'intake_key' => $payload['intake_key'] ?? null,
        ];
    }

    private function timestampExpired(string $timestamp): bool
    {
        $time = is_numeric($timestamp) ? (int) $timestamp : strtotime($timestamp);

        return $time === false
            || abs(time() - $time) > (int) config('mail-providers.signature_tolerance_seconds', 300);
    }
}
