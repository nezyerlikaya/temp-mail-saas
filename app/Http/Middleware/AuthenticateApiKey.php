<?php

namespace App\Http\Middleware;

use App\Services\Api\ApiAuthService;
use App\Services\Api\ApiRateLimitService;
use App\Services\Api\ApiUsageLoggerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $result = app(ApiAuthService::class)->authenticate($request);

        if (! $result['authenticated']) {
            app(ApiUsageLoggerService::class)->log(null, $request, 401);

            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $apiKey = $result['api_key'];
        $user = $result['user'];
        $limits = app(ApiRateLimitService::class)->limitFor($user, '/'.ltrim($request->path(), '/'));

        if (! $limits['enabled']) {
            app(ApiUsageLoggerService::class)->log($apiKey, $request, 403);

            return response()->json(['message' => 'API access is not enabled for this account.'], 403);
        }

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_user', $user);
        $request->setUserResolver(fn () => $user);

        $response = $next($request);

        app(ApiUsageLoggerService::class)->log($apiKey, $request, $response->getStatusCode());

        return $response;
    }
}
