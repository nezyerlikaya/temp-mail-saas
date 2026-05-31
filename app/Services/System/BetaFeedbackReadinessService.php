<?php

namespace App\Services\System;

use App\Services\Service;

final class BetaFeedbackReadinessService extends Service
{
    public function report(): array
    {
        $checks = [
            $this->check('feedback_collection', (bool) config('production.public_beta.feedback.collection_documented', true), 'Feedback collection process is documented.', 'Feedback collection process needs documentation.', 'warning'),
            $this->check('issue_intake', (bool) config('production.public_beta.feedback.issue_intake_documented', true), 'Issue intake process is documented.', 'Issue intake process needs documentation.', 'warning'),
            $this->check('operational_response', (bool) config('production.public_beta.feedback.operational_response_documented', true), 'Operational response process is documented.', 'Operational response process needs documentation.', 'warning'),
        ];
        $warnings = collect($checks)->where('status', 'warning')->values()->all();

        return [
            'status' => $warnings === [] ? 'ready' : 'warning',
            'checks' => $checks,
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'blockers' => [],
            'warnings' => $warnings,
            'recommendations' => collect($warnings)
                ->map(fn (array $warning): string => $warning['message'])
                ->push('Keep feedback intake manual until an external support system is approved.')
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $failedStatus = 'warning'): array
    {
        return [
            'category' => 'support',
            'name' => $name,
            'passed' => $passed,
            'status' => $passed ? 'passed' : $failedStatus,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }
}
