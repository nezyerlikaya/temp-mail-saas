<?php

namespace App\Services\Support;

use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use App\Enums\BillingInvoiceStatus;
use App\Enums\FeedbackType;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\SupportPriority;
use App\Enums\SupportStatus;
use App\Models\AbuseEvent;
use App\Models\BillingInvoice;
use App\Models\OperationsEvent;
use App\Models\SupportRequest;
use App\Models\UserFeedback;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class CustomerHealthService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $support = SupportRequest::query()
            ->whereNotIn('status', [SupportStatus::Resolved, SupportStatus::Closed])
            ->get();
        $supportScore = $support->sum(fn (SupportRequest $request): int => match ($request->priority) {
            SupportPriority::Critical => (int) config('support-intelligence.customer_health.critical_priority_weight', 4),
            SupportPriority::High => (int) config('support-intelligence.customer_health.high_priority_weight', 2),
            default => 0,
        });
        $feedbackIssues = UserFeedback::query()->where('type', FeedbackType::Issue)->count();
        $billingIssues = BillingInvoice::query()->whereIn('status', [BillingInvoiceStatus::Open, BillingInvoiceStatus::Uncollectible])->count();
        $abuseIssues = AbuseEvent::query()->whereIn('status', [AbuseStatus::Blocked, AbuseStatus::Escalated])->orWhereIn('severity', [AbuseSeverity::High, AbuseSeverity::Critical])->count();
        $operationalRisks = OperationsEvent::query()->whereIn('severity', [OperationSeverity::Warning, OperationSeverity::Critical])->count();
        $score = $supportScore
            + $feedbackIssues * (int) config('support-intelligence.customer_health.feedback_issue_weight', 1)
            + $billingIssues * (int) config('support-intelligence.customer_health.billing_issue_weight', 2)
            + $abuseIssues * (int) config('support-intelligence.customer_health.abuse_issue_weight', 2)
            + $operationalRisks * (int) config('support-intelligence.customer_health.operational_risk_weight', 1);
        $state = $score >= (int) config('support-intelligence.customer_health.risk_score', 5)
            ? 'risk'
            : ($score >= (int) config('support-intelligence.customer_health.attention_score', 2) ? 'attention' : 'healthy');

        $this->operations->log(
            OperationCategory::System,
            'customer_health_reviewed',
            $state === 'risk' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'support-intelligence',
            'Customer health review recorded.',
            [
                'state' => $state,
                'score' => $score,
                'support_count' => $support->count(),
                'feedback_issue_count' => $feedbackIssues,
                'billing_issue_count' => $billingIssues,
                'abuse_issue_count' => $abuseIssues,
                'operational_risk_count' => $operationalRisks,
            ],
        );

        return [
            'state' => $state,
            'score' => $score,
            'support_activity' => ['open_requests' => $support->count()],
            'feedback_activity' => ['issue_count' => $feedbackIssues],
            'billing_activity' => ['issue_count' => $billingIssues],
            'abuse_activity' => ['issue_count' => $abuseIssues],
            'operations_activity' => ['risk_count' => $operationalRisks],
        ];
    }
}
