<?php

namespace App\Services\System;

use App\Services\Service;

final class ProductionReadinessService extends Service
{
    public function report(): array
    {
        $checks = [
            $this->check(! config('app.debug'), 'debug_disabled', 'Debug mode should be disabled in production.', 'failure'),
            $this->check(filled((string) config('app.key')), 'app_key_present', 'Application key must be configured.', 'failure'),
            $this->httpsCheck(),
            $this->queueCheck(),
            $this->mailCheck(),
            $this->storageCheck(),
        ];

        return [
            'passed' => collect($checks)->where('status', 'passed')->count(),
            'warnings' => collect($checks)->where('status', 'warning')->count(),
            'failures' => collect($checks)->where('status', 'failure')->count(),
            'checks' => $checks,
        ];
    }

    private function httpsCheck(): array
    {
        $url = (string) config('app.url');
        $required = (bool) config('production.readiness.https_required', true);

        return $this->check(! $required || str_starts_with($url, 'https://'), 'https_expected', 'Application URL should use HTTPS for production.', 'warning');
    }

    private function queueCheck(): array
    {
        $allowed = config('production.readiness.allowed_queue_drivers', ['database', 'redis', 'sqs', 'sync']);

        return $this->check(in_array(config('queue.default'), $allowed, true), 'queue_driver_allowed', 'Queue driver should be production compatible.', 'warning');
    }

    private function mailCheck(): array
    {
        $mailer = (string) config('mail.default');
        $safe = $mailer !== 'log' || (bool) config('production.readiness.allow_log_mailer_in_production', false);

        return $this->check($safe, 'mail_configured', 'Mail transport should not use placeholder logging in production.', 'warning');
    }

    private function storageCheck(): array
    {
        return $this->check(is_writable(storage_path()), 'storage_writable', 'Storage path must be writable.', 'failure');
    }

    private function check(bool $ok, string $name, string $message, string $failureStatus): array
    {
        return [
            'name' => $name,
            'status' => $ok ? 'passed' : $failureStatus,
            'message' => $message,
        ];
    }
}
