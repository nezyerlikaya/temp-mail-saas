@props(['headers' => []])

<div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900/70">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-white/10">
            <thead class="bg-white/[0.03]">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
