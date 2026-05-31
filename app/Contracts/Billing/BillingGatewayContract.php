<?php

namespace App\Contracts\Billing;

interface BillingGatewayContract
{
    public function providerName(): string;

    public function verifyWebhook(string $payload, ?string $signature = null): bool;

    public function normalizeWebhookPayload(array $payload): array;

    public function resolveCustomer(array $payload): array;

    public function resolveSubscription(array $payload): ?array;

    public function resolveInvoice(array $payload): ?array;
}
