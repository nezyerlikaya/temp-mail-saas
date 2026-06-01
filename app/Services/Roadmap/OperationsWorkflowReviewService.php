<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class OperationsWorkflowReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('incident_workflow', Route::has('admin.audit') && (bool) config('admin-roadmap.operations_workflow.incident_ready', true), 'Incident workflow review has an audit entry point.', 'Incident workflow needs review.', 'warning'),
            $this->check('monitoring_workflow', Route::has('admin.health') && Route::has('admin.queue') && (bool) config('admin-roadmap.operations_workflow.monitoring_ready', true), 'Monitoring workflow routes are available.', 'Monitoring workflow needs review.', 'warning'),
            $this->check('provider_workflow', Route::has('admin.operations') && (bool) config('admin-roadmap.operations_workflow.provider_ready', true), 'Provider workflow can be reviewed through operations.', 'Provider workflow needs review.', 'warning'),
            $this->check('domain_workflow', Route::has('admin.domains') && (bool) config('admin-roadmap.operations_workflow.domain_ready', true), 'Domain workflow route is available.', 'Domain workflow needs review.', 'warning'),
            $this->check('billing_workflow', Route::has('admin.billing') && (bool) config('admin-roadmap.operations_workflow.billing_ready', true), 'Billing workflow route is available.', 'Billing workflow needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'operations_workflow_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'admin-roadmap',
            'Operations workflow review completed.',
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
