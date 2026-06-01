<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class MailboxLifecycleReviewService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function review(): array
    {
        $checks = [
            $this->check('mailbox_creation_flow', Route::has('inbox.generate') && (bool) config('inbox-roadmap.mailbox_lifecycle.creation_ready', true), 'Mailbox creation flow is ready for UX planning.', 'Mailbox creation flow needs review.', 'blocked'),
            $this->check('mailbox_usage_flow', Route::has('inbox.index') && Route::has('inbox.messages') && (bool) config('inbox-roadmap.mailbox_lifecycle.usage_ready', true), 'Mailbox usage flow is reviewable.', 'Mailbox usage flow needs review.', 'warning'),
            $this->check('mailbox_expiration_flow', (int) config('retention.default_mailbox_ttl_minutes', 0) > 0 && (bool) config('inbox-roadmap.mailbox_lifecycle.expiration_ready', true), 'Mailbox expiration defaults are available.', 'Mailbox expiration expectations need review.', 'warning'),
            $this->check('mailbox_cleanup_flow', (bool) config('retention.enabled', true) && (bool) config('inbox-roadmap.mailbox_lifecycle.cleanup_ready', true), 'Cleanup readiness is available for lifecycle planning.', 'Cleanup flow needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'mailbox_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'inbox-roadmap',
            'Mailbox lifecycle review completed.',
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
