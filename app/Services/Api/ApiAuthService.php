<?php

namespace App\Services\Api;

use App\Models\ApiKey;
use App\Services\Service;
use Illuminate\Http\Request;

final class ApiAuthService extends Service
{
    public function __construct(
        private readonly ApiKeyService $keys,
    ) {}

    public function authenticate(Request $request): array
    {
        $rawKey = $this->resolveToken($request);
        $apiKey = $rawKey !== null ? $this->keys->verify($rawKey) : null;

        if (! $apiKey instanceof ApiKey) {
            return $this->result(false, 'invalid_api_key');
        }

        return $this->result(true, null, $apiKey);
    }

    public function resolveToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (filled($bearer)) {
            return $bearer;
        }

        $header = $request->headers->get('X-API-Key');

        return filled($header) ? $header : null;
    }

    private function result(bool $authenticated, ?string $reason = null, ?ApiKey $apiKey = null): array
    {
        return [
            'authenticated' => $authenticated,
            'reason' => $reason,
            'api_key' => $apiKey,
            'user' => $apiKey?->user,
        ];
    }
}
