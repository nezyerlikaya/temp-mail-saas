<?php

namespace App\Services\Roadmap;

use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class OperationalIntelligenceReviewService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('abuse_intelligence', Schema::hasTable('abuse_events') && (bool) config('automation-roadmap.operational_intelligence.abuse_ready', true), 'Abuse intelligence foundation is available.', 'Abuse intelligence needs review.', 'warning'),
            $this->check('domain_intelligence', Schema::hasTable('domain_health_checks') && (bool) config('automation-roadmap.operational_intelligence.domain_ready', true), 'Domain intelligence foundation is available.', 'Domain intelligence needs review.', 'warning'),
            $this->check('provider_intelligence', Schema::hasTable('provider_activation_audits') && (bool) config('automation-roadmap.operational_intelligence.provider_ready', true), 'Provider intelligence foundation is available.', 'Provider intelligence needs review.', 'warning'),
            $this->check('billing_intelligence', Schema::hasTable('billing_webhook_events') && (bool) config('automation-roadmap.operational_intelligence.billing_ready', true), 'Billing intelligence foundation is available.', 'Billing intelligence needs review.', 'warning'),
            $this->check('governance_intelligence', (bool) config('automation-roadmap.operational_intelligence.governance_ready', true), 'Governance intelligence readiness is available.', 'Governance intelligence needs review.', 'warning'),
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
