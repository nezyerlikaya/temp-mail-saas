<?php

namespace App\Services\System;

use App\Enums\SystemHealthStatus;
use App\Models\SystemHealthCheck;
use App\Services\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class SystemHealthService extends Service
{
    public function __construct(
        private readonly InstallationService $installation,
    ) {}

    public function run(bool $store = false): array
    {
        $checks = [
            $this->database(),
            $this->cache(),
            $this->storage(),
            $this->queue(),
            $this->scheduler(),
            $this->installer(),
            $this->appKey(),
        ];

        if ($store && config('production.health.logging_enabled', true)) {
            foreach ($checks as $check) {
                $this->store($check);
            }
        }

        return [
            'status' => $this->overallStatus($checks)->value,
            'checks' => $checks,
        ];
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->check('database', SystemHealthStatus::Healthy, 'Database connection is available.');
        } catch (Throwable) {
            return $this->check('database', SystemHealthStatus::Critical, 'Database connection could not be verified.');
        }
    }

    private function cache(): array
    {
        try {
            Cache::store(config('cache.default'))->get('system-health');

            return $this->check('cache', SystemHealthStatus::Healthy, 'Cache store is available.');
        } catch (Throwable) {
            return $this->check('cache', SystemHealthStatus::Warning, 'Cache store could not be verified.');
        }
    }

    private function storage(): array
    {
        $paths = [storage_path(), storage_path('framework'), storage_path('logs')];
        $writable = collect($paths)->every(fn (string $path): bool => is_dir($path) && is_writable($path));

        return $this->check(
            'storage',
            $writable ? SystemHealthStatus::Healthy : SystemHealthStatus::Critical,
            $writable ? 'Required storage paths are writable.' : 'One or more required storage paths are not writable.',
            ['paths_checked' => count($paths)],
        );
    }

    private function queue(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $configured = array_key_exists($connection, config('queue.connections', []));

        return $this->check(
            'queue',
            $configured ? SystemHealthStatus::Healthy : SystemHealthStatus::Warning,
            $configured ? 'Queue connection is configured.' : 'Queue connection is not configured.',
            ['connection' => $connection],
        );
    }

    private function scheduler(): array
    {
        return $this->check(
            'scheduler',
            config('production.health.schedule_enabled', false) ? SystemHealthStatus::Healthy : SystemHealthStatus::Warning,
            config('production.health.schedule_enabled', false)
                ? 'Scheduled health checks are enabled.'
                : 'Scheduled health checks are disabled.',
        );
    }

    private function installer(): array
    {
        $status = $this->installation->status();

        return $this->check(
            'installer',
            $status['healthy'] ? SystemHealthStatus::Healthy : SystemHealthStatus::Warning,
            $status['healthy'] ? 'Installer is locked for healthy installation.' : 'Installer recovery may be accessible.',
            ['locked' => (bool) $status['lock']['locked']],
        );
    }

    private function appKey(): array
    {
        return $this->check(
            'app_key',
            filled((string) config('app.key')) ? SystemHealthStatus::Healthy : SystemHealthStatus::Critical,
            filled((string) config('app.key')) ? 'Application key is configured.' : 'Application key is missing.',
        );
    }

    private function check(string $name, SystemHealthStatus $status, string $message, array $metadata = []): array
    {
        return [
            'check_name' => $name,
            'status' => $status->value,
            'message' => $message,
            'metadata' => $metadata,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function store(array $check): void
    {
        SystemHealthCheck::query()->create([
            'uuid' => (string) Str::uuid(),
            ...$check,
            'checked_at' => now(),
        ]);
    }

    private function overallStatus(array $checks): SystemHealthStatus
    {
        $statuses = array_column($checks, 'status');

        return match (true) {
            in_array(SystemHealthStatus::Critical->value, $statuses, true) => SystemHealthStatus::Critical,
            in_array(SystemHealthStatus::Warning->value, $statuses, true) => SystemHealthStatus::Warning,
            default => SystemHealthStatus::Healthy,
        };
    }
}
