<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Content;
use App\Models\SeoSetting;
use App\Services\Seo\RobotsService;
use App\Services\Seo\SeoService;
use App\Services\Seo\SitemapService;
use App\Services\Seo\StructuredDataService;
use Database\Seeders\SeoSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoFoundationExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_settings_migration_and_model_helper_work(): void
    {
        $this->assertTrue(Schema::hasTable('seo_settings'));
        $this->assertTrue(Schema::hasColumns('seo_settings', [
            'key',
            'value',
            'group',
            'is_public',
        ]));

        $setting = SeoSetting::query()->create([
            'key' => 'test_setting',
            'value' => 'Visible',
            'group' => 'testing',
            'is_public' => true,
        ]);

        $this->assertTrue($setting->isPublic());
    }

    public function test_seo_setting_seeder_is_idempotent(): void
    {
        $this->seed(SeoSettingSeeder::class);
        $this->seed(SeoSettingSeeder::class);

        $this->assertSame(1, SeoSetting::query()->where('key', 'default_title')->count());
    }

    public function test_seo_service_returns_defaults(): void
    {
        $meta = app(SeoService::class)->meta(request: Request::create('/status?utm=ignored'));

        $this->assertSame(config('seo.title'), $meta->title);
        $this->assertSame(config('seo.description'), $meta->description);
        $this->assertSame(url('/status'), $meta->canonical_url);
        $this->assertSame($meta->title, $meta->og_title);
        $this->assertSame($meta->description, $meta->twitter_description);
    }

    public function test_seo_service_merges_content_values(): void
    {
        $content = Content::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Content Title',
            'slug' => 'content-title',
            'excerpt' => 'Content excerpt.',
            'type' => ContentType::Page,
            'status' => ContentStatus::Published,
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta description.',
        ]);

        $meta = app(SeoService::class)->forContent($content, [
            'canonical_url' => url('/pages/content-title'),
        ]);

        $this->assertSame('Meta Title', $meta->title);
        $this->assertSame('Meta description.', $meta->description);
        $this->assertSame(url('/pages/content-title'), $meta->canonical_url);
    }

    public function test_content_seo_fallbacks_work(): void
    {
        $content = Content::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Fallback Title',
            'slug' => 'fallback-title',
            'excerpt' => 'Fallback excerpt.',
            'type' => ContentType::Post,
            'status' => ContentStatus::Draft,
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->assertSame('Fallback Title', $content->seoTitle());
        $this->assertSame('Fallback excerpt.', $content->seoDescription());
    }

    public function test_sitemap_route_and_service_work(): void
    {
        Content::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Published Page',
            'slug' => 'published-page',
            'type' => ContentType::Page,
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);

        $entries = app(SitemapService::class)->entries();

        $this->assertTrue($entries->contains(fn (array $entry): bool => $entry['loc'] === url('/content/published-page')));

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<urlset', false)
            ->assertSee(url('/content/published-page'));
    }

    public function test_robots_route_and_service_work(): void
    {
        $content = app(RobotsService::class)->content();

        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $content);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *');
    }

    public function test_structured_data_generation_works(): void
    {
        $content = Content::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Article Title',
            'slug' => 'article-title',
            'excerpt' => 'Article excerpt.',
            'type' => ContentType::Post,
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
        $structured = app(StructuredDataService::class);

        $this->assertSame('WebSite', $structured->website()['@type']);
        $this->assertSame('Organization', $structured->organization()['@type']);
        $this->assertSame('Article', $structured->article($content)['@type']);
        $this->assertSame('Article Title', $structured->article($content)['headline']);
    }

    public function test_layout_outputs_centralized_meta_tags(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false);
    }

    public function test_existing_routes_still_work(): void
    {
        $this->get('/inbox')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
        $this->get('/admin')->assertForbidden();
    }
}
