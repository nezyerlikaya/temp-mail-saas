<?php

namespace App\Services\Support;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\SupportCategory;
use App\Enums\SupportPriority;
use App\Enums\SupportStatus;
use App\Models\SupportRequest;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class CustomerSuccessIntelligenceService extends Service
{
    public function __construct(
        private readonly SupportAnalyticsService $analytics,
        private readonly CustomerHealthService $health,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $metrics = $this->analytics->report();
        $health = $this->health->review();
        $themes = $this->recurringThemes();
        $onboarding = $this->onboardingIssues();
        $retentionRisks = $this->retentionRisks();
        $opportunities = $this->opportunities($themes, $onboarding);
        $recommendations = collect([
            $themes !== [] ? 'Review recurring support themes during product planning.' : null,
            $onboarding !== [] ? 'Prioritize onboarding friction review.' : null,
            $retentionRisks !== [] ? 'Escalate unresolved high-priority requests for retention review.' : null,
            $health['state'] !== 'healthy' ? 'Review aggregate customer health signals with operations.' : null,
        ])->filter()->values()->all();

        $this->operations->log(
            OperationCategory::System,
            'support_insight_generated',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'support-intelligence',
            'Support insight event recorded.',
            [
                'theme_count' => count($themes),
                'onboarding_issue_count' => count($onboarding),
                'retention_risk_count' => count($retentionRisks),
                'opportunity_count' => count($opportunities),
                'health_state' => $health['state'],
            ],
        );

        return [
            'metrics' => $metrics,
            'health' => $health,
            'recurring_themes' => $themes,
            'onboarding_issues' => $onboarding,
            'retention_risks' => $retentionRisks,
            'opportunities' => $opportunities,
            'recommendations' => $recommendations,
        ];
    }

    private function recurringThemes(): array
    {
        return SupportRequest::query()
            ->selectRaw('category, count(*) as request_count')
            ->groupBy('category')
            ->havingRaw('count(*) >= ?', [(int) config('support-intelligence.analytics.recurring_theme_minimum', 2)])
            ->orderByDesc('request_count')
            ->get()
            ->map(fn (SupportRequest $request): array => ['category' => $request->category->value, 'count' => (int) $request->getAttribute('request_count')])
            ->values()
            ->all();
    }

    private function onboardingIssues(): array
    {
        return SupportRequest::query()
            ->where('category', SupportCategory::Account)
            ->selectRaw('category, count(*) as issue_count')
            ->groupBy('category')
            ->havingRaw('count(*) >= ?', [(int) config('support-intelligence.analytics.onboarding_issue_minimum', 1)])
            ->get()
            ->map(fn (SupportRequest $request): array => ['category' => $request->category->value, 'count' => (int) $request->getAttribute('issue_count')])
            ->values()
            ->all();
    }

    private function retentionRisks(): array
    {
        return SupportRequest::query()
            ->whereNotIn('status', [SupportStatus::Resolved, SupportStatus::Closed])
            ->whereIn('priority', [SupportPriority::Critical, SupportPriority::High])
            ->selectRaw('category, priority, count(*) as risk_count')
            ->groupBy('category', 'priority')
            ->get()
            ->map(fn (SupportRequest $request): array => ['category' => $request->category->value, 'priority' => $request->priority->value, 'count' => (int) $request->getAttribute('risk_count')])
            ->values()
            ->all();
    }

    private function opportunities(array $themes, array $onboarding): array
    {
        return collect([...$themes, ...$onboarding])
            ->map(fn (array $item): array => ['category' => $item['category'], 'demand_count' => $item['count']])
            ->unique('category')
            ->values()
            ->all();
    }
}
