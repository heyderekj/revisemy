{{-- A sign-in link ReviseMy can't use (App\Http\Middleware\RenderAuthorizeError). --}}
@php($who = $assistant ?? 'your assistant')
<x-layouts.app title="This sign-in link doesn’t work — ReviseMy" description="The sign-in link your assistant opened can’t be used. Remove ReviseMy and add it again." robots="noindex, nofollow">
    <div class="rm-desk flex min-h-svh flex-col">
        <main class="rm-shell items-center justify-center px-6 py-16">
            <div class="w-full max-w-lg">
                <x-revisemy-logo size="lg" />
                <h1 class="mt-6 text-2xl font-semibold text-foreground">This sign-in link doesn’t work</h1>
                <p class="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                    @if ($error === 'invalid_client')
                        {{ ucfirst($who) }} opened a sign-in ReviseMy doesn’t recognise. It happens when ReviseMy was removed and its old sign-in is reused, or when it was added with a different address.
                    @else
                        {{ ucfirst($who) }} opened a sign-in that’s missing something ReviseMy needs, so it can’t finish here.
                    @endif
                </p>

                <ol class="mt-6 space-y-3 rounded-2xl bg-card p-5 text-sm text-zinc-700">
                    <li class="flex gap-3"><span class="font-medium text-foreground">1</span><span>In {{ $who }}, remove ReviseMy from your connectors.</span></li>
                    <li class="flex gap-3"><span class="font-medium text-foreground">2</span><span>Add it again with <span class="font-mono text-[13px] text-foreground">{{ $mcpUrl }}</span></span></li>
                    <li class="flex gap-3"><span class="font-medium text-foreground">3</span><span>When ReviseMy opens, click Connect.</span></li>
                </ol>

                <div class="mt-6 flex flex-col gap-3">
                    <a href="{{ url('/connect') }}" class="btn-lit inline-flex h-10 w-full items-center justify-center rounded-full text-sm font-medium">See how to connect each assistant</a>
                    <p class="text-center text-xs text-muted-foreground">If it keeps happening, tell us the code <span class="font-mono">{{ $error }}</span>.</p>
                </div>
            </div>
        </main>
    </div>
</x-layouts.app>
