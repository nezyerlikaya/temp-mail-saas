<?php

namespace App\Services\Analytics;

use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class RetentionReadinessService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('revisit_readiness', (bool) config('analytics.retention.revisit', true), 'Revisit readiness is documented.', 'Revisit readiness needs review.', 'warning'),
            $this->check('account_retention', (bool) config('analytics.retention.account_retention', true) && Schema::hasTable('users'), 'Account retention readiness is available.', 'Account retention readiness needs review.', 'warning'),
            $this->check('premium_retention', (bool) config('analytics.retention.premium_retention', true) && Schema::hasTable('user_plan_assignments'), 'Premium retention readiness is available.', 'Premium retention readiness needs review.', 'warning'),
            $this->check('lifecycle_readiness', (bool) config('analytics.retention.lifecycle', true) && Schema::hasTable('email_messages'), 'Lifecycle readiness is available.', 'Lifecycle readiness needs review.', 'blocker'),
        ];

        return $this->summarize($checks);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocker')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }
}
