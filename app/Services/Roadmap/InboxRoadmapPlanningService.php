<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class InboxRoadmapPlanningService extends Service
{
    public function __construct(
        private readonly InboxExperienceReviewService $inbox,
        private readonly MailboxLifecycleReviewService $mailbox,
        private readonly MessageWorkflowReviewService $messageWorkflow,
        private readonly InboxAccessibilityReviewService $accessibility,
        private readonly InboxUXPrioritizationService $prioritization,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->operations->log(
            OperationCategory::System,
            'inbox_review_started',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'inbox-roadmap',
            'Inbox roadmap planning review started.',
            ['scope' => 'aggregate_readiness'],
        );

        $reviews = [
            'inbox' => $this->inbox->review(),
            'mailbox' => $this->mailbox->review(),
            'message_workflow' => $this->messageWorkflow->review(),
            'accessibility' => $this->accessibility->review(),
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
            'inbox_roadmap_generated',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'inbox-roadmap',
            'Inbox roadmap planning report generated.',
            [
                'state' => $summary['state'],
                'candidate_count' => $prioritization['candidate_count'],
                'phase_one_count' => count($roadmap['phase_1']),
            ],
        );

        return [
            'summary' => $summary,
            'reviews' => $reviews,
            'ux_prioritization' => $prioritization,
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
        $phaseOneLimit = (int) config('inbox-roadmap.roadmap.phase_one_limit', 4);
        $quickWins = collect($prioritization['quick_wins']);
        $highImpact = collect($prioritization['high_impact_improvements']);
        $phaseOne = $quickWins
            ->merge($highImpact)
            ->unique('key')
            ->sortByDesc('score')
            ->take($phaseOneLimit)
            ->values()
            ->all();

        return [
            'phase_1' => $phaseOne,
            'phase_2' => collect($prioritization['high_impact_improvements'])
                ->reject(fn (array $candidate): bool => collect($phaseOne)->pluck('key')->contains($candidate['key']))
                ->values()
                ->all(),
            'deferred' => $prioritization['deferred_candidates'],
        ];
    }

    private function recommendations(array $reviews, array $prioritization): array
    {
        return collect($reviews)
            ->flatMap(fn (array $review): array => $review['recommendations'])
            ->merge([
                $prioritization['quick_wins'] !== [] ? 'Prioritize low-risk inbox UX quick wins for the first v1.1 slice.' : null,
                $prioritization['deferred_candidates'] !== [] ? 'Keep higher-risk inbox UX candidates in planning until implementation risks are clearer.' : null,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
