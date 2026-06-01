<?php

namespace App\Services\Billing;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\Plan;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class RevenueReadinessService extends Service
{
    public function __construct(
        private readonly CustomerLifecycleReadinessService $customers,
        private readonly SubscriptionOperationsService $subscriptions,
        private readonly PaymentIncidentReadinessService $incidents,
        private readonly RevenueCertificationService $certification,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('revenue_review_started');

        $billing = $this->billingReview();
        $plan = $this->planReview();
        $webhook = $this->webhookReview();
        $rollback = $this->rollbackReview();
        $customers = $this->customers->report();
        $subscriptions = $this->subscriptions->review();
        $incidents = $this->incidents->report();
        $certification = $this->certification->certify($billing);
        $sections = compact('billing', 'plan', 'webhook', 'rollback', 'customers', 'subscriptions', 'incidents');
        $blockers = $this->issues($sections, 'blockers');
        $warnings = [
            ...$this->issues($sections, 'warnings'),
            ...$certification['warnings'],
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record('revenue_review_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'certification' => $certification['status'],
        ]);

        if ($certification['status'] === 'certified') {
            $this->record('revenue_certified');
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([...$blockers, ...$warnings])->pluck('message')->unique()->values()->all(),
            'certification' => $certification,
            'sections' => $sections,
        ];
    }

    private function billingReview(): array
    {
        $checks = [
            $this->check('billing_enabled', ! (bool) config('billing.revenue_readiness.require_billing_enabled', true) || (bool) config('billing.enabled', true), 'Billing is enabled.', 'Billing must be enabled.', 'blocker'),
            $this->check('billing_tables', collect(['billing_customers', 'billing_subscriptions', 'billing_invoices', 'billing_webhook_events'])->every(fn (string $table): bool => Schema::hasTable($table)), 'Billing persistence tables are available.', 'Billing persistence tables are missing.', 'blocker'),
            $this->check('no_card_storage', ! (bool) config('billing.revenue_readiness.require_no_card_storage', true) || $this->noCardStorage(), 'No card storage fields are present.', 'PCI-sensitive card storage fields must not exist.', 'blocker'),
            $this->check('model_secret_hiding', $this->hiddenProviderIds(), 'Provider identifiers are hidden from model serialization.', 'Provider identifier hiding needs review.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function planReview(): array
    {
        $required = ['free', 'member', 'premium'];
        $plansReady = Plan::query()->whereIn('slug', $required)->where('is_active', true)->count() === count($required);
        $mapReady = collect(config('billing.provider_plan_map', []))->intersect($required)->isNotEmpty();

        return $this->summarize([
            $this->check('seeded_plans', $plansReady, 'Free, member, and premium plans are active.', 'Required revenue plans are missing.', 'blocker'),
            $this->check('provider_plan_map', ! (bool) config('billing.revenue_readiness.require_plan_map', true) || $mapReady, 'Provider plan map is configured.', 'Provider plan map needs review.', 'blocker'),
        ]);
    }

    private function webhookReview(): array
    {
        $provider = (string) config('billing.revenue_readiness.provider', config('billing.default_provider', 'local'));

        return $this->summarize([
            $this->check('billing_webhook_route', Route::has('billing.webhooks.handle'), 'Billing webhook route is registered.', 'Billing webhook route is missing.', 'blocker'),
            $this->check('webhook_secret_readiness', ! (bool) config('billing.revenue_readiness.require_webhook_secret', true) || filled((string) config("billing.providers.{$provider}.webhook_secret")), 'Webhook signing readiness is configured outside reports.', 'Webhook signing readiness needs review.', 'blocker'),
            $this->check('webhook_hash_storage', Schema::hasColumn('billing_webhook_events', 'payload_hash') && ! Schema::hasColumn('billing_webhook_events', 'payload'), 'Webhook events store payload hashes only.', 'Webhook payload storage must remain disabled.', 'blocker'),
        ]);
    }

    private function rollbackReview(): array
    {
        return $this->summarize([
            $this->check('manual_assignment_fallback', Schema::hasTable('user_plan_assignments'), 'Manual plan assignment fallback is available.', 'Manual plan assignment fallback is missing.', 'blocker'),
            $this->check('payment_incident_rollback', (bool) config('billing.revenue_readiness.incidents.rollback', true), 'Payment incident rollback readiness is documented.', 'Payment incident rollback readiness needs review.', 'blocker'),
        ]);
    }

    private function noCardStorage(): bool
    {
        return collect(['billing_customers', 'billing_subscriptions', 'billing_invoices', 'billing_webhook_events'])
            ->every(fn (string $table): bool => collect(['card_number', 'card_last_four', 'payment_method_id'])->every(fn (string $column): bool => ! Schema::hasColumn($table, $column)));
    }

    private function hiddenProviderIds(): bool
    {
        return in_array('provider_customer_id', (new BillingCustomer)->getHidden(), true)
            && in_array('provider_subscription_id', (new BillingSubscription)->getHidden(), true)
            && in_array('provider_invoice_id', (new BillingInvoice)->getHidden(), true);
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
            'status' => collect($checks)->where('classification', 'blocker')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function issues(array $sections, string $type): array
    {
        return collect($sections)
            ->flatMap(fn (array $section, string $category): array => collect($section[$type])
                ->map(fn (array $issue): array => ['category' => $category, ...$issue])
                ->all())
            ->values()
            ->all();
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'revenue-readiness',
            'Revenue readiness event recorded.',
            $metadata,
        );
    }
}
