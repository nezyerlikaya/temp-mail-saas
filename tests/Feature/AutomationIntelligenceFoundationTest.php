<?php

namespace Tests\Feature;

use App\Enums\AbuseEventType;
use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use App\Enums\AutomationActionType;
use App\Enums\AutomationExecutionStatus;
use App\Enums\AutomationRuleStatus;
use App\Enums\AutomationTriggerType;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\StaffStatus;
use App\Enums\SystemHealthStatus;
use App\Models\AbuseEvent;
use App\Models\AutomationRule;
use App\Models\DomainHealthCheck;
use App\Models\OperationsEvent;
use App\Models\Permission;
use App\Models\QueueMetric;
use App\Models\Role;
use App\Models\StaffUser;
use App\Services\Automation\AutomationEngine;
use App\Services\Automation\IntelligenceService;
use App\Services\Automation\RuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomationIntelligenceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_automation_rules_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('automation_rules'));
        $this->assertTrue(Schema::hasColumns('automation_rules', [
            'uuid',
            'name',
            'description',
            'trigger_type',
            'condition_group',
            'action_type',
            'priority',
            'status',
            'metadata',
        ]));
    }

    public function test_automation_executions_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('automation_executions'));
        $this->assertTrue(Schema::hasColumns('automation_executions', [
            'uuid',
            'automation_rule_id',
            'trigger_source',
            'status',
            'result_summary',
            'started_at',
            'completed_at',
            'metadata',
        ]));
    }

    public function test_intelligence_scores_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('intelligence_scores'));
        $this->assertTrue(Schema::hasColumns('intelligence_scores', [
            'score_type',
            'reference_type',
            'reference_id',
            'score',
            'calculated_at',
            'metadata',
        ]));
    }

    public function test_rule_evaluation_works(): void
    {
        $evaluator = app(RuleEvaluator::class);
        $payload = [
            'severity' => 'critical',
            'risk_score' => 85,
            'metadata' => [
                'source' => 'inbox',
            ],
        ];

        $this->assertTrue($evaluator->matches([
            'all' => [
                ['field' => 'severity', 'operator' => 'equals', 'value' => 'critical'],
                ['field' => 'risk_score', 'operator' => 'greater_than_or_equal', 'value' => 80],
                ['field' => 'metadata.source', 'operator' => 'equals', 'value' => 'inbox'],
            ],
        ], $payload));

        $this->assertFalse($evaluator->matches([
            'all' => [
                ['field' => 'risk_score', 'operator' => 'less_than', 'value' => 50],
            ],
        ], $payload));
    }

    public function test_automation_engine_works_and_execution_records_are_created(): void
    {
        $rule = $this->rule([
            'trigger_type' => AutomationTriggerType::AbuseEvent,
            'action_type' => AutomationActionType::Log,
            'condition_group' => [
                'all' => [
                    ['field' => 'severity', 'operator' => 'equals', 'value' => 'critical'],
                ],
            ],
        ]);

        $executions = app(AutomationEngine::class)->evaluate(AutomationTriggerType::AbuseEvent, [
            'severity' => 'critical',
            'secret' => 'not retained',
        ], 'test:abuse');

        $this->assertCount(1, $executions);
        $this->assertSame(AutomationExecutionStatus::Completed, $executions->first()->status);
        $this->assertTrue($rule->executions()->exists());
        $this->assertDatabaseMissing('automation_executions', [
            'metadata->secret' => 'not retained',
        ]);
    }

    public function test_intelligence_scores_are_calculated(): void
    {
        $abuse = AbuseEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_type' => AbuseEventType::MailboxGeneration,
            'severity' => AbuseSeverity::High,
            'status' => AbuseStatus::Observed,
            'risk_score' => 60,
            'occurred_at' => now(),
        ]);

        $score = app(IntelligenceService::class)->scoreAbuseEvent($abuse);

        $this->assertSame('abuse_risk', $score->score_type);
        $this->assertSame(85, $score->score);
        $this->assertSame(AbuseEvent::class, $score->reference_type);
    }

    public function test_abuse_event_integration_works(): void
    {
        $this->rule([
            'trigger_type' => AutomationTriggerType::AbuseEvent,
            'action_type' => AutomationActionType::Score,
            'condition_group' => [
                'all' => [
                    ['field' => 'risk_score', 'operator' => 'greater_than', 'value' => 70],
                ],
            ],
            'metadata' => [
                'score_type' => 'abuse_risk',
                'score' => 90,
            ],
        ]);

        $abuse = AbuseEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_type' => AbuseEventType::InboxPolling,
            'severity' => AbuseSeverity::Critical,
            'status' => AbuseStatus::Throttled,
            'risk_score' => 80,
            'occurred_at' => now(),
        ]);

        $executions = app(AutomationEngine::class)->consume($abuse);

        $this->assertCount(1, $executions);
        $this->assertDatabaseHas('intelligence_scores', [
            'score_type' => 'abuse_risk',
            'reference_type' => AbuseEvent::class,
            'reference_id' => $abuse->id,
            'score' => 90,
        ]);
    }

    public function test_operations_event_integration_works(): void
    {
        $this->rule([
            'trigger_type' => AutomationTriggerType::OperationsEvent,
            'action_type' => AutomationActionType::Log,
            'condition_group' => [
                'all' => [
                    ['field' => 'severity', 'operator' => 'equals', 'value' => 'critical'],
                ],
            ],
        ]);

        $event = OperationsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => OperationCategory::Queue,
            'event_type' => 'queue.failed',
            'severity' => OperationSeverity::Critical,
            'status' => OperationStatus::Detected,
            'occurred_at' => now(),
        ]);

        $executions = app(AutomationEngine::class)->consume($event);

        $this->assertCount(1, $executions);
        $this->assertSame(AutomationExecutionStatus::Completed, $executions->first()->status);
    }

    public function test_domain_health_integration_works(): void
    {
        $check = DomainHealthCheck::query()->create([
            'domain' => 'example.test',
            'status' => SystemHealthStatus::Warning,
            'score' => 65,
            'checked_at' => now(),
        ]);

        $score = app(IntelligenceService::class)->scoreDomainHealth($check);

        $this->assertSame('domain_health', $score->score_type);
        $this->assertSame(65, $score->score);
        $this->assertSame(DomainHealthCheck::class, $score->reference_type);
    }

    public function test_queue_health_scoring_supports_queue_metrics(): void
    {
        $metric = QueueMetric::query()->create([
            'queue_name' => 'default',
            'pending_jobs' => 5,
            'failed_jobs' => 2,
            'processed_jobs' => 100,
            'measured_at' => now(),
        ]);

        $score = app(IntelligenceService::class)->scoreQueueHealth($metric);

        $this->assertSame('queue_health', $score->score_type);
        $this->assertSame(70, $score->score);
    }

    public function test_rbac_permissions_are_registered_and_enforced(): void
    {
        $permissions = config('permissions.groups.automation');

        $this->assertArrayHasKey('automation.view', $permissions);
        $this->assertArrayHasKey('automation.manage', $permissions);
        $this->assertArrayHasKey('intelligence.view', $permissions);

        $staff = $this->staffWithPermissions(['automation.view', 'intelligence.view']);

        $this->assertTrue(Gate::forUser($staff)->allows('staff-permission', 'automation.view'));
        $this->assertTrue(Gate::forUser($staff)->allows('staff-permission', 'intelligence.view'));
        $this->assertFalse(Gate::forUser($staff)->allows('staff-permission', 'automation.manage'));
    }

    private function rule(array $overrides = []): AutomationRule
    {
        return AutomationRule::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test automation rule',
            'trigger_type' => AutomationTriggerType::ScheduledEvent,
            'condition_group' => null,
            'action_type' => AutomationActionType::Log,
            'priority' => 10,
            'status' => AutomationRuleStatus::Active,
            'metadata' => [],
        ], $overrides));
    }

    private function staffWithPermissions(array $permissions): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => 'Automation Staff',
            'email' => uniqid('staff-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Automation Role',
            'slug' => uniqid('automation-role-', false),
            'is_system' => false,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'group' => str($slug)->before('.')->toString(),
                ],
            );

            $role->permissions()->attach($permission);
        }

        $staff->roles()->attach($role);

        return $staff;
    }
}
