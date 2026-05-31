<?php

namespace App\Services\System;

use App\Services\Service;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class PerformanceCacheService extends Service
{
    public function healthSummary(Closure $resolver): array
    {
        return $this->remember('health_summary', $resolver);
    }

    public function readinessSummary(Closure $resolver): array
    {
        return $this->remember('readiness_summary', $resolver);
    }

    public function localizationProgress(Closure $resolver): array
    {
        return $this->remember('localization_progress', $resolver);
    }

    public function domainHealthSummary(Closure $resolver): array
    {
        return $this->remember('domain_health_summary', $resolver);
    }

    public function operationsDashboard(Closure $resolver): array
    {
        return $this->remember('operations_dashboard', $resolver);
    }

    public function forget(string $name): void
    {
        try {
            Cache::forget($this->key($name));
        } catch (Throwable) {
            // Cache failures should not affect request handling.
        }
    }

    public function flush(): void
    {
        foreach (array_keys((array) config('performance.cache.ttl', [])) as $name) {
            $this->forget((string) $name);
        }
    }

    public function key(string $name): string
    {
        $prefix = trim((string) config('performance.cache.prefix', 'tempmail:performance'), ':');

        return "{$prefix}:{$name}";
    }

    private function remember(string $name, Closure $resolver): array
    {
        if (! (bool) config('performance.cache.enabled', true)) {
            return $this->resolve($resolver);
        }

        $ttl = max(1, (int) config("performance.cache.ttl.{$name}", 60));

        try {
            return Cache::remember($this->key($name), now()->addSeconds($ttl), fn (): array => $this->resolve($resolver));
        } catch (Throwable) {
            return $this->resolve($resolver);
        }
    }

    private function resolve(Closure $resolver): array
    {
        $value = $resolver();

        return is_array($value) ? $value : [];
    }
}
