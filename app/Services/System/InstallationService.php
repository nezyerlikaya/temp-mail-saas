<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\File;

final class InstallationService extends Service
{
    public function __construct(
        private readonly InstallerLockService $lock,
        private readonly InstallerDatabaseService $database,
    ) {}

    public function status(): array
    {
        $environment = $this->environmentStatus();
        $database = $this->database->status();
        $lock = $this->lock->status();
        $installed = $environment['env_exists']
            && $environment['app_key_configured']
            && $database['ok'] === true;
        $healthy = $installed && $lock['locked'] === true;
        $recovery = ! $environment['env_exists']
            || ! $environment['app_key_configured']
            || ! $installed
            || $database['ok'] !== true;

        return [
            'installed' => $installed,
            'healthy' => $healthy,
            'recovery' => $recovery,
            'installer_accessible' => ! $healthy || $recovery,
            'environment' => $environment,
            'database' => $database,
            'lock' => $lock,
        ];
    }

    public function installerAccessible(): bool
    {
        return $this->status()['installer_accessible'] === true;
    }

    public function installed(): bool
    {
        return $this->status()['installed'] === true;
    }

    public function environmentStatus(): array
    {
        $envPath = (string) config('installer.env_path', base_path('.env'));
        $envExists = File::exists($envPath);
        $envAppKey = $envExists ? $this->readEnvValue($envPath, 'APP_KEY') : null;

        return [
            'env_exists' => $envExists,
            'app_key_configured' => $envExists ? filled($envAppKey) : filled((string) config('app.key')),
            'app_key_in_env' => filled($envAppKey),
        ];
    }

    private function readEnvValue(string $path, string $key): ?string
    {
        foreach (preg_split("/\r\n|\n|\r/", File::get($path)) as $line) {
            if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=\s*(.*)\s*$/', (string) $line, $matches)) {
                return trim((string) $matches[1], " \t\n\r\0\x0B\"'");
            }
        }

        return null;
    }
}
