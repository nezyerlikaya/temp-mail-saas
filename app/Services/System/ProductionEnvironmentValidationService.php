<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProductionEnvironmentValidationService extends Service
{
    public function __construct(private readonly InstallerLockService $lock)
    {
    }

    public function report(): array
    {
        $checks = [
            $this->appEnvironment(),
            $this->debugMode(),
            $this->appKey(),
            $this->database(),
            $this->cache(),
            $this->sessionDriver(),
            $this->queueDriver(),
            $this->mailConfiguration(),
            $this->filesystem(),
            $this->installerLock(),
        ];

        return $this->summarize($checks);
    }

    private function appEnvironment(): array
    {
        $productionRequired = (bool) config('production.first_live_validation.require_app_env_production', true);
        $env = (string) config('app.env', 'production');
        $ok = $env === 'production' || ! $productionRequired;

        return $this->check('app_env', $ok, $ok ? 'APP_ENV is suitable for first live validation.' : 'APP_ENV should be production for go-live.', $productionRequired ? 'blocker' : 'warning', ['env' => $env]);
    }

    private function debugMode(): array
    {
        $ok = ! (bool) config('app.debug', false);

        return $this->check('app_debug', $ok, $ok ? 'APP_DEBUG is disabled.' : 'APP_DEBUG must be disabled.', 'blocker');
    }

    private function appKey(): array
    {
        $ok = filled((string) config('app.key'));

        return $this->check('app_key', $ok, $ok ? 'APP_KEY is configured.' : 'APP_KEY is missing.', 'blocker');
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->check('database_connectivity', true, 'Database connection is available.', 'blocker', ['connection' => (string) config('database.default')]);
        } catch (Throwable) {
            return $this->check('database_connectivity', false, 'Database connection failed.', 'blocker', ['connection' => (string) config('database.default')]);
        }
    }

    private function cache(): array
    {
        try {
            $key = 'tempmail:first-live:cache';
            Cache::put($key, 'ok', now()->addSeconds(10));
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $this->check('cache_store', $ok, $ok ? 'Cache store accepts reads and writes.' : 'Cache store did not return the expected value.', 'warning', ['store' => (string) config('cache.default')]);
        } catch (Throwable) {
            return $this->check('cache_store', false, 'Cache store is not writable.', 'warning', ['store' => (string) config('cache.default')]);
        }
    }

    private function sessionDriver(): array
    {
        $driver = (string) config('session.driver', 'file');
        $ok = filled($driver);

        return $this->check('session_driver', $ok, $ok ? 'Session driver is configured.' : 'Session driver is missing.', 'warning', ['driver' => $driver]);
    }

    private function queueDriver(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $warnSync = (bool) config('production.first_live_validation.warn_on_sync_queue', true);
        $ok = $driver !== 'sync' || ! $warnSync;

        return $this->check('queue_driver', $ok, $ok ? 'Queue driver is suitable for launch validation.' : 'Queue driver is sync; background work will run inline.', 'warning', ['driver' => $driver]);
    }

    private function mailConfiguration(): array
    {
        $mailer = (string) config('mail.default', 'log');
        $warnLog = (bool) config('production.first_live_validation.warn_on_log_mailer', true);
        $ok = $mailer !== 'log' || ! $warnLog;

        return $this->check('mail_configuration', $ok, $ok ? 'Mail configuration is suitable for launch validation.' : 'Mail configuration uses log transport placeholder.', 'warning', ['mailer' => $mailer]);
    }

    private function filesystem(): array
    {
        $disk = (string) config('filesystems.default', 'local');

        try {
            Storage::disk($disk);
            $ok = File::isWritable(storage_path()) && File::isWritable(storage_path('app'));

            return $this->check('filesystem_storage', $ok, $ok ? 'Filesystem storage is available.' : 'Storage paths are not writable.', 'blocker', ['disk' => $disk]);
        } catch (Throwable) {
            return $this->check('filesystem_storage', false, 'Filesystem disk is not configured.', 'blocker', ['disk' => $disk]);
        }
    }

    private function installerLock(): array
    {
        $status = $this->lock->status();
        $required = (bool) config('production.first_live_validation.require_installer_lock', true);
        $ok = $status['locked'] === true || ! $required;

        return $this->check('installer_lock', $ok, $ok ? 'Installer lock status is suitable.' : 'Installer lock is missing.', $required ? 'blocker' : 'warning', ['locked' => $status['locked']]);
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
