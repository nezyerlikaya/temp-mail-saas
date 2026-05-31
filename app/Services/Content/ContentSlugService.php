<?php

namespace App\Services\Content;

use App\Models\Content;
use App\Services\Service;
use Illuminate\Support\Str;

final class ContentSlugService extends Service
{
    public function generate(string $title, ?string $locale = null, ?int $ignoreContentId = null): string
    {
        $base = $this->normalize($title);
        $slug = $base;
        $counter = 2;

        while (! $this->isUnique($slug, $locale, $ignoreContentId)) {
            $slug = $this->trimToMaxLength($base.'-'.$counter);
            $counter++;
        }

        return $slug;
    }

    public function normalize(string $value): string
    {
        $separator = $this->separator();
        $slug = Str::slug($value, $separator);

        return $this->trimToMaxLength($slug !== '' ? $slug : 'content');
    }

    public function isUnique(string $slug, ?string $locale = null, ?int $ignoreContentId = null): bool
    {
        return ! Content::query()
            ->where('slug', $slug)
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->when($ignoreContentId !== null, fn ($query) => $query->whereKeyNot($ignoreContentId))
            ->exists();
    }

    private function separator(): string
    {
        $separator = (string) config('content.slug.separator', '-');

        return $separator !== '' ? $separator : '-';
    }

    private function trimToMaxLength(string $slug): string
    {
        return Str::limit($slug, (int) config('content.slug.max_length', 160), '');
    }
}
