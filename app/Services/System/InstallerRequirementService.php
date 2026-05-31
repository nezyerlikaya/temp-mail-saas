<?php

namespace App\Services\System;

use App\Services\Service;
use PDO;

final class InstallerRequirementService extends Service
{
    public function results(): array
    {
        $checks = [
            $this->phpVersion(),
            ...$this->extensions(),
            $this->writable('storage', storage_path()),
            $this->writable('bootstrap_cache', base_path('bootstrap/cache')),
            $this->appKey(),
            $this->databaseDriver(),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check): bool => $check['ok'] === true),
            'checks' => $checks,
        ];
    }

    private function phpVersion(): array
    {
        return [
            'key' => 'php',
            'label' => 'PHP 8.3 or newer',
            'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'current' => PHP_VERSION,
        ];
    }

    private function extensions(): array
    {
        return collect([
            'ctype',
            'curl',
            'dom',
            'fileinfo',
            'filter',
            'hash',
            'mbstring',
            'openssl',
            'pdo',
            'session',
            'tokenizer',
            'xml',
        ])->map(fn (string $extension): array => [
            'key' => "extension_{$extension}",
            'label' => "PHP extension: {$extension}",
            'ok' => extension_loaded($extension),
        ])->all();
    }

    private function writable(string $key, string $path): array
    {
        return [
            'key' => $key,
            'label' => str_replace('_', '/', $key).' writable',
            'ok' => is_dir($path) && is_writable($path),
        ];
    }

    private function appKey(): array
    {
        return [
            'key' => 'app_key',
            'label' => 'Application key configured',
            'ok' => filled((string) config('app.key')),
        ];
    }

    private function databaseDriver(): array
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver", '');
        $available = match ($driver) {
            'sqlite' => in_array('sqlite', PDO::getAvailableDrivers(), true),
            'mysql', 'mariadb' => in_array('mysql', PDO::getAvailableDrivers(), true),
            'pgsql' => in_array('pgsql', PDO::getAvailableDrivers(), true),
            'sqlsrv' => in_array('sqlsrv', PDO::getAvailableDrivers(), true),
            default => false,
        };

        return [
            'key' => 'database_driver',
            'label' => 'Database driver available',
            'ok' => $available,
            'driver' => $driver,
        ];
    }
}
