@extends('layouts.app', ['title' => 'Verify email - '.config('tempmail.public_name')])

@section('content')
    <section class="min-h-screen px-6 py-16">
        <div class="mx-auto max-w-md rounded-3xl border border-white/10 bg-white/[0.04] p-8">
            <h1 class="text-3xl font-bold text-white">Verify your email</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">Check your inbox for a verification link. Email delivery can be configured later for each environment.</p>
            @include('auth.partials.status')
            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                @csrf
                <button class="rounded-xl bg-cyan-300 px-4 py-3 font-semibold text-slate-950 hover:bg-cyan-200 focus:ring-2 focus:ring-cyan-300">Resend verification email</button>
            </form>
        </div>
    </section>
@endsection
