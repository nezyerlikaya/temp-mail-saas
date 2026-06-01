<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class InboxAccessibilityReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('keyboard_navigation_readiness', (bool) config('inbox-roadmap.accessibility.keyboard_navigation_ready', true), 'Keyboard navigation is ready for planning review.', 'Keyboard navigation needs focused review.', 'warning'),
            $this->check('screen_reader_readiness', (bool) config('inbox-roadmap.accessibility.screen_reader_ready', true), 'Screen reader readiness is available for planning review.', 'Screen reader experience needs focused review.', 'warning'),
            $this->check('color_contrast_review', (bool) config('inbox-roadmap.accessibility.color_contrast_ready', true), 'Color contrast readiness is available for planning review.', 'Color contrast needs focused review.', 'warning'),
            $this->check('responsive_experience_review', (bool) config('inbox-roadmap.accessibility.responsive_ready', true), 'Responsive experience is ready for planning review.', 'Responsive experience needs focused review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'accessibility_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'inbox-roadmap',
            'Inbox accessibility review completed.',
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
