<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class TechnicalDebtReviewService extends Service
{
    private const LEVELS = ['critical', 'high', 'medium', 'low'];

    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $this->record('technical_debt_review_started');

        $items = collect(config('roadmap.debt_review.areas', []))
            ->map(fn (array $item): array => [
                'key' => (string) ($item['key'] ?? 'unnamed'),
                'area' => (string) ($item['area'] ?? 'platform'),
                'summary' => (string) ($item['summary'] ?? 'Review required.'),
                'severity' => $this->level($item['severity'] ?? 'low'),
                'priority' => (string) ($item['priority'] ?? 'future'),
                'risk' => $this->level($item['risk'] ?? 'low'),
            ])
            ->values()
            ->all();
        $critical = collect($items)->where('severity', 'critical')->values()->all();
        $high = collect($items)->where('severity', 'high')->values()->all();
        $status = (bool) config('roadmap.debt_review.block_on_critical', true) && $critical !== []
            ? 'blocked'
            : ((bool) config('roadmap.debt_review.warn_on_high', true) && $high !== [] ? 'warning' : 'ready');

        $this->record('technical_debt_review_completed', $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'status' => $status,
            'item_count' => count($items),
            'critical_count' => count($critical),
            'high_count' => count($high),
        ]);

        return [
            'status' => $status,
            'items' => $items,
            'critical' => $critical,
            'high' => $high,
            'recommendations' => collect($items)->pluck('summary')->values()->all(),
        ];
    }

    private function level(mixed $level): string
    {
        $level = (string) $level;

        return in_array($level, self::LEVELS, true) ? $level : 'low';
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'technical-debt-review',
            'Technical debt review event recorded.',
            $metadata,
        );
    }
}
