<?php

namespace App\Services\ProductIntelligence;

use App\Enums\FeedbackPriority;
use App\Enums\FeedbackType;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\UserFeedback;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class RoadmapInsightService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function generate(): array
    {
        $candidates = UserFeedback::query()
            ->whereIn('type', [FeedbackType::FeatureRequest, FeedbackType::Suggestion])
            ->selectRaw('category, type, count(*) as demand_count')
            ->groupBy('category', 'type')
            ->get()
            ->map(fn (UserFeedback $feedback): array => [
                'category' => $feedback->category->value,
                'opportunity' => $feedback->type === FeedbackType::FeatureRequest ? 'feature_request' : 'suggestion',
                'demand_count' => (int) $feedback->getAttribute('demand_count'),
                'demand_level' => $this->demandLevel((int) $feedback->getAttribute('demand_count')),
            ])
            ->values()
            ->all();
        $risks = UserFeedback::query()
            ->where('type', FeedbackType::Issue)
            ->whereIn('priority', [FeedbackPriority::Critical, FeedbackPriority::High])
            ->selectRaw('category, priority, count(*) as risk_count')
            ->groupBy('category', 'priority')
            ->get()
            ->map(fn (UserFeedback $feedback): array => [
                'category' => $feedback->category->value,
                'priority' => $feedback->priority->value,
                'risk_count' => (int) $feedback->getAttribute('risk_count'),
            ])
            ->values()
            ->all();

        $this->operations->log(
            OperationCategory::System,
            'roadmap_insight_generated',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'product-intelligence',
            'Roadmap insight event recorded.',
            [
                'candidate_count' => count($candidates),
                'risk_count' => count($risks),
            ],
        );

        return [
            'candidates' => $candidates,
            'opportunities' => $candidates,
            'risks' => $risks,
        ];
    }

    private function demandLevel(int $count): string
    {
        if ($count >= (int) config('product-intelligence.roadmap.high_demand_minimum', 5)) {
            return 'high';
        }

        if ($count >= (int) config('product-intelligence.roadmap.medium_demand_minimum', 2)) {
            return 'medium';
        }

        return 'low';
    }
}
