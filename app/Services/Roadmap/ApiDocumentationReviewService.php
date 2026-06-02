<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class ApiDocumentationReviewService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('documentation_coverage', (bool) config('api-roadmap.documentation.coverage_ready', true), 'API documentation coverage is ready for planning review.', 'API documentation coverage needs review.', 'warning'),
            $this->check('examples_coverage', (bool) config('api-roadmap.documentation.examples_ready', true), 'API examples coverage is ready for planning review.', 'API examples coverage needs review.', 'warning'),
            $this->check('error_documentation', (bool) config('api-roadmap.documentation.errors_ready', true), 'API error documentation is ready for planning review.', 'API error documentation needs review.', 'warning'),
            $this->check('integration_guidance', (bool) config('api-roadmap.documentation.integration_guidance_ready', true), 'API integration guidance is ready for planning review.', 'API integration guidance needs review.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = collect($checks)->where('classification', 'warning')->values()->all();

        return [
            'state' => $blockers !== [] ? 'improvement-needed' : ($warnings !== [] ? 'acceptable' : 'excellent'),
            'warnings' => $warnings,
            'blockers' => $blockers,
            'recommendations' => collect($checks)->reject(fn (array $check): bool => $check['passed'])->pluck('message')->values()->all(),
            'checks' => $checks,
        ];
    }
}
