<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class AdminWorkflowReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('daily_admin_tasks', Route::has('admin.index') && (bool) config('admin-roadmap.admin_workflow.daily_tasks_ready', true), 'Daily admin task entry point is available.', 'Daily admin task flow needs review.', 'blocked'),
            $this->check('navigation_efficiency', Route::has('admin.operations') && Route::has('admin.health') && Route::has('admin.queue') && (bool) config('admin-roadmap.admin_workflow.navigation_ready', true), 'Admin navigation routes are ready for workflow review.', 'Admin navigation efficiency needs review.', 'warning'),
            $this->check('information_discoverability', Route::has('admin.audit') && Route::has('admin.localization') && (bool) config('admin-roadmap.admin_workflow.discoverability_ready', true), 'Admin information discovery paths are available.', 'Admin information discoverability needs review.', 'warning'),
            $this->check('workflow_friction_points', (bool) config('admin-roadmap.admin_workflow.friction_review_ready', true), 'Workflow friction review is ready for planning.', 'Workflow friction points need review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'admin_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'admin-roadmap',
            'Admin workflow review completed.',
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
