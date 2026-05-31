<?php

namespace App\Services\System;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class PublicBetaCertificationService extends Service
{
    public function __construct(
        private readonly PublicBetaReadinessService $readiness,
        private readonly RC3CertificationService $rc3,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $beta = $this->readiness->report();
        $rc3 = $this->rc3->report();
        $blockers = [
            ...$beta['blockers'],
            ...($this->rc3Required() && $rc3['status'] === 'blocked' ? $this->rc3Items($rc3['blockers'], 'blocked') : []),
        ];
        $warnings = [
            ...$beta['warnings'],
            ...($this->rc3Required() && $rc3['status'] === 'warning' ? $this->rc3Items($rc3['warnings'], 'warning') : []),
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified');

        $this->operations->log(
            OperationCategory::System,
            'beta_certification_completed',
            $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'public-beta-certification',
            'Public beta certification event recorded.',
            ['status' => $status],
        );

        return [
            'target' => (string) config('production.public_beta.target', 'public-beta'),
            'status' => $status,
            'summary' => $this->summary($status, $blockers, $warnings),
            'readiness' => $beta,
            'rc3' => $rc3,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([...$beta['recommendations'], ...$rc3['recommendations']])
                ->push('Do not open public beta until blockers are resolved and warnings are accepted.')
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function rc3Required(): bool
    {
        return (bool) config('production.public_beta.require_rc3_certified', true);
    }

    private function rc3Items(array $items, string $severity): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'category' => $item['category'] ?? 'operations',
                'name' => 'rc3_'.$item['name'],
                'passed' => false,
                'status' => $severity,
                'message' => $item['message'],
            ])
            ->all();
    }

    private function summary(string $status, array $blockers, array $warnings): string
    {
        return match ($status) {
            'blocked' => 'Public beta is blocked by '.count($blockers).' blocker(s).',
            'warning' => 'Public beta requires review of '.count($warnings).' warning(s).',
            default => 'Public beta readiness is certified.',
        };
    }
}
