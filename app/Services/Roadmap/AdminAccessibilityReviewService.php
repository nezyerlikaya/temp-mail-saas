<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class AdminAccessibilityReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('keyboard_navigation_readiness', (bool) config('admin-roadmap.accessibility.keyboard_navigation_ready', true), 'Keyboard navigation is ready for admin planning review.', 'Keyboard navigation needs admin review.', 'warning'),
            $this->check('focus_management_review', (bool) config('admin-roadmap.accessibility.focus_management_ready', true), 'Focus management is ready for admin planning review.', 'Focus management needs admin review.', 'warning'),
            $this->check('screen_reader_readiness', (bool) config('admin-roadmap.accessibility.screen_reader_ready', true), 'Screen reader readiness is available for admin planning review.', 'Screen reader experience needs admin review.', 'warning'),
            $this->check('responsive_administration_readiness', (bool) config('admin-roadmap.accessibility.responsive_admin_ready', true), 'Responsive administration readiness is available.', 'Responsive administration needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'admin_accessibility_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'admin-roadmap',
            'Admin accessibility review completed.',
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
