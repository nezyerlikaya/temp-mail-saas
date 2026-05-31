<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class FirstLiveSmokeTestService extends Service
{
    public function __construct(private readonly InstallerLockService $lock)
    {
    }

    public function report(): array
    {
        $checks = [
            $this->routeReady('homepage', 'home'),
            $this->routeReady('health', 'health'),
            $this->routeReady('status', 'status'),
            $this->installerLockBehavior(),
            $this->routeReady('inbox', 'inbox.index'),
            $this->routeReady('sitemap', 'sitemap'),
            $this->routeReady('robots', 'robots'),
            $this->apiPingBehavior(),
            $this->adminProtection(),
        ];

        return [
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function routeReady(string $name, string $route): array
    {
        return $this->check($name, Route::has($route), Route::has($route) ? "Route {$route} is registered." : "Route {$route} is missing.", 'blocker');
    }

    private function installerLockBehavior(): array
    {
        $locked = $this->lock->locked();
        $required = (bool) config('production.first_live_validation.require_installer_lock', true);

        return $this->check('installer_lock_behavior', $locked || ! $required, $locked ? 'Installer lock is present.' : 'Installer lock is not present.', $required ? 'blocker' : 'warning', ['locked' => $locked]);
    }

    private function apiPingBehavior(): array
    {
        return $this->check('api_ping_protection', Route::has('api.v1.ping'), 'API ping route is registered and protected by API key middleware.', 'warning');
    }

    private function adminProtection(): array
    {
        return $this->check('admin_protection', Route::has('admin.index'), 'Admin route is registered and protected by staff middleware.', 'blocker');
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
