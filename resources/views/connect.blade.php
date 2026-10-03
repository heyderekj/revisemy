<x-layouts.app title="Connect your assistant — ReviseMy" description="Connect Claude, ChatGPT, Cursor, VS Code or another assistant to ReviseMy. Most connect by pasting one address and clicking Connect — no account." robots="noindex, nofollow">
    <div class="rm-desk flex min-h-svh flex-col">
        <main class="rm-shell overflow-y-auto px-5 py-10 sm:px-8 sm:py-16">
            @if ($client)
                {{-- An assistant is signing in: one screen saying what it will see, then Connect. --}}
                <div class="mx-auto w-full max-w-lg">
                    <x-revisemy-logo size="lg" />
                    <h1 class="mt-6 text-2xl font-semibold text-foreground">Connect {{ $client->name }}</h1>
                    <p class="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                        No account. Connect makes a try workspace that’s yours, and {{ $client->name }} works in it.
                    </p>

                    <x-connect-scope :name="$client->name" class="mt-6" />

                    <form method="POST" action="{{ route('connect') }}" class="mt-6 space-y-4" x-data="{ existing: {{ $errors->has('token') ? 'true' : 'false' }} }">
                        @csrf

                        <div>
                            <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-zinc-600 hover:text-foreground" x-on:click="existing = ! existing" x-bind:aria-expanded="existing.toString()">
                                Use a try token you already have
                                <flux:icon.chevron-down variant="micro" class="size-4 transition" x-bind:class="existing && 'rotate-180'" />
                            </button>
                            <div x-show="existing" x-cloak class="mt-3 space-y-2">
                                <input
                                    type="password"
                                    name="token"
                                    autocomplete="off"
                                    placeholder="Paste your try token"
                                    class="w-full rounded-xl bg-well px-3 py-2.5 font-mono text-sm text-foreground outline-none ring-1 ring-transparent placeholder:text-zinc-400 focus:ring-zinc-400"
                                >
                                <p class="text-xs text-muted-foreground">{{ $client->name }} then sees the reviews from that token too.</p>
                            </div>
                            @error('token')
                                <p class="mt-2 text-sm text-problem-ink">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="btn-lit inline-flex h-11 w-full items-center justify-center rounded-full text-sm font-medium">Connect {{ $client->name }}</button>
                        @if ($returnsTo)
                            <p class="text-center text-xs text-muted-foreground">Returns you to <span class="font-medium text-zinc-700">{{ $returnsTo }}</span></p>
                        @endif
                    </form>
                </div>
            @else
                {{-- Nothing signing in: the one list of ways to connect. --}}
                <div class="mx-auto w-full max-w-3xl">
                    <a href="/" aria-label="ReviseMy home"><x-revisemy-logo variant="wordmark" size="md" /></a>
                    <h1 class="mt-8 text-3xl font-semibold text-foreground">Connect your assistant</h1>
                    <p class="mt-2 max-w-xl text-[15px] leading-relaxed text-pretty text-muted-foreground">
                        Pick yours. Most connect by pasting one address and clicking Connect — no account, no token to copy.
                    </p>

                    <div class="mt-8"><livewire:connect-hub /></div>
                </div>
            @endif
        </main>
    </div>
</x-layouts.app>
