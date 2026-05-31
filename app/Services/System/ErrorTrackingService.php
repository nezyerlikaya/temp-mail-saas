<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ErrorTrackingService extends Service
{
    public function report(Throwable $exception, array $context = []): array
    {
        if (! config('production.error_tracking.enabled', true)) {
            return ['reported' => false, 'provider' => 'disabled'];
        }

        $provider = (string) config('production.error_tracking.provider', 'log');
        Log::error('Application error reported.', [
            'exception' => class_basename($exception),
            'message' => str($exception->getMessage())->limit(200)->toString(),
            'context' => $this->sanitizeContext($context),
        ]);

        return ['reported' => true, 'provider' => $provider];
    }

    public function sanitizeContext(array $context): array
    {
        return collect($context)
            ->reject(fn (mixed $value, string|int $key): bool => in_array(strtolower((string) $key), [
                'password',
                'token',
                'secret',
                'api_key',
                'payload',
                'body',
                'email_body',
            ], true))
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->sanitizeContext($value) : (is_scalar($value) || $value === null ? $value : null))
            ->all();
    }
}
