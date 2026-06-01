<?php

namespace App\Services\Roadmap;

use App\Services\Service;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class MessageWorkflowReviewService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('message_arrival_flow', Schema::hasTable('inbound_mail_intakes') && Schema::hasTable('email_messages') && (bool) config('inbox-roadmap.message_workflow.arrival_ready', true), 'Message arrival foundations are available.', 'Message arrival flow needs review.', 'blocked'),
            $this->check('message_reading_flow', Route::has('inbox.messages.show') && (bool) config('inbox-roadmap.message_workflow.reading_ready', true), 'Message reading route is available.', 'Message reading flow needs review.', 'warning'),
            $this->check('attachment_flow', Schema::hasTable('email_attachments') && (bool) config('inbox-roadmap.message_workflow.attachment_ready', true), 'Attachment metadata foundation is available for UX planning.', 'Attachment workflow needs review.', 'warning'),
            $this->check('message_retention_flow', (int) config('retention.email.tiers.standard', 0) > 0 && (bool) config('inbox-roadmap.message_workflow.retention_ready', true), 'Message retention defaults are available.', 'Message retention flow needs review.', 'warning'),
        ];

        return $this->summarize($checks);
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
