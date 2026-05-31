<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $staff = auth('staff')->user();

        abort_if($staff === null || ! $staff->isActive() || ! $staff->hasPermission($permission), 403);

        return $next($request);
    }
}
