<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Content;
use App\Services\Content\ContentSlugService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $slugs = app(ContentSlugService::class);

        foreach (['Welcome Page', 'About Page'] as $title) {
            $slug = $slugs->normalize($title);

            Content::query()->updateOrCreate(
                [
                    'slug' => $slug,
                    'locale' => config('tempmail.localization.default_locale', 'en'),
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => $title,
                    'excerpt' => null,
                    'content' => null,
                    'type' => ContentType::Page,
                    'status' => ContentStatus::Draft,
                    'published_at' => null,
                    'meta_title' => $title,
                    'meta_description' => null,
                    'featured_media_id' => null,
                ],
            );
        }
    }
}
