<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class ScalabilityReviewService extends Service
{
    public function review(): array
    {
        return $this->summarize([
            $this->check('queue_scalability', (bool) config('roadmap.scalability.queue_reviewed', true), 'Queue scalability is reviewed.', 'Queue scalability needs review.', 'warning'),
            $this->check('provider_scalability', (bool) config('roadmap.scalability.provider_reviewed', true), 'Provider scalability is reviewed.', 'Provider scalability needs review.', 'warning'),
            $this->check('domain_scalability', (bool) config('roadmap.scalability.domain_reviewed', true), 'Domain scalability is reviewed.', 'Domain scalability needs review.', 'warning'),
            $this->check('operations_scalability', (bool) config('roadmap.scalability.operations_reviewed', true), 'Operations scalability is reviewed.', 'Operations scalability needs review.', 'warning'),
            $this->check('billing_scalability', (bool) config('roadmap.scalability.billing_reviewed', true), 'Billing scalability is reviewed.', 'Billing scalability needs review.', 'warning'),
        ]);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocked')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocked')->values()->all(),
            'checks' => $checks,
        ];
    }
}
