<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Content;
use App\Services\Service;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

final class ContentService extends Service
{
    public function __construct(private readonly ContentSlugService $slugs)
    {
    }

    public function create(array $data): Content
    {
        $type = $this->typeFrom($data['type'] ?? ContentType::Page);
        $status = $this->statusFrom($data['status'] ?? config('content.default_status', ContentStatus::Draft->value));

        if ($status !== ContentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'New content must start as draft.',
            ]);
        }

        $title = (string) ($data['title'] ?? '');

        if (trim($title) === '') {
            throw ValidationException::withMessages([
                'title' => 'The title field is required.',
            ]);
        }

        return Content::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'slug' => $data['slug'] ?? $this->slugs->generate($title, $data['locale'] ?? null),
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? null,
            'type' => $type,
            'status' => $status,
            'published_at' => null,
            'author_staff_id' => $data['author_staff_id'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'featured_media_id' => $data['featured_media_id'] ?? null,
            'locale' => $data['locale'] ?? null,
        ]);
    }

    public function publish(Content $content, ?\DateTimeInterface $publishedAt = null): Content
    {
        $this->ensureCanTransition($content, ContentStatus::Published);

        $content->forceFill([
            'status' => ContentStatus::Published,
            'published_at' => $publishedAt ?? now(),
        ])->save();

        return $content->refresh();
    }

    public function archive(Content $content): Content
    {
        $this->ensureCanTransition($content, ContentStatus::Archived);

        $content->forceFill([
            'status' => ContentStatus::Archived,
        ])->save();

        return $content->refresh();
    }

    public function ensureCanTransition(Content $content, ContentStatus $target): void
    {
        if ($content->status === ContentStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => 'Archived content cannot transition to another state.',
            ]);
        }

        if ($target === ContentStatus::Published && $content->status !== ContentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft content can be published.',
            ]);
        }

        if ($target === ContentStatus::Archived && ! in_array($content->status, [ContentStatus::Draft, ContentStatus::Published], true)) {
            throw ValidationException::withMessages([
                'status' => 'Content cannot be archived from its current state.',
            ]);
        }
    }

    private function typeFrom(ContentType|string $type): ContentType
    {
        return $type instanceof ContentType ? $type : ContentType::from($type);
    }

    private function statusFrom(ContentStatus|string $status): ContentStatus
    {
        return $status instanceof ContentStatus ? $status : ContentStatus::from($status);
    }
}
