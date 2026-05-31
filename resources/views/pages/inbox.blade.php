@extends('layouts.app', ['title' => 'Inbox - '.config('tempmail.name')])

@section('content')
    <section
        class="min-h-screen px-4 py-8 sm:px-6 lg:px-8"
        x-data="publicInbox({
            mailbox: @js($mailbox),
            messagesUrl: @js(route('inbox.messages')),
            interval: @js($pollingInterval),
        })"
        x-init="start()"
    >
        <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[360px_1fr]">
            <aside class="space-y-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300">Temp Mail SaaS</p>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-white">Public Inbox</h1>
                </div>

                <div class="rounded-lg border border-white/10 bg-white/[0.04] p-5 shadow-2xl shadow-cyan-950/30">
                    <div class="text-sm text-slate-400">Current mailbox</div>
                    @if ($mailbox)
                        <div class="mt-3 break-all rounded-lg border border-cyan-300/30 bg-cyan-300/10 p-3 font-mono text-sm text-cyan-100" x-text="mailbox"></div>
                    @else
                        <div class="mt-3 rounded-lg border border-amber-300/30 bg-amber-300/10 p-3 text-sm text-amber-100">
                            No mailbox generated yet.
                        </div>
                    @endif

                    <div class="mt-4 grid gap-2">
                        @if ($mailbox)
                            <button type="button" class="rounded-lg bg-cyan-300 px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-200" x-on:click="copyMailbox">
                                Copy address
                            </button>
                            <form method="POST" action="{{ route('inbox.rotate') }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border border-white/10 px-4 py-3 text-sm font-semibold text-slate-100 hover:border-cyan-300/50">
                                    Change address
                                </button>
                            </form>
                            <form method="POST" action="{{ route('inbox.forget') }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border border-rose-300/30 px-4 py-3 text-sm font-semibold text-rose-100 hover:border-rose-300/60">
                                    Reset inbox
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('inbox.generate') }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-cyan-300 px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-200">
                                    Generate mailbox
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </aside>

            <main class="rounded-lg border border-white/10 bg-white/[0.04] p-5 shadow-2xl shadow-cyan-950/30">
                <div class="flex flex-col gap-3 border-b border-white/10 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Messages</h2>
                        <p class="mt-1 text-sm text-slate-400" x-text="statusText"></p>
                    </div>
                    <button type="button" class="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-cyan-300/50" x-on:click="refresh" :disabled="loading">
                        Refresh
                    </button>
                </div>

                <div class="mt-5" aria-live="polite">
                    <template x-if="error">
                        <div class="rounded-lg border border-amber-300/30 bg-amber-300/10 p-4 text-sm text-amber-100" x-text="error"></div>
                    </template>

                    <template x-if="loading && messages.length === 0">
                        <div class="rounded-lg border border-white/10 bg-slate-900/70 p-6 text-center text-sm text-slate-300">
                            Loading messages...
                        </div>
                    </template>

                    <template x-if="!loading && messages.length === 0">
                        <div class="rounded-lg border border-white/10 bg-slate-900/70 p-8 text-center">
                            <div class="text-lg font-semibold text-white">Inbox is empty</div>
                            <p class="mt-2 text-sm text-slate-400">Messages for the current session mailbox will appear here.</p>
                        </div>
                    </template>

                    <div class="grid gap-3">
                        <template x-for="message in messages" :key="message.uuid">
                            <button type="button" class="rounded-lg border border-white/10 bg-slate-900/70 p-4 text-left hover:border-cyan-300/50" x-on:click="selectMessage(message)">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-white" x-text="message.subject || '(No subject)'"></div>
                                        <div class="mt-1 text-sm text-slate-400" x-text="message.from_name || message.from_email || 'Unknown sender'"></div>
                                    </div>
                                    <div class="shrink-0 text-xs text-slate-500" x-text="formatDate(message.received_at)"></div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-white/10 bg-slate-950/60 p-5" x-show="selected" x-cloak>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white" x-text="selected?.subject || '(No subject)'"></h3>
                            <p class="mt-1 text-sm text-slate-400" x-text="selected?.from_email"></p>
                        </div>
                        <button type="button" class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" x-on:click="selected = null">Close</button>
                    </div>
                    <div class="mt-4 whitespace-pre-wrap rounded-lg bg-white p-4 text-sm leading-6 text-slate-950" x-text="selected?.sanitized_html_body ? stripTags(selected.sanitized_html_body) : (selected?.text_body || '')"></div>
                </div>
            </main>
        </div>
    </section>

    <script>
        function publicInbox(options) {
            return {
                mailbox: options.mailbox,
                messagesUrl: options.messagesUrl,
                interval: options.interval,
                messages: [],
                selected: null,
                loading: false,
                error: null,
                timer: null,
                get statusText() {
                    if (!this.mailbox) return 'Generate a mailbox to start receiving messages.';
                    if (this.loading) return 'Checking for new messages...';
                    return 'Polling-ready list for your current mailbox.';
                },
                start() {
                    if (!this.mailbox) return;
                    this.refresh();
                    this.timer = setInterval(() => this.refresh(), this.interval);
                },
                async refresh() {
                    if (!this.mailbox || this.loading) return;
                    this.loading = true;
                    this.error = null;
                    try {
                        const response = await fetch(this.messagesUrl, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) throw new Error('Messages could not be refreshed.');
                        const payload = await response.json();
                        this.messages = payload.messages || [];
                    } catch (error) {
                        this.error = error.message || 'Messages could not be refreshed.';
                    } finally {
                        this.loading = false;
                    }
                },
                async selectMessage(message) {
                    this.error = null;
                    try {
                        const response = await fetch(`${this.messagesUrl}/${message.uuid}`, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) throw new Error('Message could not be opened.');
                        const payload = await response.json();
                        this.selected = payload.message;
                    } catch (error) {
                        this.error = error.message || 'Message could not be opened.';
                    }
                },
                async copyMailbox() {
                    if (!this.mailbox || !navigator.clipboard) return;
                    await navigator.clipboard.writeText(this.mailbox);
                },
                formatDate(value) {
                    if (!value) return '';
                    return new Date(value).toLocaleString();
                },
                stripTags(value) {
                    const element = document.createElement('div');
                    element.innerHTML = value || '';
                    return element.textContent || element.innerText || '';
                },
            };
        }
    </script>
@endsection
