<?php

namespace App\Services\Roadmap;

use App\Enums\FeatureCandidateEffort;
use App\Enums\FeatureCandidatePriority;
use App\Enums\FeatureCandidateRisk;
use App\Models\FeatureCandidate;
use App\Services\Service;

final class V11FeaturePrioritizationService extends Service
{
    public function report(): array
    {
        $candidates = FeatureCandidate::query()
            ->get()
            ->map(fn (FeatureCandidate $candidate): array => $this->score($candidate))
            ->sortByDesc('score')
            ->values()
            ->all();

        return [
            'candidates' => $candidates,
            'top_recommendations' => array_slice($candidates, 0, 5),
        ];
    }

    public function score(FeatureCandidate $candidate): array
    {
        $priority = $this->priorityScore($candidate->priority);
        $impact = $this->priorityScore(FeatureCandidatePriority::tryFrom((string) $candidate->impact) ?? FeatureCandidatePriority::Medium);
        $effort = $this->effortScore($candidate->effort);
        $risk = $this->riskScore($candidate->risk);
        $score = ($priority * (int) config('v11-roadmap.prioritization.priority_weight', 3))
            + ($impact * (int) config('v11-roadmap.prioritization.impact_weight', 3))
            - ($effort * (int) config('v11-roadmap.prioritization.effort_weight', 2))
            - ($risk * (int) config('v11-roadmap.prioritization.risk_weight', 2));

        return [
            'candidate_id' => $candidate->id,
            'category' => $candidate->category->value,
            'priority' => $candidate->priority->value,
            'effort' => $candidate->effort->value,
            'impact' => (string) $candidate->impact,
            'risk' => $candidate->risk->value,
            'status' => $candidate->status->value,
            'score' => $score,
            'recommendation' => $score >= (int) config('v11-roadmap.release_planning.quick_win_score_minimum', 8) ? 'prioritize' : 'review',
        ];
    }

    private function priorityScore(FeatureCandidatePriority $priority): int
    {
        return match ($priority) {
            FeatureCandidatePriority::Critical => 4,
            FeatureCandidatePriority::High => 3,
            FeatureCandidatePriority::Medium => 2,
            FeatureCandidatePriority::Low => 1,
        };
    }

    private function effortScore(FeatureCandidateEffort $effort): int
    {
        return match ($effort) {
            FeatureCandidateEffort::Epic => 4,
            FeatureCandidateEffort::Large => 3,
            FeatureCandidateEffort::Medium => 2,
            FeatureCandidateEffort::Small => 1,
        };
    }

    private function riskScore(FeatureCandidateRisk $risk): int
    {
        return match ($risk) {
            FeatureCandidateRisk::Critical => 4,
            FeatureCandidateRisk::High => 3,
            FeatureCandidateRisk::Medium => 2,
            FeatureCandidateRisk::Low => 1,
        };
    }
}
