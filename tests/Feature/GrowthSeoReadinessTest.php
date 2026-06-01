<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Seo\ContentGrowthReadinessService;
use App\Services\Seo\GrowthCertificationService;
use App\Services\Seo\IndexingReadinessService;
use App\Services\Seo\LandingPageReadinessService;
use App\Services\Seo\SeoGrowthReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthSeoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_growth_readiness_service_reports_ready_state(): void
    {
        $report = app(SeoGrowthReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame('certified', $report['certification']['status']);
        $this->assertArrayHasKey('seo', $report['sections']);
        $this->assertArrayHasKey('content', $report['sections']);
        $this->assertArrayHasKey('landing', $report['sections']);
        $this->assertArrayHasKey('indexing', $report['sections']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'growth_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'growth_review_ready']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'growth_certified']);
    }

    public function test_content_growth_review_can_warn_without_blocking(): void
    {
        config(['seo.growth_readiness.content.tag_ready' => false]);

        $report = app(ContentGrowthReadinessService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('tag_readiness', array_column($report['warnings'], 'name'));
    }

    public function test_landing_page_review_blocks_when_homepage_route_is_missing(): void
    {
        config(['seo.growth_readiness.landing_pages.homepage_route' => 'missing.home']);

        $report = app(LandingPageReadinessService::class)->review();

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('homepage_seo', array_column($report['blockers'], 'name'));
    }

    public function test_indexing_review_can_warn_on_crawl_readiness(): void
    {
        config(['seo.growth_readiness.indexing.crawl_ready' => false]);

        $report = app(IndexingReadinessService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('crawl_readiness', array_column($report['warnings'], 'name'));
    }

    public function test_growth_certification_blocks_when_seo_is_blocked(): void
    {
        $seo = ['blockers' => [['name' => 'sitemap_readiness', 'message' => 'Sitemap missing.']]];

        $report = app(GrowthCertificationService::class)->certify($seo);

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('seo_readiness', array_column($report['blockers'], 'name'));
    }

    public function test_growth_command_outputs_safe_summary(): void
    {
        $this->artisan('system:growth-status')
            ->expectsOutput('Growth readiness: READY')
            ->expectsOutput('Certification: CERTIFIED')
            ->doesntExpectOutputToContain('api_key')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();
    }

    public function test_growth_command_fails_when_sitemap_is_disabled(): void
    {
        config(['seo.sitemap.enabled' => false]);

        $this->artisan('system:growth-status')
            ->expectsOutput('Growth readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: seo.sitemap_readiness')
            ->doesntExpectOutputToContain('secret')
            ->assertFailed();
    }

    public function test_growth_observability_events_and_output_stay_safe(): void
    {
        $report = app(SeoGrowthReadinessService::class)->report();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertTrue(OperationsEvent::query()->where('event_type', 'growth_review_ready')->exists());
        $this->assertStringNotContainsString('SEO spam', $encoded);
        $this->assertStringNotContainsString('search_console_secret', $encoded);
    }
}
