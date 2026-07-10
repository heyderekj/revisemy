<?php

use App\Services\TryTokenService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component
{
    public ?string $token = null;

    public ?string $mcpUrl = null;

    public ?string $cursorConfigJson = null;

    public ?string $error = null;

    public function getTryToken(TryTokenService $tryTokens): void
    {
        $this->error = null;

        $key = 'try-token-web:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Slow down — try again in a minute.';

            return;
        }

        RateLimiter::hit($key, 60);

        $result = $tryTokens->create();

        $this->token = $result['token'];
        $this->mcpUrl = $result['mcp_url'];
        $this->cursorConfigJson = json_encode($result['cursor_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->dispatch('scroll-to-setup');
    }
};
?>

<div
    class="rm-wash relative overflow-x-hidden"
    x-data
    x-on:scroll-to-setup.window="$nextTick(() => document.getElementById('setup')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
>
    <div class="rm-grid pointer-events-none absolute inset-0"></div>

    {{-- Nav --}}
    <header class="relative z-20 mx-auto flex max-w-6xl items-center justify-between px-6 py-5 sm:px-8">
        <a href="/" class="group flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-800 text-sm font-semibold text-white transition group-hover:bg-teal-700">R</span>
            <span class="text-[15px] font-semibold tracking-tight">ReviseMy</span>
        </a>
        <nav class="flex items-center gap-6 text-sm text-zinc-600">
            <a href="#how" class="transition hover:text-zinc-900">How it works</a>
            <a href="#setup" class="transition hover:text-zinc-900">Setup</a>
            <a href="https://github.com/heyderekj/revisemy" class="transition hover:text-zinc-900" target="_blank" rel="noreferrer">GitHub</a>
        </nav>
    </header>

    {{-- Hero: one composition — brand, line, CTA, product stage in first viewport --}}
    <section class="relative z-10 mx-auto flex min-h-[calc(100svh-4.5rem)] max-w-6xl flex-col px-6 pb-10 pt-4 sm:px-8 sm:pb-14 sm:pt-6">
        <div class="rm-fade-up grid items-end gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.15fr)] lg:gap-10">
            <div class="max-w-xl pb-1">
                <p class="font-display text-[clamp(2.75rem,7vw,4.75rem)] leading-[0.92] tracking-tight text-zinc-900">
                    ReviseMy
                </p>
                <p class="rm-fade-up-delay mt-4 max-w-md text-base leading-relaxed text-zinc-600 sm:text-lg">
                    Pin feedback for your agent — screenshots in, structured notes out over MCP.
                </p>
                <div class="rm-fade-up-delay-2 mt-6 flex flex-wrap items-center gap-3">
                    @if (! $token)
                        <flux:button variant="primary" wire:click="getTryToken" class="!h-11 !bg-teal-800 !px-5 hover:!bg-teal-700">
                            Get a try token
                        </flux:button>
                        <span class="text-sm text-zinc-500">Any project · no account</span>
                    @else
                        <a href="#setup" class="inline-flex h-11 items-center rounded-lg bg-teal-800 px-5 text-sm font-medium text-white transition hover:bg-teal-700">
                            Jump to your config
                        </a>
                    @endif
                </div>
                @if ($error)
                    <p class="mt-4 text-sm text-rose-700">{{ $error }}</p>
                @endif
            </div>

            {{-- Dominant product stage sits beside brand on desktop --}}
            <div class="rm-fade-up-delay-2 relative min-w-0 lg:row-span-1">
                <div class="pointer-events-none absolute -inset-6 bg-[radial-gradient(ellipse_at_center,_rgb(15_118_110_/_0.16),_transparent_70%)] rm-stage-shine"></div>

                <div class="relative overflow-hidden rounded-2xl border border-zinc-900/10 bg-zinc-900 shadow-[0_40px_80px_-40px_rgba(24,24,27,0.55)]">
                    <div class="flex items-center justify-between gap-3 border-b border-white/10 px-3 py-2.5 sm:px-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-[11px] text-zinc-400">
                                <span class="font-medium text-teal-400">ReviseMy</span>
                                <span>/</span>
                                <span class="truncate text-zinc-300">Checkout redesign</span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-zinc-500">Waiting on your eye</p>
                        </div>
                        <div class="hidden items-center gap-1.5 sm:flex">
                            <span class="rounded-md px-2.5 py-1 text-[11px] text-zinc-300 ring-1 ring-white/10">Request changes</span>
                            <span class="rounded-md bg-teal-700 px-2.5 py-1 text-[11px] font-medium text-white">Approve</span>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-[1fr_200px]">
                        <div class="relative h-[240px] bg-[#1c1917] sm:h-[300px] lg:h-[340px]">
                            <div class="absolute inset-3 overflow-hidden rounded-xl bg-[#fafaf9] sm:inset-4">
                                <div class="flex h-full flex-col">
                                    <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-2.5">
                                        <span class="text-sm font-semibold text-zinc-800">Northwind</span>
                                        <span class="text-[11px] text-zinc-400">Cart · 2 items</span>
                                    </div>
                                    <div class="grid flex-1 gap-3 p-4 sm:grid-cols-[1.15fr_0.85fr]">
                                        <div class="space-y-2.5">
                                            <div class="h-20 rounded-lg bg-gradient-to-br from-zinc-200 via-stone-100 to-teal-100/80 sm:h-28"></div>
                                            <div class="h-2.5 w-2/3 rounded bg-zinc-200"></div>
                                            <div class="h-2.5 w-1/2 rounded bg-zinc-100"></div>
                                        </div>
                                        <div class="flex flex-col justify-between rounded-lg border border-zinc-200 bg-white p-3">
                                            <div>
                                                <p class="text-[10px] uppercase tracking-wide text-zinc-400">Total</p>
                                                <p class="mt-0.5 text-xl font-semibold text-zinc-900">$128</p>
                                            </div>
                                            <button type="button" class="mt-3 rounded-lg bg-zinc-900 px-3 py-2 text-xs font-medium text-white">
                                                Continue to payment
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="rm-pin-pop absolute left-[62%] top-[72%] z-10 flex h-6 w-6 items-center justify-center rounded-full bg-teal-700 text-[10px] font-semibold text-white ring-2 ring-white" style="transform: translate(-50%, -50%)">1</div>
                                <div class="rm-pin-pop-2 absolute left-[28%] top-[38%] z-10 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-[10px] font-semibold text-white ring-2 ring-white" style="transform: translate(-50%, -50%)">2</div>
                                <div class="rm-pin-pop-3 absolute left-[18%] top-[18%] z-10 flex h-6 w-6 items-center justify-center rounded-full bg-teal-700 text-[10px] font-semibold text-white ring-2 ring-white" style="transform: translate(-50%, -50%)">3</div>
                            </div>
                        </div>

                        <aside class="hidden border-l border-white/10 bg-zinc-950/80 p-3 sm:block">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-zinc-500">Pins</p>
                            <ul class="mt-3 space-y-2">
                                <li class="rounded-lg border border-white/10 bg-white/5 p-2.5">
                                    <div class="mb-1 flex items-center gap-1.5">
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-teal-700 text-[9px] font-semibold text-white">1</span>
                                        <span class="text-[9px] uppercase tracking-wide text-zinc-500">Must fix</span>
                                    </div>
                                    <p class="text-[11px] leading-snug text-zinc-300">CTA feels light — bump weight.</p>
                                </li>
                                <li class="rounded-lg border border-white/10 bg-white/5 p-2.5">
                                    <div class="mb-1 flex items-center gap-1.5">
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-semibold text-white">2</span>
                                        <span class="text-[9px] uppercase tracking-wide text-zinc-500">Nit</span>
                                    </div>
                                    <p class="text-[11px] leading-snug text-zinc-300">Image ratio feels tall.</p>
                                </li>
                                <li class="rounded-lg border border-white/10 bg-white/5 p-2.5">
                                    <div class="mb-1 flex items-center gap-1.5">
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-teal-700 text-[9px] font-semibold text-white">3</span>
                                        <span class="text-[9px] uppercase tracking-wide text-zinc-500">Must fix</span>
                                    </div>
                                    <p class="text-[11px] leading-snug text-zinc-300">Wordmark competes with title.</p>
                                </li>
                            </ul>
                        </aside>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works — Agentation cadence --}}
    <section id="how" class="relative z-10 border-t border-zinc-900/8 bg-white/50">
        <div class="mx-auto max-w-6xl px-6 py-20 sm:px-8 sm:py-28">
            <p class="text-sm font-medium uppercase tracking-[0.16em] text-teal-800">How you use it</p>
            <h2 class="font-display mt-3 max-w-2xl text-3xl tracking-tight text-zinc-900 sm:text-4xl">
                From screenshot to sign-off in one loop.
            </h2>

            <ol class="mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
                @foreach ([
                    ['01', 'Agent captures UI', 'Your agent screenshots the work and calls create_review over MCP.'],
                    ['02', 'You get a link', 'Open the laravel.cloud review URL — no account, no install.'],
                    ['03', 'Pin like a critique', 'Click the shot, leave notes, mark must-fix or nit.'],
                    ['04', 'Agent reads pins', 'Approve or request changes. get_review returns structured feedback.'],
                ] as [$num, $title, $body])
                    <li>
                        <p class="font-mono text-xs text-teal-800/80">{{ $num }}</p>
                        <h3 class="mt-3 text-base font-semibold text-zinc-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- What agents get — Filament-style code craft --}}
    <section class="relative z-10 border-t border-zinc-900/8">
        <div class="mx-auto grid max-w-6xl gap-12 px-6 py-20 sm:px-8 sm:py-28 lg:grid-cols-2 lg:items-center">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.16em] text-teal-800">What the agent gets</p>
                <h2 class="font-display mt-3 text-3xl tracking-tight text-zinc-900 sm:text-4xl">
                    Feedback as data, not a Slack thread.
                </h2>
                <p class="mt-5 max-w-md text-base leading-relaxed text-zinc-600">
                    Pins come back with coordinates, severity, and your words — so the agent can fix the right spot instead of guessing which “blue button” you meant.
                </p>
            </div>
            <div class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950 shadow-[0_24px_60px_-30px_rgba(24,24,27,0.5)]">
                <div class="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-zinc-600"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-zinc-600"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-zinc-600"></span>
                    <span class="ml-2 font-mono text-[11px] text-zinc-500">get_review →</span>
                </div>
                <pre class="overflow-x-auto p-5 font-mono text-[12px] leading-relaxed text-teal-100/90 sm:text-[13px]"><code>{
  "status": "changes_requested",
  "status_label": "Changes requested",
  "review_url": "https://….laravel.cloud/r/…",
  "screenshots": [{
    "annotations": [{
      "number": 1,
      "x": 0.62,
      "y": 0.72,
      "severity": "must-fix",
      "body": "CTA feels light — bump weight."
    }]
  }]
}</code></pre>
            </div>
        </div>
    </section>

    {{-- Setup / try token --}}
    <section id="setup" class="relative z-10 border-t border-zinc-900/8 bg-zinc-900 text-zinc-100">
        <div class="mx-auto max-w-6xl px-6 py-20 sm:px-8 sm:py-28">
            <p class="text-sm font-medium uppercase tracking-[0.16em] text-teal-400">Try on any project</p>
            <h2 class="font-display mt-3 max-w-2xl text-3xl tracking-tight text-white sm:text-4xl">
                Add ReviseMy to Cursor in two minutes.
            </h2>

            @if (! $token)
                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <flux:button variant="primary" wire:click="getTryToken" class="!h-11 !bg-teal-600 !px-5 hover:!bg-teal-500">
                        Get a try token
                    </flux:button>
                    <p class="max-w-sm text-sm text-zinc-400">
                        One click creates a scoped workspace. Paste the MCP config into any repo and start reviewing.
                    </p>
                </div>
                @if ($error)
                    <p class="mt-4 text-sm text-rose-300">{{ $error }}</p>
                @endif
            @else
                <div class="mt-10 space-y-8">
                    <p class="max-w-2xl text-base text-zinc-300">
                        Your try token is ready. Paste this into Cursor MCP settings, then ask your agent to screenshot UI work and call <code class="font-mono text-teal-300">create_review</code>.
                    </p>

                    <div>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-zinc-300">Cursor MCP config</p>
                            <button
                                type="button"
                                class="text-sm text-teal-400 transition hover:text-teal-300"
                                x-data
                                x-on:click="navigator.clipboard.writeText($refs.config.textContent); $el.textContent = 'Copied'; setTimeout(() => $el.textContent = 'Copy', 1600)"
                            >
                                Copy
                            </button>
                        </div>
                        <pre
                            x-ref="config"
                            class="overflow-x-auto rounded-xl border border-white/10 bg-black/40 p-4 font-mono text-[12px] leading-relaxed text-teal-100/90 sm:text-[13px]"
                        >{{ $cursorConfigJson }}</pre>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">MCP URL</p>
                            <p class="mt-2 break-all font-mono text-sm text-zinc-200">{{ $mcpUrl }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">Bearer token</p>
                            <p class="mt-2 break-all font-mono text-sm text-zinc-200">{{ $token }}</p>
                        </div>
                    </div>

                    <ol class="grid gap-4 text-sm text-zinc-400 sm:grid-cols-3">
                        <li><span class="font-medium text-white">1.</span> Add the config to Cursor in any local project.</li>
                        <li><span class="font-medium text-white">2.</span> Ask the agent to capture UI and create a review.</li>
                        <li><span class="font-medium text-white">3.</span> Open the Cloud link, pin, approve or request changes.</li>
                    </ol>
                </div>
            @endif
        </div>
    </section>

    <footer class="relative z-10 border-t border-zinc-800 bg-zinc-950 px-6 py-8 text-sm text-zinc-500 sm:px-8">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p>Open source · Laravel + Livewire Flux · Built for Laravel Cloud</p>
            <a href="https://github.com/heyderekj/revisemy" class="text-zinc-400 transition hover:text-white" target="_blank" rel="noreferrer">heyderekj/revisemy</a>
        </div>
    </footer>
</div>
