<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class ApiUsabilityReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('endpoint_discoverability', Route::has('api.v1.ping') && (bool) config('api-roadmap.api_review.endpoint_discoverability_ready', true), 'Versioned API foundation route is available for discoverability review.', 'API endpoint discoverability needs review.', 'blocked'),
            $this->check('endpoint_consistency', (bool) config('api-roadmap.api_review.consistency_ready', true), 'API consistency is ready for planning review.', 'API consistency needs review.', 'warning'),
            $this->check('naming_conventions', str_starts_with((string) Route::getRoutes()->getByName('api.v1.ping')?->uri(), 'api/v1/') && (bool) config('api-roadmap.api_review.naming_conventions_ready', true), 'Versioned API naming convention is available.', 'API naming conventions need review.', 'warning'),
            $this->check('response_standards', (bool) config('api-roadmap.api_review.response_standards_ready', true), 'API response standards are ready for planning review.', 'API response standards need review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'api_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'api-roadmap',
            'API usability review completed.',
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
