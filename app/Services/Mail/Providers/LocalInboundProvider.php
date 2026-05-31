<?php

namespace App\Services\Mail\Providers;

use App\Contracts\Mail\InboundProviderContract;
use App\Contracts\Mail\InboundSignatureVerifierContract;
use App\Enums\InboundProvider;

final class LocalInboundProvider implements InboundProviderContract, InboundSignatureVerifierContract
{
    public function provider(): string
    {
        return InboundProvider::Local->value;
    }

    public function verifySignature(array $headers, string|array|null $payload): bool
    {
        $token = (string) config('inbound.providers.local.token', '');

        if ($token !== '') {
            return hash_equals($token, (string) ($headers['x-local-inbound-token'] ?? $headers['X-Local-Inbound-Token'] ?? ''));
        }

        return app()->environment(['local', 'testing'])
            && (bool) config('inbound.providers.local.allow_unsigned', true);
    }

    public function verify(array $headers, string|array|null $payload): bool
    {
        return $this->verifySignature($headers, $payload);
    }

    public function normalizePayload(array $payload): array
    {
        $mailboxAddress = $payload['mailbox_address'] ?? $payload['recipient'] ?? null;

        return [
            'mailbox_address' => $mailboxAddress,
            'from_email' => $payload['from_email'] ?? null,
            'from_name' => $payload['from_name'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'text_body' => $payload['text_body'] ?? null,
            'html_body' => $payload['html_body'] ?? null,
            'recipients' => $payload['recipients'] ?? $this->defaultRecipients($mailboxAddress),
            'attachments' => $payload['attachments'] ?? [],
            'received_at' => $payload['received_at'] ?? now(),
            'intake_source' => 'local',
            'provider_id' => $payload['provider_id'] ?? $payload['provider_message_id'] ?? null,
            'intake_key' => $payload['intake_key'] ?? null,
        ];
    }

    private function defaultRecipients(?string $mailboxAddress): array
    {
        if ($mailboxAddress === null || $mailboxAddress === '') {
            return [];
        }

        return [
            [
                'type' => 'to',
                'email' => $mailboxAddress,
            ],
        ];
    }
}
