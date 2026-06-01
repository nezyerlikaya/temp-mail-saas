<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class ArchitectureReviewService extends Service
{
    public function review(): array
    {
        return $this->summarize([
            $this->check('module_boundaries', (bool) config('roadmap.architecture.module_boundaries_documented', true), 'Module boundaries are documented.', 'Module boundary documentation needs review.', 'warning'),
            $this->check('service_responsibilities', (bool) config('roadmap.architecture.service_responsibilities_reviewed', true), 'Service responsibilities are reviewed.', 'Service responsibilities need review.', 'warning'),
            $this->check('dependency_structure', (bool) config('roadmap.architecture.dependency_structure_reviewed', true), 'Dependency structure is reviewed.', 'Dependency structure needs review.', 'warning'),
            $this->check('maintainability', (bool) config('roadmap.architecture.maintainability_reviewed', true), 'Architecture maintainability is reviewed.', 'Architecture maintainability needs review.', 'warning'),
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
