<?php

namespace App\Services\Integrations\Connectors;

use App\Contracts\Integrations\ConnectorContract;
use App\Enums\UserIntegrationStatus;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserIntegration;
use Illuminate\Support\Arr;

final class LocalConnector implements ConnectorContract
{
    public function connectorName(): string
    {
        return 'local';
    }

    public function connect(
        Integration $integration,
        ?User $user = null,
        ?Organization $organization = null,
        array $configuration = [],
    ): UserIntegration {
        $this->validateConfiguration($configuration);

        return UserIntegration::query()->updateOrCreate(
            [
                'integration_id' => $integration->id,
                'user_id' => $user?->id,
                'organization_id' => $organization?->id,
            ],
            [
                'status' => UserIntegrationStatus::Connected,
                'configuration' => Arr::except($configuration, ['secret', 'token', 'password']),
                'connected_at' => now(),
                'disconnected_at' => null,
            ],
        );
    }

    public function disconnect(UserIntegration $userIntegration): UserIntegration
    {
        $userIntegration->update([
            'status' => UserIntegrationStatus::Disconnected,
            'disconnected_at' => now(),
        ]);

        return $userIntegration->fresh();
    }

    public function validateConfiguration(array $configuration): bool
    {
        foreach (array_keys($configuration) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
