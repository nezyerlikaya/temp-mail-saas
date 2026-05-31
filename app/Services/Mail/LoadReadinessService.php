<?php

namespace App\Services\Mail;

use App\Models\InboundMailIntake;
use App\Models\QueueMetric;
use App\Services\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LoadReadinessService extends Service
{
    public function report(): array
    {
        return [
            'queue' => $this->queueCapacity(),
            'database' => $this->databaseReadiness(),
            'cache' => $this->cacheReadiness(),
            'intake' => $this->intakeThroughput(),
            'cleanup' => $this->cleanupThroughput(),
            'providers' => $this->providerThroughput(),
            'admin' => $this->adminReadiness(),
        ];
    }

    private function queueCapacity(): array
    {
        $queue = (string) config('inbound.queue.name', 'inbound-mail');
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->where('queue', $queue)->count() : 0;
        $threshold = (int) config('mail-providers.throughput.queue_pending_warning', config('performance.thresholds.queue_pending_warning', 100));

        return [
            'queue' => $queue,
            'pending_jobs' => $pending,
            'threshold' => $threshold,
            'connection' => (string) config('queue.default', 'sync'),
            'status' => $pending >= $threshold ? 'warning' : 'ready',
        ];
    }

    private function databaseReadiness(): array
    {
        try {
            DB::select('select 1');

            return [
                'connection' => (string) config('database.default'),
                'status' => 'ready',
                'message' => 'Database connection is available.',
            ];
        } catch (Throwable) {
            return [
                'connection' => (string) config('database.default'),
                'status' => 'blocked',
                'message' => 'Database connection failed.',
            ];
        }
    }

    private function cacheReadiness(): array
    {
        $key = 'tempmail:load-readiness:cache-check';

        try {
            Cache::put($key, 'ok', now()->addSeconds(10));
            $ready = Cache::get($key) === 'ok';
            Cache::forget($key);

            return [
                'store' => (string) config('cache.default'),
                'status' => $ready ? 'ready' : 'warning',
                'message' => $ready ? 'Cache store accepts reads and writes.' : 'Cache store did not return the expected readiness value.',
            ];
        } catch (Throwable) {
            return [
                'store' => (string) config('cache.default'),
                'status' => 'warning',
                'message' => 'Cache store is not writable; runtime will fall back to uncached reads.',
            ];
        }
    }

    private function intakeThroughput(): array
    {
        $count = InboundMailIntake::query()->where('created_at', '>=', now()->subMinute())->count();
        $threshold = (int) config('mail-providers.throughput.intake_per_minute_warning', 120);

        return [
            'intakes_last_minute' => $count,
            'threshold' => $threshold,
            'status' => $count >= $threshold ? 'warning' : 'ready',
        ];
    }

    private function cleanupThroughput(): array
    {
        $chunk = (int) config('retention.cleanup_chunk_size', 100);
        $recommended = (int) config('mail-providers.throughput.cleanup_chunk_recommendation', 100);

        return [
            'chunk_size' => $chunk,
            'recommended_minimum' => $recommended,
            'status' => $chunk >= $recommended ? 'ready' : 'recommendation',
        ];
    }

    private function providerThroughput(): array
    {
        return InboundMailIntake::query()
            ->select('provider', DB::raw('count(*) as total'))
            ->groupBy('provider')
            ->pluck('total', 'provider')
            ->all();
    }

    private function adminReadiness(): array
    {
        $requiredRoutes = [
            'admin.index',
            'admin.operations',
            'admin.health',
            'admin.queue',
            'admin.domains',
            'admin.abuse',
            'admin.billing',
            'admin.audit',
        ];

        $missing = array_values(array_filter(
            $requiredRoutes,
            fn (string $route): bool => ! Route::has($route),
        ));

        return [
            'routes_checked' => count($requiredRoutes),
            'missing_routes' => $missing,
            'recent_queue_metrics' => Schema::hasTable('queue_metrics') ? QueueMetric::query()->count() : 0,
            'status' => $missing === [] ? 'ready' : 'warning',
        ];
    }
}
