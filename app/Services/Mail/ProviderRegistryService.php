<?php

namespace App\Services\Mail;

use App\Contracts\Mail\InboundProviderContract;
use App\Services\Mail\Providers\LocalInboundProvider;
use App\Services\Service;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class ProviderRegistryService extends Service
{
    public function register(string $provider, string $class, array $metadata = []): array
    {
        config([
            "mail-providers.providers.{$provider}" => array_merge(
                config("mail-providers.providers.{$provider}", []),
                [
                    'class' => $class,
                    'metadata' => $metadata,
                ],
            ),
        ]);

        return $this->metadata($provider);
    }

    public function resolve(?string $provider = null): InboundProviderContract
    {
        $provider = $this->normalize($provider ?? (string) config('mail-providers.default', 'local'));
        $class = config("mail-providers.providers.{$provider}.class");

        if (! is_string($class) || $class === '') {
            if ($provider === 'local') {
                return app(LocalInboundProvider::class);
            }

            throw ValidationException::withMessages([
                'provider' => 'Inbound provider is not configured.',
            ]);
        }

        $instance = app($class);

        if (! $instance instanceof InboundProviderContract) {
            throw ValidationException::withMessages([
                'provider' => 'Inbound provider does not implement the required contract.',
            ]);
        }

        return $instance;
    }

    public function health(?string $provider = null): array
    {
        $provider = $this->normalize($provider ?? (string) config('mail-providers.default', 'local'));
        $configured = config("mail-providers.providers.{$provider}") !== null;

        return [
            'provider' => $provider,
            'configured' => $configured,
            'enabled' => (bool) config("mail-providers.providers.{$provider}.enabled", false),
            'has_signing_key' => filled((string) config("mail-providers.providers.{$provider}.signing_key", config("inbound.providers.{$provider}.signing_key"))),
            'metadata' => $this->metadata($provider),
        ];
    }

    public function metadata(string $provider): array
    {
        $provider = $this->normalize($provider);

        return Arr::wrap(config("mail-providers.providers.{$provider}.metadata", []));
    }

    public function providers(): array
    {
        return array_keys((array) config('mail-providers.providers', []));
    }

    private function normalize(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }
}
