<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class DeveloperOnboardingReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('onboarding_flow', Route::has('api.v1.ping') && (bool) config('api-roadmap.onboarding.flow_ready', true), 'API foundation route is available for onboarding planning.', 'Developer onboarding flow needs review.', 'warning'),
            $this->check('authentication_understanding', (bool) config('api.enabled', true) && (bool) config('api-roadmap.onboarding.authentication_understanding_ready', true), 'API key authentication foundation is available for onboarding review.', 'API authentication guidance needs review.', 'warning'),
            $this->check('integration_readiness', (bool) config('api.usage_logging_enabled', true) && (bool) config('api-roadmap.onboarding.integration_readiness_ready', true), 'Integration readiness signals are available.', 'Developer integration readiness needs review.', 'warning'),
            $this->check('documentation_discoverability', (bool) config('api-roadmap.onboarding.documentation_discoverability_ready', true), 'Documentation discoverability is ready for planning.', 'API documentation discoverability needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'developer_experience_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'api-roadmap',
            'Developer onboarding review completed.',
            [
                'state' => $summary['state'],
                'warning_count' => count($summary['warnings']),
                'blocker_count' => count($summary['blockers']),
            ],
        );

        return $summary;
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
