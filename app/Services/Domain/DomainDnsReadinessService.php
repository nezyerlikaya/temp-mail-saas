<?php

namespace App\Services\Domain;

use App\Models\Domain;
use App\Services\Service;
use Illuminate\Support\Arr;

final class DomainDnsReadinessService extends Service
{
    private const CHECKS = [
        'mx',
        'spf',
        'dkim',
        'dmarc',
        'provider_mapping',
    ];

    public function review(Domain $domain): array
    {
        $checks = collect(self::CHECKS)
            ->map(fn (string $name): array => [
                'name' => $name,
                'ready' => $this->ready($domain, $name),
                'message' => $this->ready($domain, $name)
                    ? strtoupper($name).' readiness is configured.'
                    : strtoupper($name).' readiness requires manual confirmation.',
            ])
            ->all();

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['ready']),
            'checks' => $checks,
            'passed' => collect($checks)->where('ready', true)->values()->all(),
            'pending' => collect($checks)->where('ready', false)->values()->all(),
        ];
    }

    public function provider(Domain $domain): string
    {
        return (string) Arr::get(
            $domain->metadata ?? [],
            'onboarding.provider',
            config('domains.onboarding.provider_mapping.default_provider', 'local'),
        );
    }

    private function ready(Domain $domain, string $check): bool
    {
        return (bool) Arr::get(
            $domain->metadata ?? [],
            "onboarding.dns_readiness.{$check}",
            config("domains.onboarding.dns_readiness.{$check}", false),
        );
    }
}
