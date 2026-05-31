@if (session('status'))
    <div class="mb-6 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
        {{ session('status') }}
    </div>
@endif

@if ($errors->has('form'))
    <div class="mb-6 rounded-xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200">
        {{ $errors->first('form') }}
    </div>
@endif
