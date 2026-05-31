<?php

namespace App\Services\Api;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class ApiKeyService extends Service
{
    public function create(User $user, string $name, ?Carbon $expiresAt = null, array $metadata = []): array
    {
        $rawKey = $this->generateRawKey();
        $prefix = $this->prefix($rawKey);
        $days = config('api.default_expiration_days');

        $apiKey = ApiKey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => $this->hash($rawKey),
            'status' => ApiKeyStatus::Active,
            'expires_at' => $expiresAt ?? (is_numeric($days) ? now()->addDays((int) $days) : null),
            'metadata' => $this->safeMetadata($metadata),
        ]);

        return [
            'api_key' => $apiKey,
            'plain_text_key' => $rawKey,
        ];
    }

    public function verify(string $rawKey): ?ApiKey
    {
        if (blank($rawKey)) {
            return null;
        }

        $apiKey = ApiKey::query()
            ->where('key_prefix', $this->prefix($rawKey))
            ->where('key_hash', $this->hash($rawKey))
            ->first();

        if (! $apiKey instanceof ApiKey || ! $apiKey->isActive()) {
            return null;
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        return $apiKey;
    }

    public function revoke(ApiKey $apiKey): bool
    {
        return $apiKey->forceFill([
            'status' => ApiKeyStatus::Revoked->value,
            'revoked_at' => now(),
        ])->save();
    }

    public function rotate(ApiKey $apiKey): array
    {
        $this->revoke($apiKey);

        return $this->create($apiKey->user, $apiKey->name, $apiKey->expires_at, $apiKey->metadata ?? []);
    }

    public function hash(string $rawKey): string
    {
        return hash_hmac('sha256', $rawKey, (string) config('app.key', 'local-api-key-salt'));
    }

    private function generateRawKey(): string
    {
        $prefix = (string) config('api.key_prefix', 'tm');
        $bytes = max(16, min(64, (int) config('api.key_bytes', 32)));

        return $prefix.'_'.Str::random($bytes);
    }

    private function prefix(string $rawKey): string
    {
        return Str::substr($rawKey, 0, 12);
    }

    private function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => str_contains(Str::lower((string) $key), 'secret')
                || str_contains(Str::lower((string) $key), 'token')
                || str_contains(Str::lower((string) $key), 'key'))
            ->all();
    }
}
