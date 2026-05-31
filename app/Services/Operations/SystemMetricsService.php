<?php

namespace App\Services\Operations;

use App\Models\AbuseEvent;
use App\Models\ApiUsageLog;
use App\Models\CleanupRun;
use App\Models\SystemHealthCheck;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class SystemMetricsService extends Service
{
    public function __construct(
        private readonly QueueMonitorService $queue,
        private readonly DomainHealthService $domains,
        private readonly FailedJobMonitorService $failedJobs,
    ) {}

    public function collect(bool $store = true): array
    {
        return [
            'app' => [
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
            ],
            'storage' => [
                'storage_writable' => is_writable(storage_path()),
                'logs_writable' => is_writable(storage_path('logs')),
            ],
            'health' => [
                'records' => Schema::hasTable('system_health_checks') ? SystemHealthCheck::query()->count() : 0,
            ],
            'cleanup' => [
                'runs' => Schema::hasTable('cleanup_runs') ? CleanupRun::query()->count() : 0,
            ],
            'abuse' => [
                'events' => Schema::hasTable('abuse_events') ? AbuseEvent::query()->count() : 0,
            ],
            'api' => [
                'usage_logs' => Schema::hasTable('api_usage_logs') ? ApiUsageLog::query()->count() : 0,
            ],
            'queue' => $this->queue->collect($store),
            'domains' => $this->domains->evaluate($store),
            'failed_jobs' => $this->failedJobs->summarize($store),
        ];
    }
}
