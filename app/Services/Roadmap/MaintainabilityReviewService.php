<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class MaintainabilityReviewService extends Service
{
    public function review(): array
    {
        return $this->summarize([
            $this->check('code_organization', (bool) config('roadmap.maintainability.code_organization_reviewed', true), 'Code organization is reviewed.', 'Code organization needs review.', 'warning'),
            $this->check('testing_coverage', (bool) config('roadmap.maintainability.testing_coverage_reviewed', true), 'Testing coverage is reviewed.', 'Testing coverage needs review.', 'warning'),
            $this->check('documentation_coverage', (bool) config('roadmap.maintainability.documentation_coverage_reviewed', true), 'Documentation coverage is reviewed.', 'Documentation coverage needs review.', 'warning'),
            $this->check('operational_readiness', (bool) config('roadmap.maintainability.operational_readiness_reviewed', true), 'Operational readiness is reviewed.', 'Operational readiness needs review.', 'warning'),
        ]);
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
            'checks' => $checks,
        ];
    }
}
