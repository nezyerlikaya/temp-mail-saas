<?php

namespace App\Http\Middleware;

use App\Services\System\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInstallerAccessible
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(InstallationService::class)->installerAccessible()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
