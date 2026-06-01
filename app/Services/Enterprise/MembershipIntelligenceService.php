<?php

namespace App\Services\Enterprise;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\OrganizationMemberStatus;
use App\Models\OrganizationMember;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class MembershipIntelligenceService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function report(): array
    {
        $windowDays = max(1, (int) config('enterprise.readiness.membership.growth_window_days', 30));
        $growth = OrganizationMember::query()->where('joined_at', '>=', now()->subDays($windowDays))->count();
        $inactive = OrganizationMember::query()->whereIn('status', [OrganizationMemberStatus::Suspended, OrganizationMemberStatus::Removed])->count();
        $active = OrganizationMember::query()->where('status', OrganizationMemberStatus::Active)->count();
        $statusDistribution = OrganizationMember::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $state = $inactive >= (int) config('enterprise.readiness.membership.inactive_warning_count', 1) ? 'warning' : 'ready';

        $this->operations->log(
            OperationCategory::System,
            'membership_review_completed',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'enterprise-readiness',
            'Enterprise membership review recorded.',
            [
                'state' => $state,
                'active_count' => $active,
                'inactive_count' => $inactive,
                'growth_count' => $growth,
                'window_days' => $windowDays,
            ],
        );

        return [
            'status' => $state,
            'membership_growth' => ['window_days' => $windowDays, 'joined_count' => $growth],
            'membership_risk' => ['inactive_count' => $inactive],
            'inactive_memberships' => $inactive,
            'active_memberships' => $active,
            'status_distribution' => $statusDistribution,
            'insights' => $inactive > 0 ? ['Review inactive membership trends.'] : [],
        ];
    }
}
