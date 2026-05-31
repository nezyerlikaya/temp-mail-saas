@props(['title', 'value' => null, 'tone' => 'slate'])

@php
    $tones = [
        'green' => 'text-emerald-300',
        'amber' => 'text-amber-300',
        'red' => 'text-red-300',
        'cyan' => 'text-cyan-300',
        'slate' => 'text-white',
    ];
@endphp

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-white/10 bg-slate-900/70 p-5']) }}>
    <h2 class="text-sm font-medium text-slate-400">{{ $title }}</h2>
    @if ($value !== null)
        <div class="mt-2 text-3xl font-bold {{ $tones[$tone] ?? $tones['slate'] }}">{{ $value }}</div>
    @endif
    <div class="mt-3 text-sm text-slate-300">{{ $slot }}</div>
</section>
