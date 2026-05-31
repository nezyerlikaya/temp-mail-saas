<?php

namespace Tests\Feature;

use App\DTOs\Media\MediaData;
use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Models\Media;
use App\Models\StaffUser;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_table_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('media'));
        $this->assertTrue(Schema::hasColumns('media', [
            'uuid',
            'disk',
            'directory',
            'filename',
            'original_filename',
            'extension',
            'mime_type',
            'size',
            'checksum',
            'visibility',
            'width',
            'height',
            'uploaded_by_user_id',
            'uploaded_by_staff_id',
            'status',
            'storage_driver',
            'storage_path',
        ]));
    }

    public function test_uuid_generation_and_media_record_creation_work(): void
    {
        $media = app(MediaService::class)->create([
            'collection' => 'avatars',
            'original_filename' => 'Profile Photo.JPG',
            'mime_type' => 'image/jpeg',
            'size' => 12345,
            'width' => 640,
            'height' => 480,
            'checksum' => hash('sha256', 'fake'),
        ]);

        $this->assertTrue(Str::isUuid($media->uuid));
        $this->assertSame('avatars/'.now()->format('Y/m'), $media->directory);
        $this->assertSame('jpg', $media->extension);
        $this->assertSame(MediaStatus::Pending, $media->status);
        $this->assertSame(MediaVisibility::Private, $media->visibility);
        $this->assertSame($media->directory.'/'.$media->filename, $media->storage_path);
    }

    public function test_media_path_generation_uses_collection_and_year_month(): void
    {
        $timestamp = now()->setDate(2026, 5, 31)->timestamp;

        $this->assertSame('avatars/2026/05', app(MediaService::class)->generateDirectory('avatars', $timestamp));
        $this->assertSame('blog/2026/05', app(MediaService::class)->generateDirectory('blog', $timestamp));
        $this->assertSame('seo/2026/05', app(MediaService::class)->generateDirectory('seo', $timestamp));
        $this->assertSame('attachments/2026/05', app(MediaService::class)->generateDirectory('attachments', $timestamp));
    }

    public function test_image_detection_visibility_and_relationship_helpers_work(): void
    {
        $user = User::factory()->create();
        $staff = StaffUser::query()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
        ]);

        $media = app(MediaService::class)->create([
            'collection' => 'blog',
            'original_filename' => 'hero.png',
            'mime_type' => 'image/png',
            'size' => 100,
            'uploaded_by_user_id' => $user->id,
            'uploaded_by_staff_id' => $staff->id,
        ]);

        $this->assertTrue($media->isImage());
        $this->assertTrue($media->isPublic());
        $this->assertFalse($media->isPrivate());
        $this->assertTrue($media->uploadedByUser->is($user));
        $this->assertTrue($media->uploadedByStaff->is($staff));
    }

    public function test_media_dto_safe_output_does_not_expose_internal_paths(): void
    {
        $media = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'local',
            'directory' => 'attachments/2026/05',
            'filename' => 'sample.pdf',
            'original_filename' => 'sample.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'checksum' => 'abc123',
            'visibility' => MediaVisibility::Private,
            'status' => MediaStatus::Active,
            'storage_driver' => 'local',
            'storage_path' => 'attachments/2026/05/sample.pdf',
        ]);

        $data = MediaData::fromMedia($media)->toArray();

        $this->assertSame($media->uuid, $data['uuid']);
        $this->assertSame('sample.pdf', $data['filename']);
        $this->assertSame('application/pdf', $data['mime']);
        $this->assertSame(2048, $data['size']);
        $this->assertSame('private', $data['visibility']);
        $this->assertArrayNotHasKey('disk', $data);
        $this->assertArrayNotHasKey('directory', $data);
        $this->assertArrayNotHasKey('storage_path', $data);
        $this->assertArrayNotHasKey('checksum', $data);
    }
}
