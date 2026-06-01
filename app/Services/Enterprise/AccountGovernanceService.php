<?php

namespace App\Services\Enterprise;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class AccountGovernanceService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $roles = OrganizationMember::query()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $organizationsWithoutOwners = Organization::query()->whereNull('owner_user_id')->count();
        $ownerMemberships = OrganizationMember::query()->where('role', OrganizationMemberRole::Owner)->count();
        $checks = [
            $this->check('role_distribution', (bool) config('enterprise.readiness.governance.roles_ready', true), 'Organization role distribution review is available.', 'Organization role distribution needs review.', 'warning'),
            $this->check('permission_review', (bool) config('enterprise.readiness.governance.permissions_ready', true) && config('permissions.roles', []) !== [], 'Permission review foundation is available.', 'Permission review foundation needs review.', 'blocked'),
            $this->check('ownership_review', (bool) config('enterprise.readiness.governance.ownership_ready', true) && $organizationsWithoutOwners === 0, 'Organization ownership review is healthy.', 'Organizations without owners need review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'governance_review_completed',
            $summary['status'] === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'enterprise-readiness',
            'Enterprise governance review recorded.',
            [
                'status' => $summary['status'],
                'organization_without_owner_count' => $organizationsWithoutOwners,
                'owner_membership_count' => $ownerMemberships,
                'role_group_count' => count($roles),
            ],
        );

        return [
            ...$summary,
            'role_distribution' => $roles,
            'organizations_without_owners' => $organizationsWithoutOwners,
            'owner_memberships' => $ownerMemberships,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocked')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocked')->values()->all(),
            'checks' => $checks,
        ];
    }
}
