<?php

namespace App\Services\Seo;

use App\Models\Content;
use App\Services\Service;

final class StructuredDataService extends Service
{
    public function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('seo.site_name', config('app.name')),
            'url' => url('/'),
        ];
    }

    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('seo.structured_data.organization_name', config('app.name')),
            'url' => config('seo.structured_data.organization_url', url('/')),
        ];
    }

    public function article(Content $content): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $content->seoTitle(),
            'description' => $content->seoDescription(),
            'datePublished' => $content->published_at?->toAtomString(),
            'dateModified' => $content->updated_at?->toAtomString(),
        ];
    }
}
