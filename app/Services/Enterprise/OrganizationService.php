<?php

namespace App\Services\Enterprise;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Str;

final class OrganizationService extends Service
{
    public function create(array $data, ?User $owner = null): Organization
    {
        $organization = Organization::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
            'status' => $data['status'] ?? OrganizationStatus::Active,
            'owner_user_id' => $owner?->id,
            'plan_id' => $data['plan_id'] ?? null,
            'metadata' => $this->sanitizeMetadata($data['metadata'] ?? []),
        ]);

        if ($owner !== null) {
            $this->addMember($organization, $owner, OrganizationMemberRole::Owner, null);
        }

        return $organization->refresh();
    }

    public function addMember(
        Organization $organization,
        User $user,
        OrganizationMemberRole|string|null $role = null,
        ?User $invitedBy = null,
    ): OrganizationMember {
        $role = $role instanceof OrganizationMemberRole
            ? $role
            : (OrganizationMemberRole::tryFrom((string) $role) ?? OrganizationMemberRole::from(
                (string) config('enterprise.organizations.default_role', OrganizationMemberRole::Member->value),
            ));

        return OrganizationMember::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'status' => OrganizationMemberStatus::Active,
                'invited_by_user_id' => $invitedBy?->id,
                'joined_at' => now(),
            ],
        );
    }

    public function removeMember(Organization $organization, User $user): bool
    {
        $member = OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        return $member?->forceFill([
            'status' => OrganizationMemberStatus::Removed->value,
        ])->save() ?? false;
    }

    public function isMember(Organization $organization, User $user): bool
    {
        return OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', OrganizationMemberStatus::Active->value)
            ->exists();
    }

    public function isActive(Organization $organization): bool
    {
        return $organization->status === OrganizationStatus::Active;
    }

    public function assignPlan(Organization $organization, Plan $plan): Organization
    {
        $organization->forceFill(['plan_id' => $plan->id])->save();

        return $organization->refresh();
    }

    public function normalizeSlug(string $value): string
    {
        return Str::slug($value) ?: Str::random(8);
    }

    private function uniqueSlug(string $value): string
    {
        $base = $this->normalizeSlug($value);
        $slug = $base;
        $count = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => str_contains(Str::lower((string) $key), 'secret')
                || str_contains(Str::lower((string) $key), 'token')
                || str_contains(Str::lower((string) $key), 'password'))
            ->all();
    }
}
