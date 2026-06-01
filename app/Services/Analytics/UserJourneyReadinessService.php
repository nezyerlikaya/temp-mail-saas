<?php

namespace App\Services\Analytics;

use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class UserJourneyReadinessService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('onboarding_journey', (bool) config('analytics.journeys.onboarding', true) && Route::has('register') && Route::has('login'), 'Onboarding journey readiness is available.', 'Onboarding journey readiness needs review.', 'warning'),
            $this->check('inbox_journey', (bool) config('analytics.journeys.inbox', true) && Route::has('inbox.index') && Route::has('inbox.messages'), 'Inbox journey readiness is available.', 'Inbox journey readiness needs review.', 'blocker'),
            $this->check('premium_journey', (bool) config('analytics.journeys.premium', true), 'Premium journey readiness is documented.', 'Premium journey readiness needs review.', 'warning'),
            $this->check('support_journey', (bool) config('analytics.journeys.support', true), 'Support journey readiness is documented.', 'Support journey readiness needs review.', 'warning'),
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
