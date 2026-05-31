<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\Services\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class IntegrationRegistryService extends Service
{
    public function register(array $data): Integration
    {
        $slug = Str::slug((string) ($data['slug'] ?? $data['name'] ?? ''));

        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => 'An integration slug or name is required.',
            ]);
        }

        $integration = Integration::query()->firstOrNew(['slug' => $slug]);

        $integration->fill([
            'uuid' => $integration->uuid ?: (string) Str::uuid(),
            'name' => (string) ($data['name'] ?? Str::headline($slug)),
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'status' => $data['status'] ?? IntegrationStatus::Inactive,
            'version' => $data['version'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ])->save();

        return $integration;
    }

    public function resolve(string $slug): ?Integration
    {
        return Integration::query()->where('slug', Str::slug($slug))->first();
    }

    public function ensureActive(string $slug): Integration
    {
        $integration = $this->resolve($slug);

        if (! $integration?->isActive()) {
            throw ValidationException::withMessages([
                'integration' => 'The requested integration is not active.',
            ]);
        }

        return $integration;
    }

    public function metadata(string $slug): array
    {
        return $this->resolve($slug)?->metadata ?? [];
    }

    public function active(): array
    {
        return Integration::query()
            ->where('status', IntegrationStatus::Active)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->all();
    }
}
