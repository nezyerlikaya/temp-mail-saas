<?php

namespace App\Services\Mail;

use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class ProviderConnectivityValidationService extends Service
{
    public function __construct(
        private readonly ProviderRegistryService $registry,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(?string $provider = null): array
    {
        $providers = $provider !== null ? [$this->normalize($provider)] : ['mailgun', 'postmark', 'ses'];
        $checks = collect($providers)
            ->flatMap(fn (string $name): array => $this->providerChecks($name))
            ->values()
            ->all();

        return [
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'recommendations' => collect($checks)->where('classification', 'recommendation')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function providerChecks(string $provider): array
    {
        $health = $this->registry->health($provider);
        $route = "webhooks.{$provider}";
        $class = config("mail-providers.providers.{$provider}.class");
        $configured = $health['configured'] && is_string($class) && $class !== '';

        if ($provider === 'ses') {
            $route = 'webhooks.ses';
        }

        $checks = [
            $this->check($provider, 'provider_configured', $configured, 'Provider configuration is present.', 'Provider configuration is missing.', 'blocker'),
            $this->check($provider, 'provider_activation_state', $health['enabled'], 'Provider is enabled for staging validation.', 'Provider is disabled; enable only for staging tests.', 'warning'),
            $this->check($provider, 'webhook_route_ready', Route::has($route), 'Webhook route is registered.', 'Webhook route is missing.', 'blocker'),
            $this->check($provider, 'signing_configuration_ready', $health['has_signing_key'], 'Signing configuration is present.', 'Signing configuration is missing.', 'warning'),
            $this->check($provider, 'intake_queue_ready', filled((string) config('inbound.queue.name', 'inbound-mail')), 'Inbound queue name is configured.', 'Inbound queue name is missing.', 'blocker'),
        ];

        foreach ($checks as $check) {
            if ($check['classification'] === 'blocker') {
                $this->record('staging_provider_blocked', $provider);
            }
        }

        return $checks;
    }

    private function check(string $provider, string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'provider' => $provider,
            'name' => "{$provider}_{$name}",
            'passed' => $passed,
            'classification' => $passed ? 'informational' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
            'metadata' => ['provider' => $provider],
        ];
    }

    private function normalize(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }

    private function record(string $eventType, string $provider): void
    {
        if (! (bool) config('mail-providers.staging.metrics_enabled', true)) {
            return;
        }

        $this->operations->log(
            \App\Enums\OperationCategory::Mail,
            $eventType,
            \App\Enums\OperationSeverity::Warning,
            \App\Enums\OperationStatus::Detected,
            'provider-staging',
            'Provider staging validation event recorded.',
            ['provider' => $provider],
        );
    }
}
