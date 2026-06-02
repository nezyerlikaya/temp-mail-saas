<?php

namespace App\Services\Roadmap;

use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class AutomationLifecycleReviewService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('rule_creation_lifecycle', Schema::hasTable('automation_rules') && (bool) config('automation-roadmap.lifecycle_review.rule_creation_ready', true), 'Rule creation lifecycle is ready for planning review.', 'Rule creation lifecycle needs review.', 'warning'),
            $this->check('execution_lifecycle', Schema::hasTable('automation_executions') && (bool) config('automation-roadmap.lifecycle_review.execution_ready', true), 'Execution lifecycle is ready for planning review.', 'Execution lifecycle needs review.', 'warning'),
            $this->check('audit_lifecycle', Schema::hasTable('operations_events') && (bool) config('automation-roadmap.lifecycle_review.audit_ready', true), 'Audit lifecycle is ready for planning review.', 'Automation audit lifecycle needs review.', 'warning'),
            $this->check('operational_lifecycle', (bool) config('automation-roadmap.lifecycle_review.operations_ready', true), 'Operational lifecycle is ready for planning review.', 'Automation operational lifecycle needs review.', 'warning'),
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
