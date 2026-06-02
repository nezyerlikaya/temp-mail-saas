<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class AutomationCapabilityReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('automation_rule_coverage', Schema::hasTable('automation_rules') && (bool) config('automation-roadmap.automation_review.rule_coverage_ready', true), 'Automation rule foundation is available for coverage planning.', 'Automation rule coverage needs review.', 'blocked'),
            $this->check('automation_safety_controls', Schema::hasTable('automation_executions') && (bool) config('automation-roadmap.automation_review.safety_controls_ready', true), 'Automation execution audit foundation is available.', 'Automation safety controls need review.', 'warning'),
            $this->check('automation_maintainability', (bool) config('automation-roadmap.automation_review.maintainability_ready', true), 'Automation maintainability is ready for planning review.', 'Automation maintainability needs review.', 'warning'),
            $this->check('automation_scalability', (bool) config('automation-roadmap.automation_review.scalability_ready', true), 'Automation scalability is ready for planning review.', 'Automation scalability needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'automation_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'automation-roadmap',
            'Automation capability review completed.',
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
