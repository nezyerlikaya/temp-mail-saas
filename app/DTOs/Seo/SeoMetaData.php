<?php

namespace App\DTOs\Seo;

use App\DTOs\DataTransferObject;

final readonly class SeoMetaData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical_url,
        public string $robots,
        public string $og_title,
        public string $og_description,
        public ?string $og_image,
        public string $twitter_title,
        public string $twitter_description,
        public ?string $twitter_image,
    ) {}
}
