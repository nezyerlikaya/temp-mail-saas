<?php

namespace App\Services\Seo;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Services\Service;
use Illuminate\Support\Collection;

final class SitemapService extends Service
{
    public function entries(): Collection
    {
        $static = collect(config('seo.sitemap.static_pages', []))
            ->map(fn (string $route): ?array => $this->staticEntry($route))
            ->filter()
            ->values();

        $content = Content::query()
            ->where('status', ContentStatus::Published)
            ->orderBy('updated_at')
            ->get()
            ->map(fn (Content $content): array => [
                'loc' => url('/content/'.$content->slug),
                'lastmod' => $content->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]);

        return $static->merge($content)->values();
    }

    public function xml(): string
    {
        $entries = $this->entries();

        return view('seo.sitemap', ['entries' => $entries])->render();
    }

    private function staticEntry(string $route): ?array
    {
        if (! app('router')->has($route)) {
            return null;
        }

        return [
            'loc' => route($route),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority' => $route === 'home' ? '1.0' : '0.7',
        ];
    }
}
