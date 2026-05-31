<?php

namespace App\Services\System;

use App\Models\Language;
use App\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LocaleService extends Service
{
    public function defaultLocale(): string
    {
        return $this->configuredDefaultLocale();
    }

    public function fallbackLocale(): string
    {
        return $this->configuredFallbackLocale();
    }

    public function sessionKey(): string
    {
        return (string) config('tempmail.localization.session_key', 'locale');
    }

    public function activeLocaleCodes(): array
    {
        try {
            if (! Schema::hasTable('languages')) {
                return $this->configuredSupportedLocales();
            }

            $locales = Language::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('code')
                ->all();

            return $locales !== [] ? $locales : $this->configuredSupportedLocales();
        } catch (Throwable) {
            return $this->configuredSupportedLocales();
        }
    }

    public function activeLanguages(): array
    {
        try {
            if (! Schema::hasTable('languages')) {
                return $this->configuredLanguageFallbacks();
            }

            $languages = Language::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['code', 'name', 'native_name', 'direction'])
                ->map(fn (Language $language): array => [
                    'code' => $language->code,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                    'direction' => $language->direction->value,
                ])
                ->all();

            return $languages !== [] ? $languages : $this->configuredLanguageFallbacks();
        } catch (Throwable) {
            return $this->configuredLanguageFallbacks();
        }
    }

    public function isValidLocale(?string $locale): bool
    {
        return is_string($locale)
            && preg_match('/^[a-z]{2}(?:[-_][A-Za-z]{2})?$/', $locale) === 1
            && in_array($this->normalize($locale), $this->activeLocaleCodes(), true);
    }

    public function determineLocale(?Request $request = null): string
    {
        $request ??= request();

        $candidates = [
            $request->user()?->locale,
            $request->session()->get($this->sessionKey()),
            $request->input('locale'),
            $request->query('locale'),
            $this->defaultLocale(),
            $this->fallbackLocale(),
        ];

        foreach ($candidates as $candidate) {
            if ($this->isValidLocale($candidate)) {
                return $this->normalize((string) $candidate);
            }
        }

        return $this->fallbackLocale();
    }

    public function setApplicationLocale(string $locale): string
    {
        $locale = $this->isValidLocale($locale)
            ? $this->normalize($locale)
            : $this->fallbackLocale();

        App::setLocale($locale);

        return $locale;
    }

    public function directionFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        try {
            $language = Language::query()->where('code', $locale)->first();

            return $language?->direction->value ?? 'ltr';
        } catch (Throwable) {
            return 'ltr';
        }
    }

    public function storeLocaleInSession(Request $request, string $locale): bool
    {
        if (! $this->isValidLocale($locale)) {
            return false;
        }

        $request->session()->put($this->sessionKey(), $this->normalize($locale));

        return true;
    }

    private function normalize(mixed $locale): string
    {
        return strtolower(str_replace('_', '-', trim((string) $locale)));
    }

    private function configuredDefaultLocale(): string
    {
        return $this->normalize((string) config('tempmail.localization.default_locale', config('app.locale', 'en')));
    }

    private function configuredFallbackLocale(): string
    {
        return $this->normalize((string) config('tempmail.localization.fallback_locale', config('app.fallback_locale', 'en')));
    }

    private function configuredSupportedLocales(): array
    {
        $locales = config('tempmail.localization.supported_locales', ['en']);
        $locales = is_array($locales) ? $locales : ['en'];
        $locales[] = $this->configuredDefaultLocale();
        $locales[] = $this->configuredFallbackLocale();

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $locale): string => $this->normalize($locale),
            $locales,
        ), fn (string $locale): bool => preg_match('/^[a-z]{2}(?:[-_][a-z]{2})?$/', $locale) === 1)));
    }

    private function configuredLanguageFallbacks(): array
    {
        return array_map(fn (string $locale): array => [
            'code' => $locale,
            'name' => strtoupper($locale),
            'native_name' => strtoupper($locale),
            'direction' => 'ltr',
        ], $this->configuredSupportedLocales());
    }
}
