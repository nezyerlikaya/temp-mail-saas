<?php

namespace App\DTOs\Media;

use App\DTOs\DataTransferObject;
use App\Enums\MediaVisibility;
use App\Models\Media;

final readonly class MediaData extends DataTransferObject
{
    public function __construct(
        public string $uuid,
        public string $filename,
        public string $mime,
        public int $size,
        public MediaVisibility $visibility,
    ) {
    }

    public static function fromMedia(Media $media): self
    {
        return new self(
            uuid: $media->uuid,
            filename: $media->filename,
            mime: $media->mime_type,
            size: $media->size,
            visibility: $media->visibility,
        );
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'visibility' => $this->visibility->value,
        ];
    }
}
