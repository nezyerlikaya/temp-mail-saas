<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

final class InstallerDatabaseService extends Service
{
    public function status(?string $connection = null): array
    {
        $connectionName = $connection ?: (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver", '');
        $driverAvailable = $this->driverAvailable($driver);

        if (! $driverAvailable) {
            return [
                'ok' => false,
                'connection' => $connectionName,
                'driver' => $driver,
                'driver_available' => false,
                'message' => 'Database driver is not available.',
            ];
        }

        try {
            DB::connection($connectionName)->select('select 1');

            return [
                'ok' => true,
                'connection' => $connectionName,
                'driver' => $driver,
                'driver_available' => true,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'connection' => $connectionName,
                'driver' => $driver,
                'driver_available' => true,
                'message' => 'Database connection could not be verified.',
            ];
        }
    }

    public function validate(array $settings): array
    {
        $driver = (string) ($settings['DB_CONNECTION'] ?? config('database.default'));

        return [
            'ok' => $this->driverAvailable($driver),
            'driver' => $driver,
            'driver_available' => $this->driverAvailable($driver),
            'message' => $this->driverAvailable($driver)
                ? 'Database driver is available.'
                : 'Database driver is not available.',
        ];
    }

    private function driverAvailable(string $driver): bool
    {
        return match ($driver) {
            'sqlite' => in_array('sqlite', PDO::getAvailableDrivers(), true),
            'mysql', 'mariadb' => in_array('mysql', PDO::getAvailableDrivers(), true),
            'pgsql' => in_array('pgsql', PDO::getAvailableDrivers(), true),
            'sqlsrv' => in_array('sqlsrv', PDO::getAvailableDrivers(), true),
            default => false,
        };
    }
}
