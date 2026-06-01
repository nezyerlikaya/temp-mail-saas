<?php

namespace App\Services\Governance;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class OperationalMaturityService extends Service
{
    private const ACCEPTED = ['stable', 'improving', 'developing'];

    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $checks = [
            $this->check('process_maturity', config('governance.maturity.process', 'stable')),
            $this->check('testing_maturity', config('governance.maturity.testing', 'stable')),
            $this->check('monitoring_maturity', config('governance.maturity.monitoring', 'stable')),
            $this->check('documentation_maturity', config('governance.maturity.documentation', 'stable')),
        ];
        $status = collect($checks)->where('classification', 'blocked')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready');

        $this->operations->log(
            OperationCategory::System,
            'maturity_review_completed',
            $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'governance',
            'Operational maturity review recorded.',
            [
                'status' => $status,
                'warning_count' => collect($checks)->where('classification', 'warning')->count(),
                'blocker_count' => collect($checks)->where('classification', 'blocked')->count(),
            ],
        );

        return [
            'status' => $status,
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocked')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function check(string $name, mixed $level): array
    {
        $level = (string) $level;
        $classification = in_array($level, self::ACCEPTED, true) ? ($level === 'developing' ? 'warning' : 'passed') : 'blocked';

        return [
            'name' => $name,
            'level' => $level,
            'passed' => $classification === 'passed',
            'classification' => $classification,
            'message' => str($name)->replace('_', ' ')->headline().' is '.$level.'.',
        ];
    }
}
