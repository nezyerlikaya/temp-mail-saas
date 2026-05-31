<?php

namespace App\Services\Operations;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\OperationsEvent;
use App\Services\Service;
use Illuminate\Support\Str;

final class OperationsLoggerService extends Service
{
    private const SENSITIVE_KEYS = [
        'api_key',
        'body',
        'content',
        'email_body',
        'exception',
        'password',
        'payload',
        'raw',
        'secret',
        'token',
    ];

    public function log(
        OperationCategory|string $category,
        string $eventType,
        OperationSeverity|string $severity = OperationSeverity::Info,
        OperationStatus|string $status = OperationStatus::Detected,
        ?string $source = null,
        ?string $message = null,
        array $metadata = [],
    ): OperationsEvent {
        return OperationsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => $category,
            'event_type' => $eventType,
            'severity' => $severity,
            'status' => $status,
            'source' => $source,
            'message' => $message !== null ? Str::limit($message, 255, '') : null,
            'metadata' => $this->sanitizeMetadata($metadata),
            'occurred_at' => now(),
        ]);
    }

    public function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => $this->sensitiveKey((string) $key))
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->sanitizeMetadata($value)
                : (is_scalar($value) || $value === null ? $value : null))
            ->all();
    }

    private function sensitiveKey(string $key): bool
    {
        $key = Str::lower($key);

        return collect(self::SENSITIVE_KEYS)->contains(fn (string $sensitive): bool => str_contains($key, $sensitive));
    }
}
