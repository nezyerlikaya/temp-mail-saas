<?php

namespace App\Http\Middleware;

use App\Services\System\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApplicationInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        $installation = app(InstallationService::class)->status();

        if ($installation['healthy'] !== true) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('webhooks/*') || $request->is('billing/*')) {
                return response()->json([
                    'ok' => false,
                    'status' => 'installer_required',
                    'message' => 'Application installation must be completed before this endpoint is available.',
                ], 423);
            }

            return redirect()->route('installer.index');
        }

        return $next($request);
    }
}
