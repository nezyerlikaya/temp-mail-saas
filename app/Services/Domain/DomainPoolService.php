<?php

namespace App\Services\Domain;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\DomainAssignment;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\FeatureGateService;
use App\Services\Enterprise\TenantContextService;
use App\Services\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class DomainPoolService extends Service
{
    public function __construct(
        private readonly FeatureGateService $features,
        private readonly TenantContextService $tenantContext,
    ) {}

    public function eligibleDomains(?User $user = null, ?Organization $organization = null): Collection
    {
        if (! Schema::hasTable('domains')) {
            return collect();
        }

        $organization ??= $this->tenantContext->current(user: $user);
        $tiers = $this->eligibleTiers($user, $organization);

        return Domain::query()
            ->where('status', DomainStatus::Active)
            ->whereIn('tier', $tiers)
            ->where('health_score', '>=', 1)
            ->tap(fn (Builder $query) => $this->applyOrganizationCompatibility($query, $organization))
            ->orderBy('priority')
            ->orderByDesc('health_score')
            ->get();
    }

    public function selectDomain(?User $user = null, ?Organization $organization = null): string
    {
        $domains = $this->eligibleDomains($user, $organization);

        if ($domains->isEmpty()) {
            return $this->fallbackDomain($user);
        }

        $strategy = (string) config('domains-pool.default_strategy', DomainAssignmentStrategy::HealthBased->value);

        $domain = match ($strategy) {
            DomainAssignmentStrategy::Random->value => $domains->random(),
            DomainAssignmentStrategy::Priority->value => $domains->sortBy('priority')->first(),
            DomainAssignmentStrategy::Weighted->value => $this->weightedDomain($domains),
            default => $domains->sortByDesc('health_score')->sortBy('priority')->first(),
        };

        return $domain->domain;
    }

    public function selectForMailbox(string $mailboxAddress, ?User $user = null, ?Organization $organization = null): string
    {
        $domainName = $this->selectDomain($user, $organization);
        $domain = Schema::hasTable('domains')
            ? Domain::query()->where('domain', $domainName)->first()
            : null;

        if ($domain instanceof Domain && config('domains-pool.assignment.record_history', true)) {
            $this->recordAssignment($domain, $mailboxAddress, $user, $organization);
        }

        return $domainName;
    }

    public function recordMailboxAssignment(
        string $domainName,
        string $mailboxAddress,
        ?User $user = null,
        ?Organization $organization = null,
    ): void {
        if (! Schema::hasTable('domains') || ! config('domains-pool.assignment.record_history', true)) {
            return;
        }

        $domain = Domain::query()->where('domain', $domainName)->first();

        if ($domain instanceof Domain) {
            $this->recordAssignment($domain, $mailboxAddress, $user, $organization);
        }
    }

    public function allowedDomainNames(?User $user = null, ?Organization $organization = null): array
    {
        $eligible = $this->eligibleDomains($user, $organization)->pluck('domain')->all();

        return $eligible ?: $this->fallbackDomains($user);
    }

    public function recordAssignment(
        Domain $domain,
        ?string $mailboxAddress = null,
        ?User $user = null,
        ?Organization $organization = null,
        array $metadata = [],
    ): DomainAssignment {
        return DomainAssignment::query()->create([
            'domain_id' => $domain->id,
            'mailbox_address' => $mailboxAddress,
            'user_id' => $user?->id,
            'organization_id' => $organization?->id,
            'assigned_at' => now(),
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);
    }

    private function eligibleTiers(?User $user, ?Organization $organization): array
    {
        $plan = $this->features->currentPlan($user, $organization);
        $tiers = $this->features->featureValue('domain_tiers', $user, null, $organization);

        if (! is_array($tiers)) {
            $tiers = config("domains-pool.tier_mapping.{$plan}", ['free']);
        }

        return array_values(array_unique(array_map(
            fn (mixed $tier): string => Str::lower((string) $tier),
            $tiers,
        )));
    }

    private function fallbackDomain(?User $user = null): string
    {
        $domains = $this->fallbackDomains($user);

        return $domains[array_rand($domains)];
    }

    private function fallbackDomains(?User $user = null): array
    {
        $configured = config('domains.public_mailbox.allowed_domains', []);
        $configured = is_array($configured) ? $configured : [];
        $fallback = config('domains-pool.fallback_domains', []);
        $fallback = is_array($fallback) ? $fallback : [];
        $domains = array_values(array_unique(array_filter(array_map(
            fn (mixed $domain): string => Str::lower(trim((string) $domain)),
            [...$configured, ...$fallback, config('domains.public_mailbox.default_domain', 'example.test')],
        ))));
        $planDomains = $this->features->featureValue('allowed_domains', $user, $domains);
        $planDomains = is_array($planDomains) ? $planDomains : $domains;
        $allowed = array_values(array_intersect($domains, $planDomains));

        return $allowed ?: $domains ?: ['example.test'];
    }

    private function weightedDomain(Collection $domains): Domain
    {
        return $domains
            ->sortByDesc(fn (Domain $domain): int => max(1, $domain->health_score) + max(1, 200 - $domain->priority))
            ->first();
    }

    private function applyOrganizationCompatibility(Builder $query, ?Organization $organization): void
    {
        if ($organization === null) {
            return;
        }

        $query->whereIn('tier', config('domains-pool.tier_mapping.enterprise', ['free', 'member', 'premium', 'enterprise']));
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => str_contains(Str::lower((string) $key), 'secret')
                || str_contains(Str::lower((string) $key), 'token')
                || str_contains(Str::lower((string) $key), 'password')
                || str_contains(Str::lower((string) $key), 'payload'))
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->sanitizeMetadata($value) : $value)
            ->all();
    }
}
