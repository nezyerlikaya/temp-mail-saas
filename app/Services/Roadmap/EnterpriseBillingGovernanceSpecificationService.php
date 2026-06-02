<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class EnterpriseBillingGovernanceSpecificationService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'billing_domains' => config('enterprise-billing-governance.billing_domains', []),
            'ownership_governance' => config('enterprise-billing-governance.ownership_governance', []),
            'seat_types' => config('enterprise-billing-governance.seat_types', []),
            'seat_lifecycle' => config('enterprise-billing-governance.seat_lifecycle', []),
            'subscription_governance' => config('enterprise-billing-governance.subscription_governance', []),
            'billing_roles' => config('enterprise-billing-governance.billing_roles', []),
            'cost_governance' => config('enterprise-billing-governance.cost_governance', []),
            'usage_governance' => config('enterprise-billing-governance.usage_governance', []),
            'contract_model' => config('enterprise-billing-governance.contract_model', []),
            'billing_risks' => config('enterprise-billing-governance.billing_risks', []),
            'billing_audit_events' => config('enterprise-billing-governance.billing_audit_events', []),
            'reporting_readiness' => config('enterprise-billing-governance.reporting_readiness', []),
            'financial_governance' => config('enterprise-billing-governance.financial_governance', []),
            'critical_gaps' => config('enterprise-billing-governance.critical_gaps', []),
            'step75' => config('enterprise-billing-governance.step75', []),
        ];
    }

    private function scores(): array
    {
        return [
            'enterprise_billing_readiness' => (int) config('enterprise-billing-governance.readiness_scores.enterprise_billing', 46),
            'seat_governance_readiness' => (int) config('enterprise-billing-governance.readiness_scores.seat_governance', 42),
            'subscription_governance_readiness' => (int) config('enterprise-billing-governance.readiness_scores.subscription_governance', 48),
            'ownership_governance_readiness' => (int) config('enterprise-billing-governance.readiness_scores.ownership_governance', 50),
            'cost_governance_readiness' => (int) config('enterprise-billing-governance.readiness_scores.cost_governance', 41),
            'financial_governance_readiness' => (int) config('enterprise-billing-governance.readiness_scores.financial_governance', 39),
        ];
    }
}
