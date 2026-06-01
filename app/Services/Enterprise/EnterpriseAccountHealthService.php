<?php

namespace App\Services\Enterprise;

use App\Enums\BillingInvoiceStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\OrganizationMemberStatus;
use App\Enums\OrganizationStatus;
use App\Enums\SupportStatus;
use App\Models\BillingInvoice;
use App\Models\OperationsEvent;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SupportRequest;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class EnterpriseAccountHealthService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $organizations = [
            'total' => Organization::query()->count(),
            'inactive' => Organization::query()->where('status', OrganizationStatus::Inactive)->count(),
            'suspended' => Organization::query()->where('status', OrganizationStatus::Suspended)->count(),
        ];
        $memberships = [
            'total' => OrganizationMember::query()->count(),
            'inactive' => OrganizationMember::query()->whereIn('status', [OrganizationMemberStatus::Suspended, OrganizationMemberStatus::Removed])->count(),
        ];
        $billingIssues = BillingInvoice::query()->whereIn('status', [BillingInvoiceStatus::Open, BillingInvoiceStatus::Uncollectible])->count();
        $supportIssues = SupportRequest::query()->whereNotIn('status', [SupportStatus::Resolved, SupportStatus::Closed])->count();
        $operationalRisks = OperationsEvent::query()->whereIn('severity', [OperationSeverity::Warning, OperationSeverity::Critical])->count();
        $score = $organizations['inactive'] * (int) config('enterprise.readiness.account_health.inactive_organization_weight', 1)
            + $organizations['suspended'] * (int) config('enterprise.readiness.account_health.suspended_organization_weight', 3)
            + $memberships['inactive'] * (int) config('enterprise.readiness.account_health.inactive_membership_weight', 1)
            + $billingIssues * (int) config('enterprise.readiness.account_health.billing_issue_weight', 2)
            + $supportIssues * (int) config('enterprise.readiness.account_health.support_issue_weight', 1)
            + $operationalRisks * (int) config('enterprise.readiness.account_health.operational_risk_weight', 1);
        $state = $score >= (int) config('enterprise.readiness.account_health.risk_score', 5)
            ? 'risk'
            : ($score >= (int) config('enterprise.readiness.account_health.attention_score', 2) ? 'attention' : 'healthy');

        $this->operations->log(
            OperationCategory::System,
            'enterprise_review_completed',
            $state === 'risk' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'enterprise-readiness',
            'Enterprise account health review recorded.',
            [
                'state' => $state,
                'score' => $score,
                'organization_count' => $organizations['total'],
                'membership_count' => $memberships['total'],
                'billing_issue_count' => $billingIssues,
                'support_issue_count' => $supportIssues,
                'operational_risk_count' => $operationalRisks,
            ],
        );

        return [
            'state' => $state,
            'score' => $score,
            'organizations' => $organizations,
            'memberships' => $memberships,
            'billing' => ['issue_count' => $billingIssues],
            'support' => ['issue_count' => $supportIssues],
            'operations' => ['risk_count' => $operationalRisks],
        ];
    }
}
