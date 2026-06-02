<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class IntelligenceCapabilityReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('scoring_systems', Schema::hasTable('intelligence_scores') && (bool) config('automation-roadmap.intelligence_review.scoring_ready', true), 'Intelligence score foundation is available.', 'Intelligence scoring systems need review.', 'blocked'),
            $this->check('intelligence_aggregation', (bool) config('automation-roadmap.intelligence_review.aggregation_ready', true), 'Intelligence aggregation is ready for planning.', 'Intelligence aggregation needs review.', 'warning'),
            $this->check('trend_analysis_readiness', (bool) config('automation-roadmap.intelligence_review.trend_analysis_ready', true), 'Trend analysis is ready for planning review.', 'Trend analysis readiness needs review.', 'warning'),
            $this->check('operational_insight_readiness', (bool) config('automation-roadmap.intelligence_review.operational_insight_ready', true), 'Operational insight readiness is available.', 'Operational insight readiness needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'intelligence_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'automation-roadmap',
            'Intelligence capability review completed.',
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
