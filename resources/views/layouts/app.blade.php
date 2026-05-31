<!DOCTYPE html>
@php($seoMeta = app(\App\Services\Seo\SeoService::class)->meta(['title' => $title ?? null]))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app(\App\Services\System\LocaleService::class)->directionFor() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $seoMeta->description }}">
        <meta name="robots" content="{{ $seoMeta->robots }}">
        <link rel="canonical" href="{{ $seoMeta->canonical_url }}">

        <meta property="og:title" content="{{ $seoMeta->og_title }}">
        <meta property="og:description" content="{{ $seoMeta->og_description }}">
        <meta property="og:url" content="{{ $seoMeta->canonical_url }}">
        <meta property="og:type" content="{{ config('seo.open_graph.type', 'website') }}">
        @if ($seoMeta->og_image)
            <meta property="og:image" content="{{ $seoMeta->og_image }}">
        @endif
        <meta name="twitter:card" content="{{ config('seo.twitter.card', 'summary_large_image') }}">
        <meta name="twitter:title" content="{{ $seoMeta->twitter_title }}">
        <meta name="twitter:description" content="{{ $seoMeta->twitter_description }}">
        @if ($seoMeta->twitter_image)
            <meta name="twitter:image" content="{{ $seoMeta->twitter_image }}">
        @endif

        <title>{{ $seoMeta->title }}</title>

        @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        @endif
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <main>
            @yield('content')
        </main>
        <x-locale-switcher />
    </body>
</html>
