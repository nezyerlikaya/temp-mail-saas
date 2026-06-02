<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class ApiLifecycleReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('versioning_readiness', Route::has('api.v1.ping') && (bool) config('api-roadmap.lifecycle.versioning_ready', true), 'API v1 namespace is available for lifecycle planning.', 'API versioning readiness needs review.', 'blocked'),
            $this->check('deprecation_readiness', (bool) config('api-roadmap.lifecycle.deprecation_ready', true), 'Deprecation readiness is available for planning.', 'API deprecation strategy needs review.', 'warning'),
            $this->check('compatibility_strategy', (bool) config('api-roadmap.lifecycle.compatibility_strategy_ready', true), 'Compatibility strategy is ready for planning review.', 'API compatibility strategy needs review.', 'warning'),
            $this->check('long_term_support_readiness', (bool) config('api-roadmap.lifecycle.long_term_support_ready', true), 'Long-term support readiness is available.', 'API long-term support readiness needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'api_lifecycle_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'api-roadmap',
            'API lifecycle review completed.',
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
