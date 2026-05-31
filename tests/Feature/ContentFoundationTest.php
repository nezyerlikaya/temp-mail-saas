<?php

namespace Tests\Feature;

use App\DTOs\Content\ContentData;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Models\Content;
use App\Models\Media;
use App\Models\StaffUser;
use App\Services\Content\ContentService;
use App\Services\Content\ContentSlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contents_table_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('contents'));
        $this->assertTrue(Schema::hasColumns('contents', [
            'uuid',
            'title',
            'slug',
            'excerpt',
            'content',
            'type',
            'status',
            'published_at',
            'author_staff_id',
            'meta_title',
            'meta_description',
            'featured_media_id',
            'locale',
        ]));
    }

    public function test_slug_generation_and_uniqueness_work(): void
    {
        $service = app(ContentSlugService::class);

        $this->assertSame('hello-world', $service->normalize(' Hello World! '));

        Content::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'type' => ContentType::Post,
            'status' => ContentStatus::Draft,
            'locale' => 'en',
        ]);

        $this->assertSame('hello-world-2', $service->generate('Hello World', 'en'));
        $this->assertSame('hello-world', $service->generate('Hello World', 'tr'));
    }

    public function test_status_helpers_and_relationships_work(): void
    {
        $staff = StaffUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => Hash::make('password'),
        ]);

        $media = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'local',
            'directory' => 'content/2026/05',
            'filename' => 'hero.jpg',
            'original_filename' => 'hero.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'visibility' => MediaVisibility::Public,
            'status' => MediaStatus::Active,
            'storage_driver' => 'local',
            'storage_path' => 'content/2026/05/hero.jpg',
        ]);

        $content = Content::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'About',
            'slug' => 'about',
            'type' => ContentType::Page,
            'status' => ContentStatus::Draft,
            'author_staff_id' => $staff->id,
            'featured_media_id' => $media->id,
        ]);

        $this->assertTrue($content->isDraft());
        $this->assertFalse($content->isPublished());
        $this->assertFalse($content->isArchived());
        $this->assertTrue($content->author->is($staff));
        $this->assertTrue($content->featuredMedia->is($media));
    }

    public function test_content_dto_safe_output_works(): void
    {
        $content = app(ContentService::class)->create([
            'title' => 'Welcome Page',
            'type' => ContentType::Page,
            'locale' => 'en',
            'meta_title' => 'Internal SEO Title',
        ]);

        $published = app(ContentService::class)->publish($content);
        $data = ContentData::fromContent($published)->toArray();

        $this->assertSame('Welcome Page', $data['title']);
        $this->assertSame('welcome-page', $data['slug']);
        $this->assertSame('published', $data['status']);
        $this->assertSame('page', $data['type']);
        $this->assertNotNull($data['published_at']);
        $this->assertArrayNotHasKey('meta_title', $data);
        $this->assertArrayNotHasKey('author_staff_id', $data);
        $this->assertArrayNotHasKey('featured_media_id', $data);
    }

    public function test_content_service_transitions_work(): void
    {
        $content = app(ContentService::class)->create([
            'title' => 'Announcement',
            'type' => ContentType::Announcement,
        ]);

        $this->assertTrue($content->isDraft());

        $published = app(ContentService::class)->publish($content);
        $this->assertTrue($published->isPublished());
        $this->assertNotNull($published->published_at);

        $archived = app(ContentService::class)->archive($published);
        $this->assertTrue($archived->isArchived());

        $this->expectException(ValidationException::class);
        app(ContentService::class)->publish($archived);
    }
}
