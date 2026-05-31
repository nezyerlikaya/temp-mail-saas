<?php

namespace App\Services\Api;

use App\Models\User;
use App\Services\Billing\FeatureGateService;
use App\Services\Service;

final class ApiRateLimitService extends Service
{
    public function __construct(
        private readonly FeatureGateService $features,
    ) {}

    public function limitFor(?User $user = null, ?string $endpoint = null): array
    {
        $enabled = (bool) $this->features->featureValue('api_enabled', $user, false);
        $perMinute = (int) $this->features->featureValue(
            'api_rate_limit_per_minute',
            $user,
            config('api.default_limits.per_minute', 30),
        );

        return [
            'enabled' => $enabled,
            'endpoint' => $endpoint,
            'per_minute' => max(1, $perMinute),
        ];
    }
}
