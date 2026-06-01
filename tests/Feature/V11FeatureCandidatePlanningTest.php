<?php

namespace Tests\Feature;

use App\Enums\FeatureCandidateCategory;
use App\Enums\FeatureCandidateEffort;
use App\Enums\FeatureCandidateRisk;
use App\Enums\FeatureCandidateStatus;
use App\Models\FeatureCandidate;
use App\Models\OperationsEvent;
use App\Services\Roadmap\FeatureCandidateService;
use App\Services\Roadmap\FeatureImplementationReadinessService;
use App\Services\Roadmap\V11FeaturePrioritizationService;
use App\Services\Roadmap\V11ReleasePlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class V11FeatureCandidatePlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_candidate_migration_and_model_work(): void
    {
        $this->assertTrue(Schema::hasTable('feature_candidates'));
        $this->assertTrue(Schema::hasColumns('feature_candidates', [
            'uuid',
            'title',
            'description',
            'category',
            'priority',
            'status',
            'effort',
            'impact',
            'risk',
            'metadata',
        ]));

        $candidate = $this->candidate();

        $this->assertTrue(Str::isUuid($candidate->uuid));
        $this->assertSame(FeatureCandidateCategory::Inbox, $candidate->category);
        $this->assertSame(FeatureCandidateStatus::Proposed, $candidate->status);
        $this->assertSame(FeatureCandidateEffort::Small, $candidate->effort);
        $this->assertSame(FeatureCandidateRisk::Low, $candidate->risk);
    }

    public function test_candidate_service_lifecycle_records_events(): void
    {
        $service = app(FeatureCandidateService::class);
        $candidate = $this->candidate();

        $this->assertSame(FeatureCandidateStatus::Reviewed, $service->review($candidate)->status);
        $this->assertSame(FeatureCandidateStatus::Accepted, $service->accept($candidate)->status);
        $this->assertTrue($candidate->fresh()->isAccepted());
        $this->assertSame(FeatureCandidateStatus::Deferred, $service->defer($candidate)->status);
        $this->assertTrue($candidate->fresh()->isDeferred());
        $this->assertSame(FeatureCandidateStatus::Rejected, $service->reject($candidate)->status);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'feature_candidate_created']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'feature_candidate_reviewed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'feature_candidate_accepted']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'feature_candidate_deferred']);
    }

    public function test_candidate_service_sanitizes_metadata_and_text(): void
    {
        $candidate = app(FeatureCandidateService::class)->create([
            'title' => '<b>Contact user@example.com</b>',
            'description' => 'Do not leak mailbox@example.test.',
            'metadata' => [
                'token' => 'secret-token',
                'payload' => 'raw-payload',
                'label' => '<i>Reach user@example.com</i>',
            ],
        ]);

        $this->assertSame('Contact [redacted-email]', $candidate->title);
        $this->assertSame('Do not leak [redacted-email].', $candidate->description);
        $this->assertSame(['label' => 'Reach [redacted-email]'], $candidate->metadata);
        $this->assertStringNotContainsString('secret-token', OperationsEvent::query()->latest('id')->firstOrFail()->toJson());
    }

    public function test_prioritization_scores_candidates(): void
    {
        $candidate = $this->candidate(priority: 'high', effort: 'small', impact: 'critical', risk: 'low');

        $score = app(V11FeaturePrioritizationService::class)->score($candidate);

        $this->assertSame('prioritize', $score['recommendation']);
        $this->assertGreaterThanOrEqual(8, $score['score']);
    }

    public function test_implementation_readiness_reports_warnings_and_blockers(): void
    {
        $this->candidate(risk: 'high');
        $this->assertSame('warning', app(FeatureImplementationReadinessService::class)->review()['status']);

        $this->candidate(risk: 'critical');
        $report = app(FeatureImplementationReadinessService::class)->review();

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('critical_risk_review', array_column($report['blockers'], 'name'));
    }

    public function test_release_planning_groups_quick_wins_high_risk_and_deferred(): void
    {
        $service = app(FeatureCandidateService::class);
        $quick = $service->accept($this->candidate(priority: 'high', effort: 'small', impact: 'critical', risk: 'low'));
        $service->accept($this->candidate(category: 'billing', priority: 'high', effort: 'large', impact: 'high', risk: 'high'));
        $service->defer($this->candidate(category: 'admin'));

        $report = app(V11ReleasePlanningService::class)->report();

        $this->assertSame(3, $report['candidate_summary']['total']);
        $this->assertSame(2, $report['candidate_summary']['accepted']);
        $this->assertSame(1, $report['candidate_summary']['deferred']);
        $this->assertSame($quick->id, $report['quick_wins'][0]['candidate_id']);
        $this->assertCount(1, $report['high_risk_items']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'v11_release_plan_generated']);
    }

    public function test_v11_command_outputs_safe_summary(): void
    {
        app(FeatureCandidateService::class)->accept($this->candidate());

        $this->artisan('system:v11-plan-status')
            ->expectsOutput('v1.1 release planning summary')
            ->expectsOutput('Candidates: 1')
            ->expectsOutput('Accepted: 1')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();
    }

    public function test_v11_reports_do_not_expose_sensitive_metadata(): void
    {
        app(FeatureCandidateService::class)->create([
            'title' => 'Safe roadmap item',
            'metadata' => [
                'secret' => 'provider-secret',
                'token' => 'hidden-token',
                'label' => 'public label',
            ],
        ]);

        $encoded = json_encode(app(V11ReleasePlanningService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'v11-roadmap')->get()->toJson();

        $this->assertStringNotContainsString('provider-secret', $encoded);
        $this->assertStringNotContainsString('hidden-token', $encoded);
        $this->assertStringNotContainsString('provider-secret', $events);
    }

    private function candidate(
        string $category = 'inbox',
        string $priority = 'medium',
        string $effort = 'small',
        string $impact = 'high',
        string $risk = 'low',
    ): FeatureCandidate {
        return app(FeatureCandidateService::class)->create([
            'title' => 'Inbox UX planning',
            'description' => 'Plan mailbox experience improvements.',
            'category' => $category,
            'priority' => $priority,
            'effort' => $effort,
            'impact' => $impact,
            'risk' => $risk,
        ]);
    }
}
