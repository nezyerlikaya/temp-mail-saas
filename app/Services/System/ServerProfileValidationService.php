<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\File;

final class ServerProfileValidationService extends Service
{
    public function __construct(private readonly ServerReadinessService $server) {}

    public function report(): array
    {
        $base = $this->server->report();
        $checks = [
            ...$base['checks'],
            $this->writableDirectory('storage_permissions', storage_path()),
            $this->writableDirectory('cache_permissions', storage_path('framework/cache')),
            $this->writableDirectory('bootstrap_cache_permissions', base_path('bootstrap/cache')),
            $this->queueDriver(),
            $this->scheduler(),
        ];

        return $this->summarize($checks);
    }

    private function writableDirectory(string $name, string $path): array
    {
        $ok = File::isDirectory($path) && File::isWritable($path);

        return $this->check($name, $ok, $ok ? 'Required directory is writable.' : 'Required directory is not writable.', 'blocker');
    }

    private function queueDriver(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $required = (bool) config('production.deployment_readiness.queue.require_worker_driver', true);
        $ok = ! $required || $driver !== 'sync';

        return $this->check('queue_driver_readiness', $ok, $ok ? 'Queue driver is suitable for deployed workers.' : 'Queue driver must support deployed workers.', $required ? 'blocker' : 'warning', [
            'configured' => filled($driver),
            'worker_driver' => $driver !== 'sync',
        ]);
    }

    private function scheduler(): array
    {
        $ready = (bool) config('production.deployment_readiness.scheduler.scheduler_ready', true);

        return $this->check('scheduler_deployment_readiness', $ready, $ready ? 'Scheduler deployment readiness is documented.' : 'Scheduler deployment readiness needs review.', 'warning');
    }

    private function check(string $name, bool $passed, string $message, string $classification, array $metadata = []): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $message,
            'metadata' => $metadata,
        ];
    }

    private function summarize(array $checks): array
    {
        return [
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }
}
