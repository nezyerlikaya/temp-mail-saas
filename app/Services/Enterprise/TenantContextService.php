<?php

namespace App\Services\Enterprise;

use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use Illuminate\Http\Request;

final class TenantContextService extends Service
{
    public function __construct(
        private readonly OrganizationService $organizations,
    ) {}

    public function current(?Request $request = null, ?User $user = null): ?Organization
    {
        $request ??= request();
        $user ??= $request->user();

        if ($user === null || ! $request->hasSession()) {
            return null;
        }

        $organizationId = $request->session()->get($this->sessionKey());
        $organization = is_numeric($organizationId) ? Organization::query()->find((int) $organizationId) : null;

        if (! $organization instanceof Organization || ! $this->validForUser($organization, $user)) {
            $this->clear($request);

            return null;
        }

        return $organization;
    }

    public function set(Organization $organization, User $user, ?Request $request = null): bool
    {
        $request ??= request();

        if (! $request->hasSession() || ! $this->validForUser($organization, $user)) {
            return false;
        }

        $request->session()->put($this->sessionKey(), $organization->id);

        return true;
    }

    public function clear(?Request $request = null): void
    {
        $request ??= request();

        if ($request->hasSession()) {
            $request->session()->forget($this->sessionKey());
        }
    }

    public function validForUser(Organization $organization, User $user): bool
    {
        return $this->organizations->isActive($organization)
            && $this->organizations->isMember($organization, $user);
    }

    private function sessionKey(): string
    {
        return (string) config('enterprise.organizations.tenant_context_session_key', 'enterprise.organization_id');
    }
}
