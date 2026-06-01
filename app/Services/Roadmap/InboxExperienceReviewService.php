<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class InboxExperienceReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('inbox_route', Route::has('inbox.index') && (bool) config('inbox-roadmap.inbox_review.usability_ready', true), 'Public inbox route is available for usability review.', 'Public inbox usability needs review.', 'blocked'),
            $this->check('message_visibility', Route::has('inbox.messages') && Route::has('inbox.messages.show') && (bool) config('inbox-roadmap.inbox_review.message_visibility_ready', true), 'Message visibility routes are available.', 'Message visibility needs review.', 'warning'),
            $this->check('polling_experience', Route::has('inbox.messages') && (bool) config('inbox-roadmap.inbox_review.polling_ready', true), 'Polling experience is ready for v1.1 planning.', 'Polling expectations need UX review.', 'warning'),
            $this->check('mailbox_discoverability', Route::has('inbox.generate') && (bool) config('inbox-roadmap.inbox_review.mailbox_discoverability_ready', true), 'Mailbox generation is discoverable for planning.', 'Mailbox discoverability needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'inbox_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'inbox-roadmap',
            'Inbox experience review completed.',
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
