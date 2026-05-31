<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = auth('staff')->user();

        abort_if($staff === null || ! $staff->isActive(), 403);

        return $next($request);
    }
}
