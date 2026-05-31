<?php

namespace App\Services\Api;

use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use App\Services\Service;
use Illuminate\Http\Request;
use Throwable;

final class ApiUsageLoggerService extends Service
{
    public function log(?ApiKey $apiKey, Request $request, int $responseStatus): ?ApiUsageLog
    {
        if (! config('api.usage_logging_enabled', true)) {
            return null;
        }

        try {
            return ApiUsageLog::query()->create([
                'api_key_id' => $apiKey?->id,
                'endpoint' => '/'.ltrim($request->path(), '/'),
                'method' => $request->method(),
                'response_status' => $responseStatus,
                'request_count' => 1,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
