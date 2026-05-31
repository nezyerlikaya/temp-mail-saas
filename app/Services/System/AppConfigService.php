<?php

namespace App\Services\System;

use App\Services\Service;

final class AppConfigService extends Service
{
    public function publicName(): string
    {
        return $this->stringConfig('tempmail.public_name', (string) config('app.name', 'Temp Mail SaaS'));
    }

    public function supportEmail(): string
    {
        return $this->stringConfig('tempmail.support_email', 'support@example.com');
    }

    public function defaultLocale(): string
    {
        return $this->stringConfig('tempmail.locale', (string) config('app.locale', 'en'));
    }

    public function fallbackLocale(): string
    {
        return $this->stringConfig('tempmail.fallback_locale', (string) config('app.fallback_locale', 'en'));
    }

    public function timezone(): string
    {
        return $this->stringConfig('tempmail.timezone', (string) config('app.timezone', 'UTC'));
    }

    public function defaultMailboxTtl(): int
    {
        return $this->intConfig('retention.default_mailbox_ttl_minutes', 60);
    }

    public function cleanupChunkSize(): int
    {
        return $this->intConfig('retention.cleanup_chunk_size', 100);
    }

    public function inboundProvider(): string
    {
        return $this->stringConfig('inbound.provider', (string) config('inbound.driver', 'null'));
    }

    public function defaultSeoTitle(): string
    {
        return $this->stringConfig('seo.defaults.title', (string) config('seo.title', $this->publicName()));
    }

    public function defaultSeoDescription(): string
    {
        return $this->stringConfig('seo.defaults.description', (string) config('seo.description', ''));
    }

    private function stringConfig(string $key, string $fallback): string
    {
        $value = config($key);

        return filled($value) ? (string) $value : $fallback;
    }

    private function intConfig(string $key, int $fallback): int
    {
        $value = config($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $fallback;
    }
}
