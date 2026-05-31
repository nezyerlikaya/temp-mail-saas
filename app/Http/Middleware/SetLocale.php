<?php

namespace App\Http\Middleware;

use App\Services\System\LocaleService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $locale = app(LocaleService::class)->determineLocale($request);
            app(LocaleService::class)->setApplicationLocale($locale);
            Carbon::setLocale($locale);
        } catch (Throwable) {
            //
        }

        return $next($request);
    }
}
