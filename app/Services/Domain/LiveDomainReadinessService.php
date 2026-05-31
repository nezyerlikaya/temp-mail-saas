<?php

namespace App\Services\Domain;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Models\DomainOnboardingAudit;
use App\Services\Mail\PublicMailboxService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class LiveDomainReadinessService extends Service
{
    public function __construct(
        private readonly DomainOnboardingService $onboarding,
        private readonly DomainActivationReviewService $activation,
        private readonly DomainRollbackReadinessService $rollback,
        private readonly DomainPoolService $pool,
        private readonly PublicMailboxService $mailboxes,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(?string $domainName = null): array
    {
        $this->record('live_domain_review_started');

        $domains = $this->domains($domainName);
        $sections = $domains
            ->mapWithKeys(fn (Domain $domain): array => [(string) $domain->id => $this->domainReview($domain)])
            ->all();
        $rollback = $this->rollback->report($domains->first());
        $blockers = [
            ...($domains->isEmpty() ? [$this->issue('inventory', 'active_domain_available', 'No active onboarded domain is available.')] : []),
            ...$this->issues($sections, 'blockers'),
            ...collect($rollback['blockers'])->map(fn (array $issue): array => ['category' => 'rollback', ...$issue])->all(),
        ];
        $warnings = [
            ...$this->issues($sections, 'warnings'),
            ...collect($rollback['warnings'])->map(fn (array $issue): array => ['category' => 'rollback', ...$issue])->all(),
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->auditReviews($domains, $sections, $status);
        $this->record('live_domain_review_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'domain_count' => $domains->count(),
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
        ]);

        return [
            'status' => $status,
            'domain_count' => $domains->count(),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $this->recommendations($blockers, $warnings),
            'rollback' => $rollback,
            'sections' => $sections,
        ];
    }

    private function domainReview(Domain $domain): array
    {
        $onboarding = $this->onboarding->readiness($domain);
        $activation = $this->activation->review($domain);
        $eligible = $this->pool->eligibleDomains()->contains(fn (Domain $eligible): bool => $eligible->is($domain));
        $allowed = $this->pool->allowedDomainNames();
        $normalizes = $this->mailboxes->normalize('review@'.$domain->domain) !== null;
        $checks = [
            $this->check('onboarding_readiness', $onboarding['blockers'] === [], 'Domain onboarding readiness has no blockers.', 'Domain onboarding readiness has blockers.', 'blocker'),
            $this->check('activation_readiness', $activation['blockers'] === [], 'DNS activation readiness has no blockers.', 'DNS activation readiness has blockers.', 'blocker'),
            $this->check('provider_compatibility', collect($onboarding['checks'])->where('name', 'provider_compatibility')->every(fn (array $check): bool => $check['passed']), 'Provider compatibility is confirmed.', 'Provider compatibility needs review.', 'blocker'),
            $this->check('domain_pool_selection', $eligible, 'Domain pool can select the active onboarded domain.', 'Domain pool cannot select the domain.', 'blocker'),
            $this->check('suspended_domain_exclusion', ! Domain::query()->where('status', DomainStatus::Suspended)->where('onboarding_state', DomainOnboardingState::Suspended)->get()->contains(fn (Domain $suspended): bool => in_array($suspended->domain, $allowed, true)), 'Suspended domains are excluded from mailbox selection.', 'Suspended domains must be excluded from mailbox selection.', 'blocker'),
            $this->check('mailbox_generation_readiness', in_array($domain->domain, $allowed, true) && $normalizes, 'Mailbox generation and normalization are ready.', 'Mailbox generation readiness needs review.', 'blocker'),
            $this->check('observability_readiness', (bool) config('domains.live_activation.observability.operations_events_required', true) && (bool) config('domains.live_activation.observability.domain_audits_required', true), 'Domain activation observability is configured.', 'Domain activation observability needs review.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function domains(?string $domainName): mixed
    {
        return Domain::query()
            ->where('status', DomainStatus::Active)
            ->where('onboarding_state', DomainOnboardingState::Active)
            ->when($domainName !== null, fn ($query) => $query->where('domain', $domainName))
            ->get();
    }

    private function auditReviews(mixed $domains, array $sections, string $status): void
    {
        foreach ($domains as $domain) {
            $section = $sections[(string) $domain->id];
            $activationRecommendation = $status === 'blocked'
                ? 'Hold live domain activation until blockers are cleared.'
                : ($status === 'warning' ? 'Proceed only after warnings are acknowledged.' : 'Domain is ready for controlled live activation.');
            $suspensionRecommendation = $section['blockers'] === []
                ? 'Suspension path remains available if domain activation degrades.'
                : 'Prepare suspension before attempting live activation.';

            foreach ([
                'readiness_review' => 'Live domain readiness review recorded.',
                'activation_recommendation' => $activationRecommendation,
                'suspension_recommendation' => $suspensionRecommendation,
            ] as $reviewType => $recommendation) {
                DomainOnboardingAudit::query()->create([
                    'domain_id' => $domain->id,
                    'domain_name' => $domain->domain,
                    'previous_state' => $domain->onboarding_state,
                    'new_state' => $domain->onboarding_state,
                    'reason' => 'Live domain readiness review.',
                    'review_type' => $reviewType,
                    'recommendation' => $recommendation,
                    'metadata' => [
                        'status' => $section['blockers'] === [] ? ($section['warnings'] === [] ? 'ready' : 'warning') : 'blocked',
                        'blocker_count' => count($section['blockers']),
                        'warning_count' => count($section['warnings']),
                    ],
                ]);
            }
        }
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function summarize(array $checks): array
    {
        return [
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function issues(array $sections, string $type): array
    {
        return collect($sections)
            ->flatMap(fn (array $section): array => collect($section[$type])
                ->map(fn (array $issue): array => ['category' => 'domain', ...$issue])
                ->all())
            ->values()
            ->all();
    }

    private function issue(string $category, string $name, string $message): array
    {
        return compact('category', 'name', 'message');
    }

    private function recommendations(array $blockers, array $warnings): array
    {
        return collect([...$blockers, ...$warnings])
            ->pluck('message')
            ->unique()
            ->values()
            ->all();
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::Domain,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'live-domain-readiness',
            'Live domain readiness event recorded.',
            $metadata,
        );
    }
}
