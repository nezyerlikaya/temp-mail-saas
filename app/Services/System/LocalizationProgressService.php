<?php

namespace App\Services\System;

use App\Models\Language;
use App\Models\Translation;
use App\Services\Service;
use Illuminate\Support\Collection;

final class LocalizationProgressService extends Service
{
    public function progressFor(Language $language): array
    {
        $default = Language::query()->where('is_default', true)->first();

        if ($default === null) {
            return [
                'total' => 0,
                'completed' => 0,
                'missing' => [],
                'untranslated' => [],
                'percent' => 100,
            ];
        }

        $defaultKeys = $this->keysFor($default);
        $languageTranslations = Translation::query()
            ->where('language_id', $language->id)
            ->get()
            ->keyBy(fn (Translation $translation): string => $translation->group.'.'.$translation->key);

        $missing = [];
        $untranslated = [];
        $completed = 0;

        foreach ($defaultKeys as $key => $defaultValue) {
            $translation = $languageTranslations->get($key);

            if ($translation === null) {
                $missing[] = $key;
                continue;
            }

            if (blank($translation->value) || ($language->id !== $default->id && $translation->value === $defaultValue)) {
                $untranslated[] = $key;
                continue;
            }

            $completed++;
        }

        $total = $defaultKeys->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'missing' => $missing,
            'untranslated' => $untranslated,
            'percent' => $total === 0 ? 100 : (int) round(($completed / $total) * 100),
        ];
    }

    public function all(): array
    {
        return Language::query()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Language $language): array => [
                $language->id => $this->progressFor($language),
            ])
            ->all();
    }

    private function keysFor(Language $language): Collection
    {
        return Translation::query()
            ->where('language_id', $language->id)
            ->get(['group', 'key', 'value'])
            ->mapWithKeys(fn (Translation $translation): array => [
                $translation->group.'.'.$translation->key => $translation->value,
            ]);
    }
}
