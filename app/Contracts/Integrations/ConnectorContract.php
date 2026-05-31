<?php

namespace App\Contracts\Integrations;

use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserIntegration;

interface ConnectorContract
{
    public function connectorName(): string;

    public function connect(Integration $integration, ?User $user = null, ?Organization $organization = null, array $configuration = []): UserIntegration;

    public function disconnect(UserIntegration $userIntegration): UserIntegration;

    public function validateConfiguration(array $configuration): bool;
}
