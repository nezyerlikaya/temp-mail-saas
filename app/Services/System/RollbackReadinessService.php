<?php

namespace App\Services\System;

use App\Services\Service;

final class RollbackReadinessService extends Service
{
    public function __construct(private readonly BackupReadinessService $backup)
    {
    }

    public function report(): array
    {
        $checks = [
            $this->backupReady(),
            $this->deploymentNotes(),
            $this->restorePrerequisites(),
        ];

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['passed'] === true),
            'risks' => collect($checks)->where('passed', false)->values()->all(),
            'checks' => $checks,
        ];
    }

    private function backupReady(): array
    {
        $backup = $this->backup->report();
        $required = (bool) config('production.rollback.require_backup_ready', true);

        return $this->check(
            'rollback_backup_ready',
            $backup['ready'] === true || ! $required,
            $backup['ready'] === true ? 'Backup readiness checks passed.' : 'Backup readiness is not complete.',
            ['checks' => count($backup['checks'])],
        );
    }

    private function deploymentNotes(): array
    {
        $required = (bool) config('production.rollback.require_deployment_notes', true);
        $path = (string) config('production.rollback.documentation_path', 'docs/deployment/shared-hosting.md');

        return $this->check(
            'rollback_deployment_notes',
            ! $required || is_file(base_path($path)),
            'Rollback deployment notes are available.',
            ['path' => $path],
        );
    }

    private function restorePrerequisites(): array
    {
        $required = (bool) config('production.rollback.require_restore_prerequisites', true);
        $documented = (bool) config('production.backup.restore_prerequisites_documented', true);

        return $this->check(
            'rollback_restore_prerequisites',
            ! $required || $documented,
            'Restore prerequisites are documented.',
            [],
        );
    }

    private function check(string $name, bool $passed, string $message, array $metadata = []): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
            'metadata' => $metadata,
        ];
    }
}
