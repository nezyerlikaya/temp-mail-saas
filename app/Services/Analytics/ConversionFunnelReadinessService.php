<?php

namespace App\Services\Analytics;

use App\Models\Plan;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class ConversionFunnelReadinessService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('landing_visit', (bool) config('analytics.conversion.landing_visit', true) && Route::has('home'), 'Landing visit readiness is available.', 'Landing visit readiness needs review.', 'blocker'),
            $this->check('mailbox_creation', (bool) config('analytics.conversion.mailbox_creation', true) && Route::has('inbox.generate'), 'Mailbox creation readiness is available.', 'Mailbox creation readiness needs review.', 'blocker'),
            $this->check('inbox_activation', (bool) config('analytics.conversion.inbox_activation', true) && Route::has('inbox.index'), 'Inbox activation readiness is available.', 'Inbox activation readiness needs review.', 'blocker'),
            $this->check('account_registration', (bool) config('analytics.conversion.account_registration', true) && Route::has('register'), 'Account registration readiness is available.', 'Account registration readiness needs review.', 'warning'),
            $this->check('premium_conversion', (bool) config('analytics.conversion.premium_conversion', true) && Plan::query()->where('slug', 'premium')->where('is_active', true)->exists(), 'Premium conversion readiness is available.', 'Premium conversion readiness needs review.', 'warning'),
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
