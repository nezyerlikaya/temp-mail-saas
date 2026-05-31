<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class BackupReadinessService extends Service
{
    public function report(): array
    {
        $checks = [
            $this->storagePaths(),
            $this->destinationDisk(),
            $this->restorePrerequisites(),
            $this->retentionGuidance(),
        ];

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['ok'] === true),
            'checks' => $checks,
        ];
    }

    private function storagePaths(): array
    {
        $paths = config('production.backup.paths', [storage_path('app'), database_path()]);
        $ok = collect($paths)->every(fn (string $path): bool => is_dir($path) && is_readable($path));

        return [
            'name' => 'backup_paths_readable',
            'ok' => $ok,
            'message' => $ok ? 'Backup source paths are readable.' : 'One or more backup source paths are not readable.',
            'metadata' => ['paths_checked' => count($paths)],
        ];
    }

    private function destinationDisk(): array
    {
        $disk = (string) config('production.backup.destination_disk', 'local');

        try {
            Storage::disk($disk);

            return [
                'name' => 'backup_destination_configured',
                'ok' => true,
                'message' => 'Backup destination disk is configured.',
                'metadata' => ['disk' => $disk],
            ];
        } catch (Throwable) {
            return [
                'name' => 'backup_destination_configured',
                'ok' => false,
                'message' => 'Backup destination disk is not configured.',
                'metadata' => ['disk' => $disk],
            ];
        }
    }

    private function restorePrerequisites(): array
    {
        $ok = (bool) config('production.backup.restore_prerequisites_documented', true);

        return [
            'name' => 'restore_prerequisites_documented',
            'ok' => $ok,
            'message' => $ok ? 'Restore prerequisites are documented.' : 'Restore prerequisites need documentation.',
            'metadata' => [],
        ];
    }

    private function retentionGuidance(): array
    {
        $ok = (bool) config('production.backup.retention_guidance_documented', true);

        return [
            'name' => 'backup_retention_guidance_documented',
            'ok' => $ok,
            'message' => $ok ? 'Backup retention guidance is documented.' : 'Backup retention guidance needs documentation.',
            'metadata' => [],
        ];
    }
}
