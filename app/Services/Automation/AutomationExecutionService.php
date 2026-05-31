<?php

namespace App\Services\Automation;

use App\Enums\AutomationActionType;
use App\Enums\AutomationExecutionStatus;
use App\Models\AutomationExecution;
use App\Models\AutomationRule;
use App\Services\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

final class AutomationExecutionService extends Service
{
    public function __construct(
        private readonly IntelligenceService $intelligence,
    ) {}

    public function createExecution(AutomationRule $rule, string $triggerSource, array $payload = []): AutomationExecution
    {
        return AutomationExecution::query()->create([
            'uuid' => (string) Str::uuid(),
            'automation_rule_id' => $rule->id,
            'trigger_source' => $triggerSource,
            'status' => AutomationExecutionStatus::Pending,
            'metadata' => $this->safeMetadata($payload),
        ]);
    }

    public function execute(AutomationExecution $execution, array $payload = []): AutomationExecution
    {
        $execution->update([
            'status' => AutomationExecutionStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $summary = $this->executeAction($execution->rule, $payload);

            $execution->update([
                'status' => AutomationExecutionStatus::Completed,
                'result_summary' => $summary,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $execution->update([
                'status' => AutomationExecutionStatus::Failed,
                'result_summary' => Str::limit(class_basename($exception), 255, ''),
                'completed_at' => now(),
            ]);
        }

        return $execution->fresh();
    }

    private function executeAction(AutomationRule $rule, array $payload): string
    {
        return match ($rule->action_type) {
            AutomationActionType::Score => $this->scoreAction($rule, $payload),
            AutomationActionType::Log => 'Automation log action recorded.',
            AutomationActionType::Notify => 'Notification action prepared.',
            AutomationActionType::Tag => 'Tag action prepared.',
            AutomationActionType::QueueJob => 'Queue job action prepared.',
        };
    }

    private function scoreAction(AutomationRule $rule, array $payload): string
    {
        $scoreType = (string) Arr::get($rule->metadata ?? [], 'score_type', 'automation');
        $score = (int) Arr::get($rule->metadata ?? [], 'score', Arr::get($payload, 'score', 0));

        $this->intelligence->recordScore(
            $scoreType,
            $score,
            Arr::get($payload, 'reference_type'),
            Arr::get($payload, 'reference_id'),
            ['source' => 'automation_rule', 'rule_uuid' => $rule->uuid],
        );

        return "Score action recorded for {$scoreType}.";
    }

    private function safeMetadata(array $payload): array
    {
        return collect($payload)
            ->reject(fn (mixed $value, string|int $key): bool => $this->sensitiveKey((string) $key))
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->safeMetadata($value)
                : (is_scalar($value) || $value === null ? $value : null))
            ->all();
    }

    private function sensitiveKey(string $key): bool
    {
        $key = Str::lower($key);

        return collect(['body', 'content', 'email', 'payload', 'raw', 'secret', 'token', 'password'])
            ->contains(fn (string $sensitive): bool => str_contains($key, $sensitive));
    }
}
