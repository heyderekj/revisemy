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
    class="rm-wash relative min-h-screen"
    x-data="{
        mobileNav: false,
        showTaylor: localStorage.getItem('rm-taylor-mention') !== '0'
    }"
    x-on:scroll-to-setup.window="$nextTick(() => document.getElementById('setup')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
>
    <div class="rm-grid pointer-events-none absolute inset-0"></div>

    <div class="relative z-10 mx-auto flex min-h-screen max-w-[1200px]">
        {{-- Agentation-style sidebar --}}
        <aside class="hidden w-[220px] shrink-0 flex-col border-r border-zinc-900/8 px-6 py-8 lg:flex">
            <a href="/" class="font-mark text-[2.35rem] leading-none tracking-tight text-rose-500">
                ReviseMy
            </a>

            <nav class="mt-12 flex flex-1 flex-col gap-8 text-[14px]">
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Overview</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#top" class="transition hover:text-zinc-900">Home</a></li>
                        <li><a href="#how" class="transition hover:text-zinc-900">How you use it</a></li>
                        <li><a href="#cloud" class="transition hover:text-zinc-900">Not just pins</a></li>
                        <li><a href="#agents" class="transition hover:text-zinc-900">How agents use it</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Tools</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#setup" class="transition hover:text-zinc-900">Try token</a></li>
                        <li><a href="#setup" class="transition hover:text-zinc-900">MCP config</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Resources</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li>
                            <a href="https://github.com/heyderekj/revisemy" class="inline-flex items-center gap-2 transition hover:text-zinc-900" target="_blank" rel="noreferrer">
                                GitHub
                            </a>
                        </li>
                        <li>
                            <a href="https://github.com/heyderekj/revisemy/blob/main/docs/CONNECTORS.md" class="inline-flex items-center gap-2 transition hover:text-zinc-900" target="_blank" rel="noreferrer">
                                Connectors
                                <span class="rounded bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">MCP</span>
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://x.com/taylorotwell/status/2075667366646858222"
                                class="transition hover:text-zinc-900"
                                target="_blank"
                                rel="noreferrer"
                            >Taylor’s challenge</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <p class="mt-auto pt-8 font-mono text-[11px] text-zinc-400">v1.0.0</p>
        </aside>

        {{-- Main --}}
        <main id="top" class="min-w-0 flex-1 px-5 py-6 sm:px-8 sm:py-8 lg:px-12 lg:py-10">
            {{-- Mobile top bar --}}
            <div class="mb-8 flex items-center justify-between lg:hidden">
                <a href="/" class="font-mark text-3xl leading-none text-rose-500">ReviseMy</a>
                <button type="button" class="text-sm text-zinc-600" x-on:click="mobileNav = !mobileNav">Menu</button>
            </div>
            <div x-show="mobileNav" x-cloak class="mb-8 space-y-2 text-sm text-zinc-600 lg:hidden">
                <a href="#how" class="block" x-on:click="mobileNav = false">How you use it</a>
                <a href="#cloud" class="block" x-on:click="mobileNav = false">Not just pins</a>
                <a href="#agents" class="block" x-on:click="mobileNav = false">How agents use it</a>
                <a href="#setup" class="block" x-on:click="mobileNav = false">Try token</a>
                <a href="https://github.com/heyderekj/revisemy" target="_blank" rel="noreferrer" class="block">GitHub</a>
            </div>

            {{-- Hero --}}
            <section class="rm-fade-up">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <h1 class="max-w-xl text-[clamp(2.4rem,5.5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-zinc-900">
                        <span class="rm-highlight">Pin feedback.</span>
                        <span class="rm-underline-mark"> For agents.</span>
                    </h1>
                    <button
                        type="button"
                        wire:click="getTryToken"
                        class="shrink-0 self-start font-mono text-[12px] text-zinc-500 transition hover:text-rose-600 sm:mt-3"
                    >
                        get a try token ↗
                    </button>
                </div>

                <p class="rm-fade-up-delay mt-5 max-w-xl text-[15px] leading-relaxed text-zinc-600 sm:text-base">
                    Drop screenshots from any project. Agents can pre-load a second opinion. You pin what matters, then approve or request changes — structured work packets come back over MCP on Laravel Cloud.
                </p>

                @if ($error)
                    <p class="mt-3 text-sm text-rose-600">{{ $error }}</p>
                @endif

                {{-- Product stage: stylized Laravel Cloud dashboard under review --}}
                <div class="rm-fade-up-delay-2 relative mt-10 sm:mt-12">
                    <div class="overflow-hidden rounded-xl border border-zinc-900/10 bg-white shadow-[0_18px_50px_-28px_rgba(24,24,27,0.45)]">
                        <div class="flex items-center gap-2 border-b border-zinc-200 bg-zinc-50 px-3 py-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-300"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-300"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-300"></span>
                            <span class="ml-2 truncate font-mono text-[11px] text-zinc-400">cloud.laravel.com · revisemy / production</span>
                        </div>

                        <div class="relative min-h-[300px] bg-[#f8f8f9] sm:min-h-[400px]">
                            {{-- Stylized Cloud environment canvas --}}
                            <div class="absolute inset-3 overflow-hidden rounded-lg border border-zinc-200/80 bg-white sm:inset-5">
                                {{-- Cloud top bar --}}
                                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-3 py-2.5 sm:px-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-[10px] text-zinc-400">Derek · revisemy · production</p>
                                        <div class="mt-0.5 flex items-center gap-2">
                                            <span class="flex h-5 w-5 items-center justify-center rounded bg-rose-500 text-[10px] font-bold text-white">R</span>
                                            <span class="text-sm font-semibold text-zinc-900">revisemy · production</span>
                                        </div>
                                    </div>
                                    <div class="hidden items-center gap-1.5 sm:flex">
                                        <span class="rounded-md border border-zinc-200 px-2 py-1 text-[10px] text-zinc-500">Cancel</span>
                                        <span class="rounded-md bg-zinc-900 px-2.5 py-1 text-[10px] font-medium text-white">Visit</span>
                                    </div>
                                </div>

                                {{-- Tabs --}}
                                <div class="flex gap-4 border-b border-zinc-100 px-3 text-[11px] text-zinc-400 sm:px-4">
                                    <span class="border-b-2 border-zinc-900 py-2 font-medium text-zinc-900">Environment</span>
                                    <span class="py-2">Deployments</span>
                                    <span class="hidden py-2 sm:inline">Logs</span>
                                    <span class="hidden py-2 md:inline">Metrics</span>
                                </div>

                                <div class="grid gap-3 p-3 sm:grid-cols-[1.35fr_0.9fr] sm:gap-4 sm:p-4">
                                    {{-- Infra map --}}
                                    <div class="relative rounded-lg border border-dashed border-zinc-200 bg-[linear-gradient(to_right,rgb(24_24_27/0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgb(24_24_27/0.03)_1px,transparent_1px)] bg-[size:20px_20px] p-3">
                                        <div class="grid gap-2 sm:grid-cols-3">
                                            <div class="rounded-lg border border-zinc-200 bg-white p-2.5 shadow-sm">
                                                <p class="text-[10px] font-medium text-zinc-500">Network</p>
                                                <p class="mt-1 text-[11px] font-semibold text-zinc-800">Edge network</p>
                                                <p class="mt-1 flex items-center gap-1 text-[10px] text-emerald-600"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> CDN · Active</p>
                                            </div>
                                            <div class="rounded-lg border border-zinc-200 bg-white p-2.5 shadow-sm">
                                                <p class="text-[10px] font-medium text-zinc-500">US East</p>
                                                <p class="mt-1 text-[11px] font-semibold text-zinc-800">App cluster</p>
                                                <p class="mt-1 text-[10px] text-zinc-500">Flex 512 MiB</p>
                                            </div>
                                            <div class="flex items-center justify-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50/80 p-2.5 text-[10px] text-zinc-400">
                                                + Add resource
                                            </div>
                                        </div>
                                        <p class="mt-2 font-mono text-[9px] text-zinc-400">revisemy-production-….laravel.cloud</p>
                                    </div>

                                    {{-- Deployments --}}
                                    <div class="rounded-lg border border-zinc-200 bg-white p-2.5 shadow-sm">
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-400">Latest deployments</p>
                                        <ul class="mt-2 space-y-1.5">
                                            <li class="flex items-start gap-2 text-[10px]">
                                                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500"></span>
                                                <span><span class="font-medium text-zinc-800">Deploying</span> · second opinion docs</span>
                                            </li>
                                            <li class="flex items-start gap-2 text-[10px]">
                                                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                                                <span><span class="font-medium text-zinc-800">Deployed</span> · Taylor mention</span>
                                            </li>
                                            <li class="flex items-start gap-2 text-[10px]">
                                                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                                                <span><span class="font-medium text-zinc-800">Deployed</span> · pink markup pen</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            {{-- Pins on Cloud UI --}}
                            <div class="rm-pin-pop absolute left-[22%] top-[48%] z-20 flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-xs font-semibold text-white ring-[3px] ring-white" style="transform: translate(-50%, -50%)">1</div>
                            <div class="rm-pin-pop-2 absolute left-[72%] top-[42%] z-10 flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-xs font-semibold text-white ring-[3px] ring-white" style="transform: translate(-50%, -50%)">2</div>
                            <div class="rm-pin-pop-3 absolute left-[48%] top-[28%] z-10 flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-xs font-semibold text-white ring-[3px] ring-white" style="transform: translate(-50%, -50%)">3</div>

                            {{-- Annotation popover --}}
                            <div class="absolute bottom-[14%] left-1/2 z-30 w-[min(100%-1.5rem,260px)] -translate-x-1/2 rounded-lg border border-zinc-800 bg-zinc-900 p-3 shadow-xl sm:left-[70%] sm:bottom-[20%] sm:translate-x-0">
                                <p class="font-mono text-[10px] text-zinc-400">card.app-cluster</p>
                                <p class="mt-2 text-[13px] leading-snug text-zinc-100">Cluster label hierarchy feels soft — bump weight so “App cluster” reads first.</p>
                                <div class="mt-3 flex items-center justify-end gap-3">
                                    <span class="text-xs text-zinc-500">Cancel</span>
                                    <span class="rounded bg-rose-500 px-2.5 py-1 text-xs font-medium text-white">Add</span>
                                </div>
                            </div>

                            {{-- Floating toolbar --}}
                            <div class="absolute bottom-3 right-3 z-20 flex items-center gap-1 rounded-full bg-zinc-900 px-2 py-1.5 text-white shadow-lg sm:bottom-4 sm:right-4">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-[11px] font-semibold">R</span>
                                <span class="px-1 font-mono text-[10px] text-zinc-400">3 pins</span>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-center text-[12px] text-zinc-400">
                        Stylized Laravel Cloud environment — the kind of UI your agent ships, then asks you to revise.
                    </p>
                </div>
            </section>

            {{-- How you use it --}}
            <section id="how" class="mt-20 scroll-mt-8 sm:mt-24">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">How you use it</h2>
                <ol class="mt-6 max-w-2xl space-y-3 text-[15px] leading-relaxed text-zinc-600">
                    <li><span class="font-medium text-zinc-900">1.</span> Get a try token and paste the MCP config into Cursor (any project).</li>
                    <li><span class="font-medium text-zinc-900">2.</span> Ask your agent to screenshot UI work and call <code class="font-mono text-[13px] text-rose-600">create_review</code>.</li>
                    <li><span class="font-medium text-zinc-900">3.</span> Open the <code class="font-mono text-[13px]">laravel.cloud</code> link — second opinion may already be on the shot. Pin what you care about.</li>
                    <li><span class="font-medium text-zinc-900">4.</span> Approve or request changes — the agent polls <code class="font-mono text-[13px] text-rose-600">get_review</code>.</li>
                </ol>
                <p class="rm-note mt-6 inline max-w-2xl text-[15px] leading-relaxed text-zinc-700">
                    <span class="font-medium">Note:</span> With MCP, you skip the copy-paste loop. Just say “address my feedback” or “fix pin 1.”
                </p>
            </section>

            {{-- Not just pins --}}
            <section id="cloud" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Not just pins</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    Agentation-style markup is the start. ReviseMy is a shared critique surface on Laravel Cloud — queues, object storage, and work packets agents can actually implement.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li><span class="font-medium text-zinc-800">Cloud-queued second opinion</span> — free design checklist on every shot; optional OpenAI vision when keyed</li>
                    <li><span class="font-medium text-zinc-800">Agent as subagent</span> — <code class="font-mono text-[13px] text-rose-600">add_findings</code> drops suggestion / a11y / polish notes into the same review before you look</li>
                    <li><span class="font-medium text-zinc-800">You stay authoritative</span> — only your pins and approve / request-changes flip the status</li>
                </ul>
            </section>

            {{-- How agents use it --}}
            <section id="agents" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">How agents use it</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    <code class="font-mono text-[13px] text-rose-600">get_review</code> returns work packets — human intent first, second opinion as hints.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li>Human pins with severity <span class="font-medium text-zinc-800">must-fix</span> or <span class="font-medium text-zinc-800">nit</span> (apply these first)</li>
                    <li><span class="font-medium text-zinc-800">second_opinion</span> findings: suggestion / a11y / polish from checklist, vision, or the agent subagent</li>
                    <li>Optional <span class="font-medium text-zinc-800">page_url</span> for later DOM grounding</li>
                    <li>A stable review URL on Laravel Cloud</li>
                </ul>
            </section>

            {{-- Setup --}}
            <section id="setup" class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Try it on any project</h2>
                <p class="mt-3 max-w-xl text-[15px] text-zinc-600">
                    One click. No account. Paste into Cursor and start reviewing.
                </p>

                @if (! $token)
                    <div class="mt-8">
                        <flux:button variant="primary" wire:click="getTryToken" class="!h-11 !bg-rose-600 !px-5 hover:!bg-rose-500">
                            Get a try token
                        </flux:button>
                    </div>
                @else
                    <div class="mt-8 space-y-5">
                        <p class="max-w-2xl text-[15px] text-zinc-600">
                            Paste this into Cursor MCP settings, then ask your agent to call <code class="font-mono text-rose-600">create_review</code>.
                        </p>
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-sm font-medium text-zinc-700">Cursor MCP config</p>
                                <button
                                    type="button"
                                    class="text-sm text-rose-600 hover:text-rose-500"
                                    x-data
                                    x-on:click="navigator.clipboard.writeText($refs.config.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                >Copy</button>
                            </div>
                            <pre x-ref="config" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $cursorConfigJson }}</pre>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">MCP URL</p>
                                <p class="mt-2 break-all font-mono text-sm text-zinc-700">{{ $mcpUrl }}</p>
                            </div>
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Bearer token</p>
                                <p class="mt-2 break-all font-mono text-sm text-zinc-700">{{ $token }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <footer class="mt-20 border-t border-zinc-900/8 py-8 text-sm text-zinc-400">
                Open source · Laravel + Livewire Flux · Built for Laravel Cloud
            </footer>
        </main>
    </div>

    {{-- Floating mention: Taylor's Laravel Cloud weekend challenge --}}
    <div
        x-show="showTaylor"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed bottom-4 right-4 z-40 w-[min(100%-2rem,22rem)] sm:bottom-6 sm:right-6"
        style="display: none;"
    >
        <div class="relative">
        <a
            href="https://x.com/taylorotwell/status/2075667366646858222"
            target="_blank"
            rel="noreferrer"
            class="group relative block overflow-hidden rounded-2xl border border-zinc-900/10 bg-white/95 p-3 shadow-[0_20px_50px_-24px_rgba(24,24,27,0.55)] backdrop-blur-md transition hover:-translate-y-0.5 hover:border-rose-300/80"
        >
            <div class="flex gap-3">
                <img
                    src="{{ asset('images/taylor-otwell.png') }}"
                    alt="Taylor Otwell"
                    class="h-14 w-14 shrink-0 rounded-xl object-cover ring-1 ring-zinc-900/10"
                    width="56"
                    height="56"
                />
                <div class="min-w-0 pr-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-rose-500">Why this exists</p>
                    <p class="mt-1 text-[13px] font-semibold leading-snug text-zinc-900">
                        Taylor Otwell’s Laravel Cloud weekend challenge
                    </p>
                    <p class="mt-1.5 text-[12px] leading-relaxed text-zinc-500">
                        “Best side project shipped on Laravel Cloud this weekend… reply with a laravel.cloud URL.”
                    </p>
                    <p class="mt-2 font-mono text-[10px] text-zinc-400 transition group-hover:text-rose-500">
                        @taylorotwell ↗
                    </p>
                </div>
            </div>
        </a>

        <button
            type="button"
            class="absolute right-2 top-2 z-10 flex h-7 w-7 items-center justify-center rounded-full text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
            aria-label="Dismiss"
            x-on:click.stop="showTaylor = false; localStorage.setItem('rm-taylor-mention', '0')"
        >
            <span class="text-lg leading-none">&times;</span>
        </button>
        </div>
    </div>
</div>
