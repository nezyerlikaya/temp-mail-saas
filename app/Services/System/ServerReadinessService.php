<?php

namespace App\Services\System;

use App\Services\Service;

final class ServerReadinessService extends Service
{
    public function report(): array
    {
        $checks = [
            $this->phpVersion(),
            $this->phpExtensions(),
            $this->writablePaths(),
            $this->schedulerReadiness(),
            $this->queueWorkerReadiness(),
        ];

        return [
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function phpVersion(): array
    {
        $minimum = (string) config('production.server_readiness.minimum_php_version', '8.2.0');
        $ok = version_compare(PHP_VERSION, $minimum, '>=');

        return $this->check('php_version', $ok, $ok ? 'PHP version is compatible.' : 'PHP version is below the configured minimum.', 'blocker', [
            'current' => PHP_VERSION,
            'minimum' => $minimum,
        ]);
    }

    private function phpExtensions(): array
    {
        $required = config('production.server_readiness.required_extensions', []);
        $required = is_array($required) ? $required : [];
        $missing = array_values(array_filter($required, fn (string $extension): bool => ! extension_loaded($extension)));

        return $this->check('php_extensions', $missing === [], $missing === [] ? 'Required PHP extensions are loaded.' : 'One or more required PHP extensions are missing.', 'blocker', [
            'missing' => $missing,
            'required_count' => count($required),
        ]);
    }

    private function writablePaths(): array
    {
        $paths = config('production.deployment.required_writable_paths', []);
        $paths = is_array($paths) ? $paths : [];
        $unwritable = array_values(array_filter($paths, fn (string $path): bool => ! is_dir($path) || ! is_writable($path)));

        return $this->check('writable_paths', $unwritable === [], $unwritable === [] ? 'Required writable paths are available.' : 'One or more writable paths need attention.', 'blocker', [
            'paths_checked' => count($paths),
            'unwritable_count' => count($unwritable),
        ]);
    }

    private function schedulerReadiness(): array
    {
        $required = (bool) config('production.server_readiness.scheduler_required', false);
        $enabled = (bool) config('production.health.schedule_enabled', false) || (bool) config('operations.metrics.schedule_enabled', false);
        $ok = ! $required || $enabled;

        return $this->check('scheduler_readiness', $ok, $ok ? 'Scheduler readiness is acceptable.' : 'Scheduler is required but not enabled in config.', $required ? 'blocker' : 'warning', [
            'required' => $required,
            'configured' => $enabled,
        ]);
    }

    private function queueWorkerReadiness(): array
    {
        $required = (bool) config('production.server_readiness.queue_worker_required', false);
        $driver = (string) config('queue.default', 'sync');
        $ok = ! $required || $driver !== 'sync';

        return $this->check('queue_worker_readiness', $ok, $ok ? 'Queue worker readiness is acceptable.' : 'Queue worker is required but queue driver is sync.', $required ? 'blocker' : 'warning', [
            'required' => $required,
            'driver' => $driver,
        ]);
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
}
