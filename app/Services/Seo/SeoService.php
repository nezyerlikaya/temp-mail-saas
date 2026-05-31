<?php

namespace App\Services\Seo;

use App\DTOs\Seo\SeoMetaData;
use App\Models\Content;
use App\Models\SeoSetting;
use App\Services\Service;
use Illuminate\Http\Request;
use Throwable;

final class SeoService extends Service
{
    public function meta(array $values = [], ?Content $content = null, ?Request $request = null): SeoMetaData
    {
        $request ??= request();
        $defaults = $this->defaults();
        $contentValues = $content !== null ? [
            'title' => $content->seoTitle(),
            'description' => $content->seoDescription(),
        ] : [];
        $merged = array_filter([
            ...$defaults,
            ...$contentValues,
            ...$values,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
        $canonical = (string) ($merged['canonical_url'] ?? $this->canonicalUrl($request));
        $title = (string) ($merged['title'] ?? config('seo.title'));
        $description = (string) ($merged['description'] ?? config('seo.description'));

        return new SeoMetaData(
            title: $title,
            description: $description,
            canonical_url: $canonical,
            robots: (string) ($merged['robots'] ?? config('seo.robots', 'index,follow')),
            og_title: (string) ($merged['og_title'] ?? $title),
            og_description: (string) ($merged['og_description'] ?? $description),
            og_image: $merged['og_image'] ?? null,
            twitter_title: (string) ($merged['twitter_title'] ?? $title),
            twitter_description: (string) ($merged['twitter_description'] ?? $description),
            twitter_image: $merged['twitter_image'] ?? $merged['og_image'] ?? null,
        );
    }

    public function forContent(Content $content, array $values = [], ?Request $request = null): SeoMetaData
    {
        return $this->meta($values, $content, $request);
    }

    public function canonicalUrl(?Request $request = null): string
    {
        $request ??= request();

        return strtok($request->fullUrl(), '?') ?: url('/');
    }

    public function robots(?string $value = null): string
    {
        return $value ?: (string) $this->setting('default_robots', config('seo.robots', 'index,follow'));
    }

    private function defaults(): array
    {
        return [
            'title' => $this->setting('default_title', config('seo.defaults.title')),
            'description' => $this->setting('default_description', config('seo.defaults.description')),
            'robots' => $this->robots(),
            'og_image' => config('seo.defaults.og_image'),
            'twitter_image' => config('seo.defaults.twitter_image'),
        ];
    }

    private function setting(string $key, mixed $fallback): mixed
    {
        try {
            return SeoSetting::query()
                ->where('key', $key)
                ->where('is_public', true)
                ->value('value') ?? $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
