<?php

namespace App\Services\Analytics;

use App\Services\Service;

final class AnalyticsCertificationService extends Service
{
    public function __construct(
        private readonly ConversionFunnelReadinessService $conversion,
        private readonly UserJourneyReadinessService $journey,
        private readonly RetentionReadinessService $retention,
    ) {}

    public function certify(array $analytics): array
    {
        $conversion = $this->conversion->review();
        $journey = $this->journey->review();
        $retention = $this->retention->review();
        $checks = [
            $this->check('analytics_readiness', ! (bool) config('analytics.certification.analytics', true) || $analytics['blockers'] === [], 'Analytics readiness is certified.', 'Analytics readiness is blocked.', 'blocked'),
            $this->check('conversion_readiness', ! (bool) config('analytics.certification.conversion', true) || $conversion['blockers'] === [], 'Conversion readiness is certified.', 'Conversion readiness is blocked.', 'blocked'),
            $this->check('journey_readiness', ! (bool) config('analytics.certification.journey', true) || $journey['blockers'] === [], 'User journey readiness is certified.', 'User journey readiness is blocked.', 'blocked'),
            $this->check('retention_readiness', ! (bool) config('analytics.certification.retention', true) || $retention['blockers'] === [], 'Retention readiness is certified.', 'Retention readiness is blocked.', 'blocked'),
        ];
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = [
            ...$conversion['warnings'],
            ...$journey['warnings'],
            ...$retention['warnings'],
        ];

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified'),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'checks' => $checks,
            'conversion' => $conversion,
            'journey' => $journey,
            'retention' => $retention,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }
}
