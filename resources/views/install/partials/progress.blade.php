@php
    $steps = [
        'installer.index' => 'Welcome',
        'installer.requirements' => 'Requirements',
        'installer.environment' => 'Environment',
        'installer.database' => 'Database',
        'installer.finish' => 'Finish',
    ];
@endphp

<nav aria-label="Installation progress" class="grid gap-2 sm:grid-cols-5">
    @foreach ($steps as $route => $label)
        @php($active = request()->routeIs($route))
        <a href="{{ route($route) }}" class="rounded-lg border px-3 py-2 text-center text-xs font-semibold {{ $active ? 'border-cyan-300 bg-cyan-300 text-slate-950' : 'border-white/10 bg-white/[0.04] text-slate-300 hover:border-cyan-300/50' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
