<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\File;
use Throwable;

final class InstallerLockService extends Service
{
    public function __construct(
        private readonly ?string $path = null,
    ) {}

    public function locked(): bool
    {
        return File::exists($this->lockPath());
    }

    public function create(): array
    {
        try {
            File::ensureDirectoryExists(dirname($this->lockPath()));
            File::put($this->lockPath(), json_encode([
                'installed_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return [
                'ok' => true,
                'locked' => true,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'locked' => false,
                'message' => 'Installer lock could not be created.',
            ];
        }
    }

    public function remove(): array
    {
        try {
            if ($this->locked()) {
                File::delete($this->lockPath());
            }

            return [
                'ok' => true,
                'locked' => false,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'locked' => $this->locked(),
                'message' => 'Installer lock could not be removed.',
            ];
        }
    }

    public function status(): array
    {
        return [
            'locked' => $this->locked(),
            'strategy' => 'storage_file',
            'label' => 'storage/app/install.lock',
        ];
    }

    private function lockPath(): string
    {
        return $this->path ?? (string) config('installer.lock_path', storage_path('app/install.lock'));
    }
}
