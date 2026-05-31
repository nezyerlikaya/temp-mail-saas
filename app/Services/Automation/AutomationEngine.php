<?php

namespace App\Services\Automation;

use App\Enums\AutomationRuleStatus;
use App\Enums\AutomationTriggerType;
use App\Models\AbuseEvent;
use App\Models\AutomationExecution;
use App\Models\AutomationRule;
use App\Models\BillingWebhookEvent;
use App\Models\DomainHealthCheck;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Services\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class AutomationEngine extends Service
{
    public function __construct(
        private readonly RuleEvaluator $evaluator,
        private readonly AutomationExecutionService $executions,
    ) {}

    public function evaluate(AutomationTriggerType|string $triggerType, array $payload = [], ?string $triggerSource = null): Collection
    {
        $triggerType = $triggerType instanceof AutomationTriggerType ? $triggerType->value : $triggerType;
        $triggerSource ??= $triggerType;

        return AutomationRule::query()
            ->where('status', AutomationRuleStatus::Active)
            ->where('trigger_type', $triggerType)
            ->orderBy('priority')
            ->get()
            ->filter(fn (AutomationRule $rule): bool => $this->evaluator->matches($rule->condition_group, $payload))
            ->map(function (AutomationRule $rule) use ($payload, $triggerSource): AutomationExecution {
                $execution = $this->executions->createExecution($rule, $triggerSource, $payload);

                return $this->executions->execute($execution, $payload);
            })
            ->values();
    }

    public function consume(Model $event): Collection
    {
        return $this->evaluate(
            $this->triggerFor($event),
            $this->payloadFor($event),
            $event::class.':'.$event->getKey(),
        );
    }

    public function triggerFor(Model $event): AutomationTriggerType
    {
        return match (true) {
            $event instanceof AbuseEvent => AutomationTriggerType::AbuseEvent,
            $event instanceof QueueMetric => AutomationTriggerType::QueueEvent,
            $event instanceof DomainHealthCheck => AutomationTriggerType::DomainEvent,
            $event instanceof BillingWebhookEvent => AutomationTriggerType::BillingEvent,
            $event instanceof OperationsEvent => AutomationTriggerType::OperationsEvent,
            default => AutomationTriggerType::UserEvent,
        };
    }

    public function payloadFor(Model $event): array
    {
        $payload = $event->toArray();

        $payload['reference_type'] = $event::class;
        $payload['reference_id'] = $event->getKey();

        return $payload;
    }
}
