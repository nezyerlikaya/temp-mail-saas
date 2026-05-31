@php
    $localeService = app(\App\Services\System\LocaleService::class);
    $languages = $localeService->activeLanguages();
    $currentLocale = app()->getLocale();
@endphp

@if (count($languages) > 1)
    <form method="POST" action="{{ route('locale.switch') }}" class="fixed bottom-4 right-4 z-20 rounded-2xl border border-white/10 bg-slate-900/90 p-3 shadow-xl shadow-slate-950/30">
        @csrf
        <label for="locale-switcher" class="sr-only">Language</label>
        <select id="locale-switcher" name="locale" onchange="this.form.submit()" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
            @foreach ($languages as $language)
                <option value="{{ $language['code'] }}" @selected($currentLocale === $language['code'])>
                    {{ $language['native_name'] }}
                </option>
            @endforeach
        </select>
    </form>
@endif
