<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class ApiRoadmapPlanningService extends Service
{
    public function __construct(
        private readonly ApiUsabilityReviewService $api,
        private readonly ApiLifecycleReviewService $lifecycle,
        private readonly DeveloperOnboardingReviewService $onboarding,
        private readonly ApiDocumentationReviewService $documentation,
        private readonly DeveloperExperiencePrioritizationService $prioritization,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->operations->log(
            OperationCategory::System,
            'api_review_started',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'api-roadmap',
            'API roadmap planning review started.',
            ['scope' => 'aggregate_readiness'],
        );

        $reviews = [
            'api' => $this->api->review(),
            'lifecycle' => $this->lifecycle->review(),
            'onboarding' => $this->onboarding->review(),
            'documentation' => $this->documentation->review(),
        ];
        $prioritization = $this->prioritization->report($reviews);
        $roadmap = $this->roadmap($prioritization);
        $summary = [
            'state' => $this->state($reviews),
            'warning_count' => collect($reviews)->sum(fn (array $review): int => count($review['warnings'])),
            'blocker_count' => collect($reviews)->sum(fn (array $review): int => count($review['blockers'])),
        ];

        $this->operations->log(
            OperationCategory::System,
            'api_roadmap_generated',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'api-roadmap',
            'API roadmap planning report generated.',
            [
                'state' => $summary['state'],
                'candidate_count' => $prioritization['candidate_count'],
                'phase_one_count' => count($roadmap['phase_1']),
            ],
        );

        return [
            'summary' => $summary,
            'reviews' => $reviews,
            'dx_prioritization' => $prioritization,
            'roadmap' => $roadmap,
            'recommendations' => $this->recommendations($reviews, $prioritization),
        ];
    }

    private function state(array $reviews): string
    {
        $states = collect($reviews)->pluck('state');

        if ($states->contains('improvement-needed')) {
            return 'improvement-needed';
        }

        return $states->contains('acceptable') ? 'acceptable' : 'excellent';
    }

    private function roadmap(array $prioritization): array
    {
        $phaseOneLimit = (int) config('api-roadmap.roadmap.phase_one_limit', 4);
        $quickWins = collect($prioritization['quick_wins']);
        $onboarding = collect($prioritization['onboarding_improvements']);
        $phaseOne = $quickWins
            ->merge($onboarding)
            ->unique('key')
            ->sortByDesc('score')
            ->take($phaseOneLimit)
            ->values()
            ->all();

        return [
            'phase_1' => $phaseOne,
            'phase_2' => collect($prioritization['documentation_improvements'])
                ->reject(fn (array $candidate): bool => collect($phaseOne)->pluck('key')->contains($candidate['key']))
                ->values()
                ->all(),
            'deferred' => $prioritization['deferred_dx_candidates'],
        ];
    }

    private function recommendations(array $reviews, array $prioritization): array
    {
        return collect($reviews)
            ->flatMap(fn (array $review): array => $review['recommendations'])
            ->merge([
                $prioritization['quick_wins'] !== [] ? 'Prioritize low-risk API DX quick wins for the first v1.1 API slice.' : null,
                $prioritization['onboarding_improvements'] !== [] ? 'Review developer onboarding improvements before public API expansion.' : null,
                $prioritization['deferred_dx_candidates'] !== [] ? 'Keep higher-risk DX candidates deferred until implementation risk is clearer.' : null,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
