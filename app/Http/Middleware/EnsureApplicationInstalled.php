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

        if (! app(InstallationService::class)->installed()) {
            return redirect()->route('installer.index');
        }

        return $next($request);
    }
}
