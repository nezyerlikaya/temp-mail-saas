<?php

namespace App\Services\System;

use App\Models\Language;
use App\Models\Translation;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TranslationService extends Service
{
    public function __construct(private readonly LocaleService $locales)
    {
    }

    public function get(string $group, string $key, ?string $locale = null, ?string $default = null): string
    {
        $locale = $locale !== null && $this->locales->isValidLocale($locale)
            ? strtolower($locale)
            : app()->getLocale();

        $value = $this->databaseValue($group, $key, $locale)
            ?? $this->databaseValue($group, $key, $this->locales->fallbackLocale());

        if (filled($value)) {
            return (string) $value;
        }

        $translationKey = "{$group}.{$key}";
        $laravelValue = __($translationKey, [], $locale);

        if ($laravelValue !== $translationKey) {
            return $laravelValue;
        }

        return $default ?? $translationKey;
    }

    public function exists(string $group, string $key, ?string $locale = null): bool
    {
        return $this->databaseValue($group, $key, $locale ?? app()->getLocale()) !== null;
    }

    private function databaseValue(string $group, string $key, string $locale): ?string
    {
        try {
            if (! Schema::hasTable('languages') || ! Schema::hasTable('translations')) {
                return null;
            }

            $language = Language::query()
                ->where('code', $locale)
                ->where('is_active', true)
                ->first();

            if ($language === null) {
                return null;
            }

            return Translation::query()
                ->where('language_id', $language->id)
                ->where('group', $group)
                ->where('key', $key)
                ->value('value');
        } catch (Throwable) {
            return null;
        }
    }
}
