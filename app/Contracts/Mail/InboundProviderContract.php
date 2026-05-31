<?php

namespace App\Contracts\Mail;

interface InboundProviderContract
{
    public function provider(): string;

    public function verifySignature(array $headers, string|array|null $payload): bool;

    public function normalizePayload(array $payload): array;
}
