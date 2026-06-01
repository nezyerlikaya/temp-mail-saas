<?php

namespace App\Services\Roadmap;

use App\Enums\FeatureCandidateRisk;
use App\Enums\FeatureCandidateStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\FeatureCandidate;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class V11ReleasePlanningService extends Service
{
    public function __construct(
        private readonly V11FeaturePrioritizationService $prioritization,
        private readonly FeatureImplementationReadinessService $readiness,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $prioritization = $this->prioritization->report();
        $readiness = $this->readiness->review();
        $phaseOneLimit = (int) config('v11-roadmap.release_planning.phase_one_limit', 3);
        $accepted = collect($prioritization['candidates'])
            ->where('status', FeatureCandidateStatus::Accepted->value)
            ->values();
        $quickWins = $accepted
            ->where('recommendation', 'prioritize')
            ->where('risk', FeatureCandidateRisk::Low->value)
            ->values()
            ->all();
        $highRisk = collect($prioritization['candidates'])
            ->whereIn('risk', [FeatureCandidateRisk::High->value, FeatureCandidateRisk::Critical->value])
            ->values()
            ->all();
        $deferred = collect($prioritization['candidates'])
            ->where('status', FeatureCandidateStatus::Deferred->value)
            ->values()
            ->all();
        $phases = [
            'phase_1' => $accepted->take($phaseOneLimit)->values()->all(),
            'phase_2' => $accepted->skip($phaseOneLimit)->values()->all(),
            'deferred' => $deferred,
        ];

        $this->operations->log(
            OperationCategory::System,
            'v11_release_plan_generated',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'v11-roadmap',
            'v1.1 release plan generated.',
            [
                'candidate_count' => count($prioritization['candidates']),
                'quick_win_count' => count($quickWins),
                'high_risk_count' => count($highRisk),
                'deferred_count' => count($deferred),
                'readiness_status' => $readiness['status'],
            ],
        );

        return [
            'candidate_summary' => [
                'total' => FeatureCandidate::query()->count(),
                'accepted' => FeatureCandidate::query()->where('status', FeatureCandidateStatus::Accepted->value)->count(),
                'deferred' => FeatureCandidate::query()->where('status', FeatureCandidateStatus::Deferred->value)->count(),
            ],
            'prioritization' => $prioritization,
            'implementation_readiness' => $readiness,
            'quick_wins' => $quickWins,
            'high_risk_items' => $highRisk,
            'deferred_items' => $deferred,
            'phases' => $phases,
            'recommendations' => collect([
                $quickWins !== [] ? 'Start v1.1 with accepted low-risk quick wins.' : null,
                $highRisk !== [] ? 'Review high-risk candidates before implementation.' : null,
                ...$readiness['recommendations'],
            ])->filter()->unique()->values()->all(),
        ];
    }
}
