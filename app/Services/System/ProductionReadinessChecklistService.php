<?php

namespace App\Services\System;

use App\Models\DomainHealthCheck;
use App\Models\QueueMetric;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class ProductionReadinessChecklistService extends Service
{
    public function __construct(
        private readonly BackupReadinessService $backup,
        private readonly SystemHealthService $health,
    ) {}

    public function report(): array
    {
        $checks = [
            ...$this->deploymentChecks(),
            ...$this->configurationChecks(),
            ...$this->backupChecks(),
            ...$this->monitoringChecks(),
            ...$this->goLiveChecks(),
        ];

        return [
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'recommendations' => collect($checks)->where('classification', 'recommendation')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function deploymentChecks(): array
    {
        $paths = config('production.deployment.required_writable_paths', []);
        $paths = is_array($paths) ? $paths : [];

        return [
            $this->check(
                'app_key_configured',
                filled((string) config('app.key')),
                'APP_KEY is configured.',
                'APP_KEY is missing.',
                (bool) config('production.release.block_on_missing_app_key', true) ? 'blocker' : 'warning',
            ),
            $this->check(
                'storage_paths_writable',
                collect($paths)->every(fn (string $path): bool => is_dir($path) && is_writable($path)),
                'Required writable paths are available.',
                'One or more required writable paths are missing or not writable.',
                (bool) config('production.release.block_on_unwritable_storage', true) ? 'blocker' : 'warning',
                ['paths_checked' => count($paths)],
            ),
        ];
    }

    private function configurationChecks(): array
    {
        $queue = (string) config('queue.default', 'sync');
        $mailer = (string) config('mail.default', 'log');
        $cache = (string) config('cache.default', 'file');
        $session = (string) config('session.driver', 'file');

        return [
            $this->check(
                'debug_disabled',
                ! (bool) config('app.debug', false),
                'APP_DEBUG is disabled.',
                'APP_DEBUG is enabled.',
                (bool) config('production.release.block_on_debug', true) ? 'blocker' : 'warning',
            ),
            $this->check(
                'app_url_https',
                ! (bool) config('production.release.warn_on_http_url', true) || str_starts_with((string) config('app.url'), 'https://'),
                'APP_URL is HTTPS or HTTPS warning is disabled.',
                'APP_URL is not HTTPS.',
                'warning',
            ),
            $this->check(
                'queue_driver_ready',
                $queue !== 'sync' || ! (bool) config('production.release.warn_on_sync_queue', true),
                'Queue driver is suitable for background work.',
                'Queue driver is sync; background work will run inline.',
                'warning',
                ['driver' => $queue],
            ),
            $this->check(
                'mail_transport_ready',
                $mailer !== 'log' || ! (bool) config('production.release.warn_on_log_mailer', true),
                'Mail transport is not placeholder logging.',
                'Mail transport uses log driver.',
                'warning',
                ['mailer' => $mailer],
            ),
            $this->check(
                'cache_store_configured',
                filled($cache),
                'Cache store is configured.',
                'Cache store is missing.',
                'warning',
                ['store' => $cache],
            ),
            $this->check(
                'session_driver_configured',
                filled($session),
                'Session driver is configured.',
                'Session driver is missing.',
                'warning',
                ['driver' => $session],
            ),
        ];
    }

    private function backupChecks(): array
    {
        $backup = $this->backup->report();

        return [
            $this->check(
                'backup_readiness',
                $backup['ready'] === true,
                'Backup readiness checks passed.',
                'Backup readiness checks require attention.',
                'warning',
                ['checks' => count($backup['checks'])],
            ),
        ];
    }

    private function monitoringChecks(): array
    {
        $health = $this->health->run(store: false);
        $metricsRequired = (bool) config('production.monitoring.operations_metrics_required', false);

        return [
            $this->check(
                'health_routes_registered',
                Route::has('health') && Route::has('status'),
                'Health and status routes are registered.',
                'Health or status routes are missing.',
                'blocker',
            ),
            $this->check(
                'system_health_available',
                isset($health['status'], $health['checks']) && $health['checks'] !== [],
                'System health service returns checks.',
                'System health service did not return checks.',
                'blocker',
            ),
            $this->check(
                'queue_metrics_available',
                ! $metricsRequired || QueueMetric::query()->exists(),
                'Queue metrics are available or not required for RC.',
                'Queue metrics have not been collected yet.',
                'recommendation',
            ),
            $this->check(
                'domain_health_available',
                ! $metricsRequired || DomainHealthCheck::query()->exists(),
                'Domain health checks are available or not required for RC.',
                'Domain health checks have not been collected yet.',
                'recommendation',
            ),
        ];
    }

    private function goLiveChecks(): array
    {
        return [
            $this->check('installer_route_available', Route::has('installer.index'), 'Installer route is registered.', 'Installer route is missing.', 'blocker'),
            $this->check('auth_routes_available', Route::has('login') && Route::has('register'), 'Auth routes are registered.', 'Auth routes are missing.', 'blocker'),
            $this->check('api_route_available', Route::has('api.v1.ping'), 'API foundation route is registered.', 'API foundation route is missing.', 'warning'),
            $this->check('admin_route_available', Route::has('admin.index'), 'Admin operations route is registered.', 'Admin operations route is missing.', 'warning'),
            $this->check('inbox_route_available', Route::has('inbox.index'), 'Public inbox route is registered.', 'Public inbox route is missing.', 'warning'),
        ];
    }

    private function check(
        string $name,
        bool $passed,
        string $passedMessage,
        string $failedMessage,
        string $classification,
        array $metadata = [],
    ): array {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'informational' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
            'metadata' => $metadata,
        ];
    }
}
