<?php

namespace App\Services\Abuse;

use App\Enums\AbuseEventType;
use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use App\Models\AbuseEvent;
use App\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

final class AbuseLoggerService extends Service
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'body',
        'content',
        'cookie',
        'email',
        'headers',
        'html_body',
        'ip',
        'mailbox',
        'password',
        'payload',
        'raw',
        'session',
        'text_body',
        'token',
        'user_agent',
    ];

    public function __construct(
        private readonly AbuseSignalService $signals,
    ) {}

    public function log(
        AbuseEventType|string $type,
        AbuseSeverity|string $severity = AbuseSeverity::Low,
        AbuseStatus|string $status = AbuseStatus::Observed,
        ?string $reason = null,
        array $metadata = [],
        ?Request $request = null,
        int $riskScore = 0,
    ): ?AbuseEvent {
        if (! config('abuse.enabled', true)) {
            return null;
        }

        try {
            $signals = $this->signals->collect($request);

            return AbuseEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'event_type' => $type,
                'severity' => $severity,
                'status' => $status,
                ...$signals,
                'risk_score' => max(0, min(100, $riskScore)),
                'reason' => $reason !== null ? Str::limit($reason, 255, '') : null,
                'metadata' => $this->sanitizeMetadata($metadata),
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => $this->sensitiveKey((string) $key))
            ->map(function (mixed $value): mixed {
                if (is_array($value)) {
                    return $this->sanitizeMetadata($value);
                }

                if (is_string($value)) {
                    return Str::limit(strip_tags($value), 255, '');
                }

                return is_scalar($value) || $value === null ? $value : null;
            })
            ->all();
    }

    private function sensitiveKey(string $key): bool
    {
        return Arr::first(
            self::SENSITIVE_KEYS,
            fn (string $sensitive): bool => str_contains(Str::lower($key), $sensitive),
        ) !== null;
    }
}
