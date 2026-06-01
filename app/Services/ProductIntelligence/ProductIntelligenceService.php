<?php

namespace App\Services\ProductIntelligence;

use App\Enums\FeedbackType;
use App\Models\UserFeedback;
use App\Services\Service;

final class ProductIntelligenceService extends Service
{
    public function __construct(
        private readonly FeedbackService $feedback,
        private readonly RoadmapInsightService $roadmap,
    ) {}

    public function report(): array
    {
        $feedback = $this->feedback->aggregate();
        $trends = $this->trends();
        $recurringIssues = $this->recurringIssues();
        $featureRequests = $this->featureRequests();
        $roadmap = $this->roadmap->generate();

        return [
            'feedback' => $feedback,
            'trends' => $trends,
            'recurring_issues' => $recurringIssues,
            'feature_requests' => $featureRequests,
            'roadmap' => $roadmap,
            'recommendations' => $this->recommendations($trends, $recurringIssues, $featureRequests),
        ];
    }

    private function trends(): array
    {
        return UserFeedback::query()
            ->selectRaw('category, count(*) as trend_count')
            ->groupBy('category')
            ->havingRaw('count(*) >= ?', [(int) config('product-intelligence.trends.minimum_count', 2)])
            ->orderByDesc('trend_count')
            ->get()
            ->map(fn (UserFeedback $feedback): array => [
                'category' => $feedback->category->value,
                'count' => (int) $feedback->getAttribute('trend_count'),
            ])
            ->values()
            ->all();
    }

    private function recurringIssues(): array
    {
        return UserFeedback::query()
            ->where('type', FeedbackType::Issue)
            ->selectRaw('category, count(*) as issue_count')
            ->groupBy('category')
            ->havingRaw('count(*) >= ?', [(int) config('product-intelligence.trends.recurring_issue_minimum', 2)])
            ->orderByDesc('issue_count')
            ->get()
            ->map(fn (UserFeedback $feedback): array => [
                'category' => $feedback->category->value,
                'count' => (int) $feedback->getAttribute('issue_count'),
            ])
            ->values()
            ->all();
    }

    private function featureRequests(): array
    {
        return UserFeedback::query()
            ->where('type', FeedbackType::FeatureRequest)
            ->selectRaw('category, count(*) as request_count')
            ->groupBy('category')
            ->havingRaw('count(*) >= ?', [(int) config('product-intelligence.trends.feature_request_minimum', 1)])
            ->orderByDesc('request_count')
            ->get()
            ->map(fn (UserFeedback $feedback): array => [
                'category' => $feedback->category->value,
                'count' => (int) $feedback->getAttribute('request_count'),
            ])
            ->values()
            ->all();
    }

    private function recommendations(array $trends, array $recurringIssues, array $featureRequests): array
    {
        return collect([
            $trends !== [] ? 'Review the strongest feedback categories during roadmap planning.' : null,
            $recurringIssues !== [] ? 'Prioritize recurring issue review before feature expansion.' : null,
            $featureRequests !== [] ? 'Compare feature request demand with v1.1 roadmap priorities.' : null,
        ])->filter()->values()->all();
    }
}
