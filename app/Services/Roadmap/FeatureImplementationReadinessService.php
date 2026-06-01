<?php

namespace App\Services\Roadmap;

use App\Enums\FeatureCandidateRisk;
use App\Models\FeatureCandidate;
use App\Services\Service;

final class FeatureImplementationReadinessService extends Service
{
    public function review(): array
    {
        $criticalRisk = FeatureCandidate::query()->where('risk', FeatureCandidateRisk::Critical->value)->count();
        $highRisk = FeatureCandidate::query()->where('risk', FeatureCandidateRisk::High->value)->count();
        $checks = [
            $this->check('dependency_readiness', (bool) config('v11-roadmap.readiness.dependencies_ready', true), 'Dependencies are ready for v1.1 planning.', 'Dependencies need review before implementation.', 'blocked'),
            $this->check('test_readiness', (bool) config('v11-roadmap.readiness.tests_ready', true), 'Test readiness is available.', 'Test readiness needs review.', 'blocked'),
            $this->check('documentation_readiness', (bool) config('v11-roadmap.readiness.documentation_ready', true), 'Documentation readiness is available.', 'Documentation readiness needs review.', 'warning'),
            $this->check('operational_readiness', (bool) config('v11-roadmap.readiness.operations_ready', true), 'Operational readiness is available.', 'Operational readiness needs review.', 'warning'),
            $this->check('critical_risk_review', ! (bool) config('v11-roadmap.risk.block_critical', true) || $criticalRisk === 0, 'No critical-risk candidates are currently blocking implementation.', 'Critical-risk candidates require review.', 'blocked'),
            $this->check('high_risk_review', ! (bool) config('v11-roadmap.risk.warn_high', true) || $highRisk === 0, 'High-risk candidate count is acceptable.', 'High-risk candidates need review.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocked')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocked')->values()->all(),
            'recommendations' => collect($checks)->reject(fn (array $check): bool => $check['passed'])->pluck('message')->values()->all(),
            'checks' => $checks,
        ];
    }
}
