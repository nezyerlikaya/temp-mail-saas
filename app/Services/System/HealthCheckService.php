<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class HealthCheckService extends Service
{
    public function __construct(
        private readonly AppConfigService $config,
        private readonly EnvironmentService $environment,
    ) {
    }

    public function report(): array
    {
        $checks = [
            'application' => $this->applicationStatus(),
            'environment' => $this->environmentStatus(),
            'cache' => $this->cacheStatus(),
            'storage' => $this->storageStatus(),
            'configuration' => $this->configurationStatus(),
        ];

        return [
            'status' => in_array('fail', $checks, true) ? 'degraded' : 'ok',
            'checks' => $checks,
        ];
    }

    public function publicStatus(): array
    {
        $status = $this->report()['status'];

        return [
            'application' => $this->config->publicName(),
            'status' => $status,
            'availability' => $status === 'ok' ? 'Operational' : 'Degraded',
        ];
    }

    private function applicationStatus(): string
    {
        return filled($this->config->publicName()) ? 'ok' : 'fail';
    }

    private function environmentStatus(): string
    {
        $summary = $this->environment->summary();

        return isset($summary['environment'], $summary['app_key'], $summary['debug']) ? 'ok' : 'fail';
    }

    private function cacheStatus(): string
    {
        try {
            Cache::store(config('cache.default'))->get('health-check');

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function storageStatus(): string
    {
        $storage = $this->environment->storage();

        return $storage['available'] === true && $storage['writable'] === true ? 'ok' : 'fail';
    }

    private function configurationStatus(): string
    {
        return filled($this->config->defaultLocale())
            && filled($this->config->fallbackLocale())
            && filled($this->config->timezone())
            ? 'ok'
            : 'fail';
    }
}
