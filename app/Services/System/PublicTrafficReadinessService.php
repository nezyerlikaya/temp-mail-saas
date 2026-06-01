<?php

namespace App\Services\System;

use App\Enums\AbuseEventType;
use App\Services\Abuse\RateLimitProfileService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class PublicTrafficReadinessService extends Service
{
    public function __construct(private readonly RateLimitProfileService $limits) {}

    public function report(): array
    {
        $polling = $this->limits->for(AbuseEventType::InboxPolling);
        $route = Route::getRoutes()->getByName('inbox.messages');
        $middleware = $route?->gatherMiddleware() ?? [];
        $checks = [
            $this->check('inbox_traffic', ! (bool) config('production.public_launch.traffic.inbox_enabled', true) || Route::has('inbox.index'), 'Public inbox traffic route is ready.', 'Public inbox traffic route is unavailable.', 'blocker'),
            $this->check('polling_readiness', ! (bool) config('production.public_launch.traffic.polling_enabled', true) || (in_array('throttle:inbox-message-polling', $middleware, true) && (int) ($polling['per_minute'] ?? 0) > 0), 'Inbox polling is rate limited.', 'Inbox polling rate limit readiness needs review.', 'blocker'),
            $this->check('queue_readiness', ! (bool) config('production.public_launch.traffic.queue_required', true) || (string) config('queue.default', 'sync') !== 'sync', 'Worker-backed queue is configured.', 'Worker-backed queue is required for public traffic.', 'blocker'),
            $this->check('abuse_protection', ! (bool) config('production.public_launch.traffic.abuse_protection_required', true) || ((bool) config('abuse.enabled', true) && (bool) config('abuse.rate_limits.enabled', true)), 'Abuse protection is enabled.', 'Abuse protection must be enabled.', 'blocker'),
            $this->check('monitoring_readiness', ! (bool) config('production.public_launch.traffic.monitoring_required', true) || (bool) config('monitoring.enabled', true), 'Monitoring is enabled for public traffic.', 'Monitoring must be enabled for public traffic.', 'blocker'),
        ];

        return $this->summarize($checks);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->contains(fn (array $check): bool => $check['classification'] === 'blocker') ? 'blocked' : 'ready',
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }
}
