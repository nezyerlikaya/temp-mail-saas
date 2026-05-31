<?php

namespace App\Services\Operations;

use App\Enums\SystemHealthStatus;
use App\Models\DomainHealthCheck;
use App\Services\Service;
use Illuminate\Support\Str;

final class DomainHealthService extends Service
{
    public function evaluate(bool $store = true): array
    {
        $domains = $this->domains();

        return collect($domains)
            ->map(fn (string $domain): array => $this->evaluateDomain($domain, $store))
            ->values()
            ->all();
    }

    private function evaluateDomain(string $domain, bool $store): array
    {
        $valid = filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        $score = $valid ? 100 : 25;
        $status = match (true) {
            $score < (int) config('operations.thresholds.domain_critical_score', 40) => SystemHealthStatus::Critical,
            $score < (int) config('operations.thresholds.domain_warning_score', 70) => SystemHealthStatus::Warning,
            default => SystemHealthStatus::Healthy,
        };
        $data = [
            'domain' => $domain,
            'status' => $status,
            'score' => $score,
            'message' => $valid ? 'Domain format is valid.' : 'Domain format needs attention.',
            'checked_at' => now(),
        ];

        if ($store) {
            DomainHealthCheck::query()->create($data);
        }

        if ($status !== SystemHealthStatus::Healthy) {
            app(OperationsLoggerService::class)->log(
                OperationCategory::Domain,
                'domain_health_warning',
                $status === SystemHealthStatus::Critical ? 'critical' : 'warning',
                source: 'domain-health',
                message: 'Domain health needs attention.',
                metadata: ['domain' => $domain, 'score' => $score],
            );
        }

        return [
            ...$data,
            'status' => $status->value,
            'checked_at' => $data['checked_at']->toIso8601String(),
        ];
    }

    private function domains(): array
    {
        $domains = config('domains.public_mailbox.allowed_domains', []);
        $domains = is_array($domains) ? $domains : [];
        $domains[] = (string) config('domains.public_mailbox.default_domain', 'example.test');

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $domain): string => Str::lower(trim((string) $domain)),
            $domains,
        ))));
    }
}
