<?php

namespace App\Services\Operations;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Services\Service;
use Illuminate\Support\Str;

final class IncidentService extends Service
{
    public function create(
        string $category,
        IncidentSeverity|string $severity,
        string $title,
        ?string $summary = null,
        array $metadata = [],
    ): Incident {
        return Incident::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => Str::lower(Str::limit($category, 64, '')),
            'severity' => $this->severity($severity)->value,
            'status' => IncidentStatus::Open->value,
            'title' => Str::limit($title, 255, ''),
            'summary' => $summary !== null ? Str::limit($summary, 1000, '') : null,
            'detected_at' => now(),
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);
    }

    public function acknowledge(Incident $incident): Incident
    {
        if ($incident->isResolved()) {
            return $incident;
        }

        $incident->forceFill([
            'status' => IncidentStatus::Acknowledged->value,
        ])->save();

        return $incident->refresh();
    }

    public function resolve(Incident $incident): Incident
    {
        $incident->forceFill([
            'status' => IncidentStatus::Resolved->value,
            'resolved_at' => $incident->resolved_at ?? now(),
        ])->save();

        return $incident->refresh();
    }

    private function severity(IncidentSeverity|string $severity): IncidentSeverity
    {
        return $severity instanceof IncidentSeverity
            ? $severity
            : (IncidentSeverity::tryFrom($severity) ?? IncidentSeverity::Medium);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->take((int) config('monitoring.incidents.metadata_limit', 20))
            ->reject(fn (mixed $value, string|int $key): bool => $this->sensitiveKey((string) $key))
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->sanitizeMetadata($value)
                : (is_scalar($value) || $value === null ? $value : null))
            ->all();
    }

    private function sensitiveKey(string $key): bool
    {
        $key = Str::lower($key);

        return str_contains($key, 'secret')
            || str_contains($key, 'token')
            || str_contains($key, 'password')
            || str_contains($key, 'payload')
            || str_contains($key, 'key');
    }
}
