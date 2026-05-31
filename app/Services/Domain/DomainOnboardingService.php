<?php

namespace App\Services\Domain;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Models\DomainOnboardingAudit;
use App\Models\Organization;
use App\Models\User;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DomainOnboardingService extends Service
{
    public function __construct(
        private readonly DomainDnsReadinessService $dns,
        private readonly DomainSafetyCheckService $safety,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function start(Domain $domain, ?string $reason = null): DomainOnboardingAudit
    {
        return $this->transition($domain, DomainOnboardingState::Validating, $reason, 'domain_onboarding_started');
    }

    public function validate(Domain $domain, ?User $user = null, ?Organization $organization = null): array
    {
        if ($domain->onboarding_state === DomainOnboardingState::Draft) {
            $this->start($domain, 'Domain onboarding validation started.');
            $domain->refresh();
        }

        $review = $this->safety->report($domain, $user, $organization);
        $this->record('domain_onboarding_validated', $domain);

        if ($review['blockers'] !== []) {
            $this->audit($domain, $domain->onboarding_state, 'Domain onboarding validation blocked.', [
                'blocker_count' => count($review['blockers']),
                'warning_count' => count($review['warnings']),
            ]);
            $this->record('domain_onboarding_blocked', $domain, OperationSeverity::Warning);

            return $review;
        }

        $this->transition($domain, DomainOnboardingState::Ready, 'Domain onboarding validation passed.', 'domain_onboarding_ready');

        return $review;
    }

    public function activate(Domain $domain, ?User $user = null, ?Organization $organization = null): array
    {
        $review = $this->activationReview($domain, $user, $organization);

        if ($review['blockers'] !== []) {
            $this->audit($domain, $domain->onboarding_state, 'Domain activation blocked.', [
                'blocker_count' => count($review['blockers']),
                'warning_count' => count($review['warnings']),
            ]);
            $this->record('domain_onboarding_blocked', $domain, OperationSeverity::Warning);

            return $review;
        }

        $domain->forceFill(['status' => DomainStatus::Active])->save();
        $this->transition($domain, DomainOnboardingState::Active, 'Domain activation passed.', 'domain_onboarding_activated');

        return $review;
    }

    public function suspend(Domain $domain, ?string $reason = null): DomainOnboardingAudit
    {
        $domain->forceFill(['status' => DomainStatus::Suspended])->save();

        return $this->transition($domain, DomainOnboardingState::Suspended, $reason, 'domain_onboarding_suspended');
    }

    public function readiness(Domain $domain, ?User $user = null, ?Organization $organization = null): array
    {
        return [
            'state' => $domain->onboarding_state->value,
            'dns' => $this->dns->review($domain),
            ...$this->safety->report($domain, $user, $organization),
        ];
    }

    public function activationReview(Domain $domain, ?User $user = null, ?Organization $organization = null): array
    {
        return $this->safety->activationReview($domain, $user, $organization);
    }

    public function recommendations(Domain $domain): array
    {
        return $this->safety->report($domain)['recommendations'];
    }

    private function transition(
        Domain $domain,
        DomainOnboardingState $newState,
        ?string $reason,
        string $eventType,
    ): DomainOnboardingAudit {
        if (! in_array($newState->value, (array) config('domains.onboarding.states', []), true)) {
            throw ValidationException::withMessages(['state' => 'Domain onboarding state is not supported.']);
        }

        $previous = $domain->onboarding_state;
        $domain->forceFill(['onboarding_state' => $newState])->save();
        $audit = $this->audit($domain, $previous, $reason);
        $this->record($eventType, $domain);

        return $audit;
    }

    private function audit(
        Domain $domain,
        ?DomainOnboardingState $previous,
        ?string $reason,
        array $metadata = [],
    ): DomainOnboardingAudit {
        return DomainOnboardingAudit::query()->create([
            'domain_id' => $domain->id,
            'domain_name' => $domain->domain,
            'previous_state' => $previous,
            'new_state' => $domain->onboarding_state,
            'reason' => $reason !== null ? Str::limit($reason, 255, '') : null,
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);
    }

    private function record(string $eventType, Domain $domain, OperationSeverity $severity = OperationSeverity::Info): void
    {
        $this->operations->log(
            OperationCategory::Domain,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'domain-onboarding',
            'Domain onboarding readiness event recorded.',
            [
                'domain_id' => $domain->id,
                'state' => $domain->onboarding_state->value,
            ],
        );
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => collect([
                'credential',
                'dkim',
                'dmarc',
                'dns',
                'mx',
                'password',
                'record',
                'secret',
                'spf',
                'token',
            ])->contains(fn (string $sensitive): bool => str_contains(Str::lower((string) $key), $sensitive)))
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->sanitizeMetadata($value)
                : (is_scalar($value) || $value === null ? $value : null))
            ->all();
    }
}
