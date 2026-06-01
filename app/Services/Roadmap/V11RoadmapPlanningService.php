<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class V11RoadmapPlanningService extends Service
{
    public function __construct(
        private readonly TechnicalDebtReviewService $debt,
        private readonly ArchitectureReviewService $architecture,
        private readonly ScalabilityReviewService $scalability,
        private readonly MaintainabilityReviewService $maintainability,
        private readonly ReleasePrioritizationService $prioritization,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('roadmap_review_started');

        $debt = $this->debt->review();
        $architecture = $this->architecture->review();
        $scalability = $this->scalability->review();
        $maintainability = $this->maintainability->review();
        $priorities = $this->prioritization->summarize();
        $sections = compact('architecture', 'scalability', 'maintainability');
        $blockers = [
            ...$debt['critical'],
            ...$this->issues($sections, 'blockers'),
        ];
        $warnings = [
            ...$debt['high'],
            ...$this->issues($sections, 'warnings'),
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record('roadmap_review_completed', $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'status' => $status,
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
        ]);
        $this->record('roadmap_prioritized', OperationSeverity::Info, [
            'v1_1_count' => $priorities['counts']['v1.1'],
            'v1_2_count' => $priorities['counts']['v1.2'],
            'future_count' => $priorities['counts']['future'],
        ]);

        return [
            'status' => $status,
            'technical_debt' => $debt,
            'architecture' => $architecture,
            'scalability' => $scalability,
            'maintainability' => $maintainability,
            'priorities' => $priorities,
            'opportunities' => $priorities['v1.1'],
            'risks' => collect($debt['items'])->whereIn('risk', ['critical', 'high', 'medium'])->values()->all(),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([
                ...$debt['recommendations'],
                ...collect($warnings)->pluck('message')->all(),
            ])->filter()->unique()->values()->all(),
        ];
    }

    private function issues(array $sections, string $type): array
    {
        return collect($sections)
            ->flatMap(fn (array $section, string $category): array => collect($section[$type])
                ->map(fn (array $issue): array => ['category' => $category, ...$issue])
                ->all())
            ->values()
            ->all();
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'roadmap-review',
            'Roadmap review event recorded.',
            $metadata,
        );
    }
}
