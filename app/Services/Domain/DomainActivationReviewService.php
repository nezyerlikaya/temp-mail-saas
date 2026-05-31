<?php

namespace App\Services\Domain;

use App\Models\Domain;
use App\Services\Service;

final class DomainActivationReviewService extends Service
{
    public function __construct(private readonly DomainDnsReadinessService $dns) {}

    public function review(Domain $domain): array
    {
        $dns = $this->dns->review($domain);
        $ready = collect($dns['checks'])->keyBy('name');
        $checks = [
            $this->check('mx_readiness', $this->optional('mx', $ready), 'MX readiness is confirmed.', 'MX readiness requires manual confirmation.'),
            $this->check('spf_readiness', $this->optional('spf', $ready), 'SPF readiness is confirmed.', 'SPF readiness requires manual confirmation.'),
            $this->check('dkim_readiness', $this->optional('dkim', $ready), 'DKIM readiness is confirmed.', 'DKIM readiness requires manual confirmation.'),
            $this->check('dmarc_readiness', $this->optional('dmarc', $ready), 'DMARC readiness is confirmed.', 'DMARC readiness requires manual confirmation.'),
            $this->check('provider_mapping_readiness', $this->optional('provider_mapping', $ready), 'Provider mapping readiness is confirmed.', 'Provider mapping readiness requires manual confirmation.'),
        ];

        return $this->summarize($checks);
    }

    private function optional(string $name, mixed $checks): bool
    {
        return ! (bool) config("domains.live_activation.review.require_{$name}", true)
            || (bool) data_get($checks->get($name), 'ready', false);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : 'blocker',
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function summarize(array $checks): array
    {
        return [
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => [],
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }
}
