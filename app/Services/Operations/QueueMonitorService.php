<?php

namespace App\Services\Operations;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Models\QueueMetric;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class QueueMonitorService extends Service
{
    public function collect(bool $store = true): array
    {
        $queues = config('operations.queue_names', ['default']);
        $metrics = [];

        foreach ($queues as $queue) {
            $metric = [
                'queue_name' => $queue,
                'pending_jobs' => $this->pendingJobs($queue),
                'failed_jobs' => $this->failedJobs($queue),
                'processed_jobs' => 0,
                'measured_at' => now(),
            ];

            if ($store) {
                QueueMetric::query()->create($metric);
            }

            $this->warnIfNeeded($metric);
            $metrics[] = [
                ...$metric,
                'measured_at' => $metric['measured_at']->toIso8601String(),
            ];
        }

        return $metrics;
    }

    public function configuration(): array
    {
        return [
            'connection' => config('queue.default'),
            'configured' => array_key_exists((string) config('queue.default'), config('queue.connections', [])),
        ];
    }

    private function pendingJobs(string $queue): int
    {
        return Schema::hasTable('jobs')
            ? DB::table('jobs')->where('queue', $queue)->count()
            : 0;
    }

    private function failedJobs(string $queue): int
    {
        return Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('queue', $queue)->count()
            : 0;
    }

    private function warnIfNeeded(array $metric): void
    {
        $pendingThreshold = (int) config('operations.thresholds.queue_pending_warning', 100);
        $failedThreshold = (int) config('operations.thresholds.queue_failed_warning', 1);

        if ($metric['pending_jobs'] >= $pendingThreshold || $metric['failed_jobs'] >= $failedThreshold) {
            app(OperationsLoggerService::class)->log(
                OperationCategory::Queue,
                'queue_threshold_exceeded',
                OperationSeverity::Warning,
                source: 'queue-monitor',
                message: 'Queue threshold exceeded.',
                metadata: [
                    'queue_name' => $metric['queue_name'],
                    'pending_jobs' => $metric['pending_jobs'],
                    'failed_jobs' => $metric['failed_jobs'],
                ],
            );
        }
    }
}
