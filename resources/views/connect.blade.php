<x-layouts.app title="Connect your assistant — ReviseMy" robots="noindex, nofollow">
    <div class="rm-desk flex min-h-svh flex-col">
        <main class="rm-shell items-center justify-center px-6 py-16">
            <div class="w-full max-w-sm">
                <x-revisemy-logo size="lg" />

                @if ($client)
                    <h1 class="mt-6 text-2xl font-semibold text-foreground">Connect {{ $client->name }}</h1>
                    <p class="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                        {{ $client->name }} will be able to create reviews and read your marks. No account — Connect makes a try workspace that’s yours.
                    </p>

                    <form method="POST" action="{{ route('connect') }}" class="mt-8 space-y-6" x-data="{ existing: {{ $errors->has('token') ? 'true' : 'false' }} }">
                        @csrf

                        <div class="rounded-2xl bg-card p-4">
                            <button type="button" class="flex w-full items-center justify-between text-left text-sm font-medium text-foreground" x-on:click="existing = ! existing" x-bind:aria-expanded="existing.toString()">
                                Use a try token you already have
                                <flux:icon.chevron-down variant="micro" class="size-4 text-zinc-400 transition" x-bind:class="existing && 'rotate-180'" />
                            </button>
                            <div x-show="existing" x-cloak class="mt-3 space-y-2">
                                <input
                                    type="password"
                                    name="token"
                                    autocomplete="off"
                                    placeholder="Paste your try token"
                                    class="w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-sm text-foreground outline-none placeholder:text-zinc-400 focus:border-zinc-400"
                                >
                                <p class="text-xs text-muted-foreground">Your reviews from that token show up here too.</p>
                            </div>
                            @error('token')
                                <p class="mt-2 text-sm text-problem-ink">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-3">
                            <button type="submit" class="btn-lit inline-flex h-10 w-full items-center justify-center rounded-full text-sm font-medium">Connect</button>
                            @if ($returnsTo)
                                <p class="text-center text-xs text-muted-foreground">Returns you to <span class="font-medium text-zinc-700">{{ $returnsTo }}</span></p>
                            @endif
                        </div>
                    </form>
                @else
                    <h1 class="mt-6 text-2xl font-semibold text-foreground">Connect from your assistant</h1>
                    <p class="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                        Add <span class="font-mono text-zinc-700">{{ url('/mcp/revisemy') }}</span> as a custom connector in Claude or ChatGPT. It sends you back here to connect.
                    </p>
                    <p class="mt-8 text-sm text-zinc-600">Cursor or VS Code? Install it here and sign in the same way:</p>
                    <x-install-links class="mt-3" />
                    <a href="/#setup" class="mt-6 inline-flex text-sm font-medium text-zinc-600 underline decoration-zinc-300 underline-offset-2 hover:text-foreground">See every way to connect</a>
                @endif
            </div>
        </main>
    </div>
</x-layouts.app>
