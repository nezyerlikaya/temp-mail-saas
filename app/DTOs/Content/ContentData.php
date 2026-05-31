<?php

namespace App\DTOs\Content;

use App\DTOs\DataTransferObject;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Content;
use Illuminate\Support\Carbon;

final readonly class ContentData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public string $slug,
        public ContentStatus $status,
        public ContentType $type,
        public ?Carbon $publishedAt,
    ) {
    }

    public static function fromContent(Content $content): self
    {
        return new self(
            title: $content->title,
            slug: $content->slug,
            status: $content->status,
            type: $content->type,
            publishedAt: $content->published_at,
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'published_at' => $this->publishedAt?->toISOString(),
        ];
    }
}
