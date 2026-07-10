<?php

use App\Services\TryTokenService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component
{
    public ?string $token = null;

    public ?string $mcpUrl = null;

    public ?string $cursorConfigJson = null;

    public ?string $claudeDesktopConfigJson = null;

    public ?string $vscodeConfigJson = null;

    public ?string $claudeCodeCommand = null;

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
        $this->claudeDesktopConfigJson = json_encode($result['claude_desktop_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->vscodeConfigJson = json_encode($result['vscode_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->claudeCodeCommand = $result['claude_code_command'];

        $this->dispatch('scroll-to-setup');
    }
};
?>

<div
    class="rm-wash relative min-h-screen"
    x-data="{
        mobileNav: false,
        showMention: localStorage.getItem('rm-taylor-mention') !== '0',
        mentionTab: 'challenge'
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
                        <li><a href="#how" class="transition hover:text-zinc-900">The loop</a></li>
                        <li><a href="#cloud" class="transition hover:text-zinc-900">Not just marks</a></li>
                        <li><a href="#agents" class="transition hover:text-zinc-900">How agents use it</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Tools</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#setup" class="transition hover:text-zinc-900">Try token</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Resources</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li>
                            <a href="https://github.com/heyderekj/revisemy" class="transition hover:text-zinc-900" target="_blank" rel="noreferrer">
                                GitHub ↗
                            </a>
                        </li>
                        <li>
                            <a href="https://github.com/heyderekj/revisemy/blob/main/docs/CONNECTORS.md" class="inline-flex items-center gap-2 transition hover:text-zinc-900" target="_blank" rel="noreferrer">
                                Connectors ↗
                                <span class="rounded bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">MCP</span>
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://x.com/taylorotwell/status/2075667366646858222"
                                class="transition hover:text-zinc-900"
                                target="_blank"
                                rel="noreferrer"
                            >Taylor’s challenge ↗</a>
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
                <a href="#how" class="block" x-on:click="mobileNav = false">The loop</a>
                <a href="#cloud" class="block" x-on:click="mobileNav = false">Not just marks</a>
                <a href="#agents" class="block" x-on:click="mobileNav = false">How agents use it</a>
                <a href="#setup" class="block" x-on:click="mobileNav = false">Try token</a>
                <a href="https://github.com/heyderekj/revisemy" target="_blank" rel="noreferrer" class="block">GitHub ↗</a>
            </div>

            {{-- Hero --}}
            <section class="rm-fade-up">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <h1 class="max-w-xl text-[clamp(2.4rem,5.5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-zinc-900">
                        <span class="rm-highlight">Mark feedback.</span>
                        <br>
                        <span class="rm-underline-mark">For agents.</span>
                    </h1>
                    <x-try-token-button class="self-start sm:mt-2" />
                </div>

                <p class="rm-fade-up-delay mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Drop screenshots from any project. Agents can pre-load a second opinion.<br class="hidden sm:block">
                    You mark what matters, then approve or request changes — structured work packets come back over MCP on
                    <a
                        href="https://laravel.com/cloud"
                        target="_blank"
                        rel="noreferrer"
                        class="mx-0.5 inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-2 py-0.5 align-middle text-[13px] font-medium text-zinc-800 shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-950"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7.5 18.5h9a4.5 4.5 0 0 0 .6-8.97A5.5 5.5 0 0 0 6.2 7.8 4 4 0 0 0 7.5 18.5Z" fill="#F53003" fill-opacity="0.14" stroke="#F53003" stroke-width="1.5" stroke-linejoin="round"/>
                            <path d="M9 14.5h6" stroke="#F53003" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Laravel Cloud
                        <svg class="h-3 w-3 text-zinc-400" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                            <path d="M3.5 8.5 8.5 3.5M4.5 3.5h4v4" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <span class="text-xs text-zinc-500">(Laravel not required to use)</span>
                </p>

                @if ($error)
                    <p class="mt-3 text-sm text-rose-600">{{ $error }}</p>
                @endif

                {{-- Product stage: 16:9 Cloud UI + agent chat loop --}}
                <div class="rm-fade-up-delay-2 relative mt-10 sm:mt-12">
                    <div
                        class="rm-stage overflow-hidden rounded-xl border border-zinc-900/10 bg-white shadow-[0_18px_50px_-28px_rgba(24,24,27,0.45)]"
                        x-data="{
                            step: 0,
                            reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                            timer: null,
                            start() {
                                if (this.reduced) { this.step = 4; return; }
                                this.timer = setInterval(() => { this.step = (this.step + 1) % 5; }, 2800);
                            },
                            stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } }
                        }"
                        x-init="start(); $cleanup(() => stop())"
                    >
                        <div class="flex items-center gap-2 border-b border-zinc-200 bg-zinc-50 px-3 py-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-300"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-300"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-300"></span>
                            <span class="ml-2 truncate font-mono text-[11px] text-zinc-400">revisemy · design checkup loop</span>
                        </div>

                        {{-- Checkpoint rail: what makes ReviseMy special --}}
                        <div class="border-b border-zinc-200 bg-white px-2 py-2 sm:px-3">
                            <div class="flex gap-1 overflow-x-auto pb-0.5 sm:grid sm:grid-cols-5 sm:gap-1.5 sm:overflow-visible">
                                <template x-for="(cp, i) in [
                                    { label: '1 · Capture', sub: 'create_review', tone: 'zinc' },
                                    { label: '2 · Second opinion', sub: 'hints only', tone: 'sky' },
                                    { label: '3 · Your marks', sub: 'authoritative', tone: 'rose' },
                                    { label: '4 · Decide', sub: 'you own it', tone: 'amber' },
                                    { label: '5 · Work packets', sub: 'agent continues', tone: 'emerald' }
                                ]" :key="i">
                                    <div
                                        class="rm-checkpoint flex min-w-[7.5rem] flex-1 flex-col rounded-lg border px-2 py-1.5 transition duration-300 sm:min-w-0"
                                        :class="{
                                            'border-zinc-900 bg-zinc-900 text-white shadow-sm scale-[1.02]': step === i && cp.tone === 'zinc',
                                            'border-sky-500 bg-sky-50 text-sky-900 shadow-sm shadow-sky-100 scale-[1.02]': step === i && cp.tone === 'sky',
                                            'border-rose-500 bg-rose-50 text-rose-900 shadow-sm shadow-rose-100 scale-[1.02]': step === i && cp.tone === 'rose',
                                            'border-amber-500 bg-amber-50 text-amber-950 shadow-sm shadow-amber-100 scale-[1.02]': step === i && cp.tone === 'amber',
                                            'border-emerald-500 bg-emerald-50 text-emerald-900 shadow-sm shadow-emerald-100 scale-[1.02]': step === i && cp.tone === 'emerald',
                                            'border-zinc-200 bg-zinc-50/80 text-zinc-400': step !== i && step < i,
                                            'border-zinc-200 bg-white text-zinc-600': step !== i && step > i
                                        }"
                                    >
                                        <span class="text-[10px] font-semibold leading-tight" x-text="cp.label"></span>
                                        <span
                                            class="mt-0.5 font-mono text-[8px] leading-tight"
                                            :class="step === i ? 'opacity-90' : 'opacity-70'"
                                            x-text="cp.sub"
                                        ></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="grid md:aspect-video md:grid-cols-[1.35fr_1fr]">
                            {{-- Left: Cloud UI under review --}}
                            <div
                                class="rm-stage-ui relative min-h-[280px] border-b border-zinc-200 bg-[#f8f8f9] transition duration-500 md:min-h-0 md:border-b-0 md:border-r"
                                :class="{
                                    'ring-2 ring-inset ring-sky-300/60': step === 1,
                                    'ring-2 ring-inset ring-rose-300/70': step === 2,
                                    'ring-2 ring-inset ring-amber-300/60': step === 3,
                                    'ring-2 ring-inset ring-emerald-300/70': step === 4
                                }"
                            >
                                {{-- Step callout badge on UI --}}
                                <div
                                    class="absolute left-3 top-10 z-40 max-w-[70%] rounded-md px-2 py-1 font-mono text-[9px] font-semibold uppercase tracking-wide shadow-sm sm:left-4 sm:top-11"
                                    :class="{
                                        'bg-zinc-900 text-white': step === 0,
                                        'bg-sky-500 text-white': step === 1,
                                        'bg-rose-500 text-white': step === 2,
                                        'bg-amber-500 text-zinc-950': step === 3,
                                        'bg-emerald-500 text-white': step === 4
                                    }"
                                    x-text="['Agent opens review', 'Second opinion (hints)', 'Your marks (authority)', 'You decide', 'Agent gets packets'][step]"
                                ></div>

                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-zinc-200/80 bg-white sm:inset-3">
                                    <div class="flex items-center justify-between gap-2 border-b border-zinc-100 px-2.5 py-2 sm:px-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-[9px] text-zinc-400">Derek · revisemy · production</p>
                                            <div class="mt-0.5 flex items-center gap-1.5">
                                                <x-logo-mark size="sm" />
                                                <span class="truncate text-[12px] font-semibold text-zinc-900">revisemy · production</span>
                                            </div>
                                        </div>
                                        <span class="hidden rounded-md bg-zinc-900 px-2 py-0.5 text-[9px] font-medium text-white sm:inline">Visit</span>
                                    </div>

                                    <div class="flex gap-3 border-b border-zinc-100 px-2.5 text-[10px] text-zinc-400 sm:px-3">
                                        <span class="border-b-2 border-zinc-900 py-1.5 font-medium text-zinc-900">Environment</span>
                                        <span class="py-1.5">Deployments</span>
                                        <span class="hidden py-1.5 sm:inline">Logs</span>
                                    </div>

                                    <div class="grid gap-2 p-2 sm:grid-cols-[1.3fr_0.9fr] sm:gap-2.5 sm:p-2.5">
                                        <div class="relative rounded-md border border-dashed border-zinc-200 bg-[linear-gradient(to_right,rgb(24_24_27/0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgb(24_24_27/0.03)_1px,transparent_1px)] bg-[size:16px_16px] p-2">
                                            <div class="grid grid-cols-3 gap-1.5">
                                                <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                    <p class="text-[8px] font-medium text-zinc-500">Network</p>
                                                    <p class="mt-0.5 text-[10px] font-semibold text-zinc-800">Edge</p>
                                                    <p class="mt-0.5 flex items-center gap-1 text-[8px] text-emerald-600"><span class="h-1 w-1 rounded-full bg-emerald-500"></span> Active</p>
                                                </div>
                                                <div class="relative rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                    <p class="text-[8px] font-medium text-zinc-500">US East</p>
                                                    <p class="mt-0.5 text-[10px] font-semibold text-zinc-800">App cluster</p>
                                                    <p class="mt-0.5 text-[8px] text-zinc-500">Flex 512</p>
                                                    {{-- Second opinion area --}}
                                                    <div
                                                        class="pointer-events-none absolute -inset-1 rounded-md border-2 border-dashed border-sky-400 bg-sky-400/15"
                                                        x-show="step >= 1"
                                                        x-transition.opacity
                                                        :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                    ></div>
                                                    <div
                                                        class="absolute -left-2 -top-2 z-10 flex h-5 w-5 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white text-[8px] font-bold text-sky-700 shadow-sm"
                                                        x-show="step >= 1 && step < 2"
                                                        x-transition
                                                    >S</div>
                                                </div>
                                                <div class="relative flex items-center justify-center rounded-md border border-dashed border-zinc-300 bg-zinc-50/80 p-1.5 text-[8px] text-zinc-400">
                                                    + Add
                                                    <div
                                                        class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-sky-300/70 bg-sky-300/10"
                                                        x-show="step === 1"
                                                        x-transition.opacity
                                                    ></div>
                                                </div>
                                            </div>
                                            <p class="mt-1.5 font-mono text-[8px] text-zinc-400">….laravel.cloud</p>
                                        </div>

                                        <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                            <p class="text-[8px] font-medium uppercase tracking-wide text-zinc-400">Deployments</p>
                                            <ul class="mt-1 space-y-1 text-[9px]">
                                                <li class="flex items-center gap-1.5">
                                                    <span class="h-1 w-1 shrink-0 rounded-full bg-sky-500"></span>
                                                    <span class="truncate text-zinc-700">second opinion</span>
                                                </li>
                                                <li class="flex items-center gap-1.5">
                                                    <span class="h-1 w-1 shrink-0 rounded-full bg-emerald-500"></span>
                                                    <span class="truncate text-zinc-700">markup pen</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                {{-- Second opinion legend --}}
                                <div
                                    class="absolute bottom-3 left-3 z-30 flex items-center gap-1.5 rounded-full border border-sky-300 bg-sky-50 px-2 py-1 text-[9px] font-medium text-sky-800 shadow-sm sm:bottom-4 sm:left-4"
                                    x-show="step === 1"
                                    x-transition
                                >
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full border border-dashed border-sky-500 text-[8px]">S</span>
                                    Hint — not a decision
                                </div>

                                {{-- Human marks: rose selection rectangles --}}
                                <div
                                    class="absolute left-[30%] top-[44%] z-20 h-[20%] w-[26%] rounded-md border-2 border-rose-500 bg-rose-500/10"
                                    x-show="step >= 2"
                                    x-transition
                                    :class="step === 2 ? 'rm-pin-pop' : ''"
                                >
                                    <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M1</span>
                                </div>
                                <div
                                    class="absolute left-[54%] top-[30%] z-20 h-[16%] w-[20%] rounded-md border-2 border-rose-500 bg-rose-500/10"
                                    x-show="step >= 2"
                                    x-transition
                                    :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                >
                                    <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M2</span>
                                </div>
                                <div
                                    class="absolute bottom-3 left-3 z-30 flex items-center gap-1.5 rounded-full border border-rose-300 bg-rose-50 px-2 py-1 text-[9px] font-medium text-rose-800 shadow-sm sm:bottom-4 sm:left-4"
                                    x-show="step === 2"
                                    x-transition
                                >
                                    Your marks
                                </div>

                                {{-- Annotation popover — light --}}
                                <div
                                    class="absolute bottom-[12%] left-[8%] z-30 w-[min(92%,230px)] rounded-lg border border-rose-200 bg-white p-2.5 shadow-xl sm:left-auto sm:right-[8%]"
                                    x-show="step >= 2 && step < 4"
                                    x-transition.opacity
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-mono text-[9px] text-zinc-400">card.app-cluster</p>
                                        <span class="rounded-full bg-rose-50 px-1.5 py-0.5 text-[8px] font-semibold uppercase text-rose-600">must-fix</span>
                                    </div>
                                    <p class="mt-1.5 text-[11px] leading-snug text-zinc-700">Cluster label hierarchy feels soft — bump weight so “App cluster” reads first.</p>
                                    <div class="mt-2 flex items-center justify-end gap-2">
                                        <span class="text-[10px] text-zinc-400">Cancel</span>
                                        <span class="rounded bg-rose-500 px-2 py-0.5 text-[10px] font-medium text-white">Mark</span>
                                    </div>
                                </div>

                                {{-- Decision toolbar — light --}}
                                <div
                                    class="absolute bottom-2 right-2 z-20 flex items-center gap-1 rounded-full border border-zinc-200 bg-white px-1.5 py-1 text-zinc-700 shadow-lg ring-2 ring-amber-300/60 sm:bottom-3 sm:right-3"
                                    x-show="step >= 3"
                                    x-transition
                                    :class="step === 3 ? 'rm-pulse-amber' : ''"
                                >
                                    <x-logo-mark size="md" />
                                    <template x-if="step < 4">
                                        <span class="flex gap-1 pr-1">
                                            <span class="rounded px-1.5 py-0.5 text-[9px] text-zinc-400">Request changes</span>
                                            <span class="rounded bg-rose-500 px-1.5 py-0.5 text-[9px] font-medium text-white">Looks good</span>
                                        </span>
                                    </template>
                                    <template x-if="step >= 4">
                                        <span class="pr-1.5 font-mono text-[9px] text-emerald-600">approved</span>
                                    </template>
                                </div>

                                {{-- Approve flash --}}
                                <div
                                    class="pointer-events-none absolute inset-0 z-10 rounded-lg bg-emerald-400/15"
                                    x-show="step >= 4"
                                    x-transition.opacity
                                ></div>
                                <div
                                    class="absolute left-1/2 top-1/2 z-40 -translate-x-1/2 -translate-y-1/2 rounded-full border border-emerald-400/40 bg-emerald-500 px-3 py-1.5 text-[11px] font-semibold text-white shadow-lg"
                                    x-show="step === 4"
                                    x-transition
                                >Looks good — approved</div>

                                {{-- Packet chip flying from chat --}}
                                <div
                                    class="rm-packet-chip absolute right-2 top-1/2 z-30 hidden -translate-y-1/2 rounded-full border border-sky-300 bg-sky-50 px-2 py-1 font-mono text-[8px] font-semibold text-sky-700 shadow-md md:block"
                                    x-show="step === 1"
                                    x-transition
                                >second opinion →</div>
                            </div>

                            {{-- Right: Agent chat — light, cohesive with capture --}}
                            <div
                                class="rm-stage-chat flex min-h-[240px] flex-col bg-zinc-50 transition duration-500 md:min-h-0"
                                :class="{
                                    'ring-2 ring-inset ring-sky-300/50': step === 1,
                                    'ring-2 ring-inset ring-rose-300/60': step === 2,
                                    'ring-2 ring-inset ring-amber-300/50': step === 3,
                                    'ring-2 ring-inset ring-emerald-300/60': step === 4
                                }"
                            >
                                <div class="flex items-center justify-between border-b border-zinc-200 bg-white px-3 py-2">
                                    <div>
                                        <p class="font-mono text-[10px] text-zinc-400">Agent</p>
                                        <p class="text-[11px] font-medium text-zinc-800">revisemy · MCP</p>
                                    </div>
                                    <span
                                        class="rounded-full px-2 py-0.5 font-mono text-[9px]"
                                        :class="{
                                            'bg-zinc-100 text-zinc-500': step === 0,
                                            'bg-sky-50 text-sky-700': step === 1,
                                            'bg-rose-50 text-rose-700': step === 2,
                                            'bg-amber-50 text-amber-800': step === 3,
                                            'bg-emerald-50 text-emerald-700': step === 4
                                        }"
                                        x-text="['capture', 'hints', 'human', 'decide', 'done'][step]"
                                    ></span>
                                </div>

                                <div class="flex flex-1 flex-col gap-2 overflow-hidden p-3">
                                    <div
                                        class="rm-msg max-w-[95%] rounded-lg rounded-tl-sm border px-2.5 py-2 transition"
                                        :class="step === 0 ? 'border-zinc-300 bg-white ring-2 ring-zinc-300/60' : 'border-zinc-200 bg-white opacity-70'"
                                        x-show="step >= 0"
                                        x-transition
                                    >
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-zinc-400">Capture</p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-zinc-600">Opening a design checkup on the Cloud environment…</p>
                                        <p class="mt-1 font-mono text-[9px] text-rose-600">create_review</p>
                                    </div>

                                    <div
                                        class="rm-msg max-w-[95%] rounded-lg rounded-tl-sm border px-2.5 py-2 transition"
                                        :class="step === 1 ? 'border-sky-300 bg-sky-50 ring-2 ring-sky-200' : 'border-sky-200/80 bg-sky-50/70 opacity-70'"
                                        x-show="step >= 1"
                                        x-transition
                                    >
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-sky-700">Second opinion</p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-sky-900/80">Suggestions only — check CTA contrast near Visit. Never overrides your marks.</p>
                                        <p class="mt-1 font-mono text-[9px] text-sky-600">finding: a11y · hint</p>
                                    </div>

                                    <div
                                        class="rm-msg ml-auto max-w-[92%] rounded-lg rounded-tr-sm border px-2.5 py-2 transition"
                                        :class="step === 2 ? 'border-rose-300 bg-rose-50 ring-2 ring-rose-200' : 'border-rose-200/80 bg-rose-50/70 opacity-70'"
                                        x-show="step >= 2"
                                        x-transition
                                    >
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-700">Your authority</p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-rose-950/85">Bump hierarchy on App cluster so the label reads first.</p>
                                        <p class="mt-1 font-mono text-[9px] text-rose-600">must-fix · M1</p>
                                    </div>

                                    <div
                                        class="rm-msg max-w-[95%] rounded-lg rounded-tl-sm border px-2.5 py-2 transition"
                                        :class="step === 3 ? 'border-amber-300 bg-amber-50 ring-2 ring-amber-200' : 'border-zinc-200 bg-white opacity-70'"
                                        x-show="step >= 3"
                                        x-transition
                                    >
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-amber-800">Waiting on you</p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-zinc-600">Polling the review — agent does not invent approval.</p>
                                        <p class="mt-1 font-mono text-[9px] text-zinc-400">get_review → wait_for_human</p>
                                    </div>

                                    <div
                                        class="rm-msg max-w-[95%] rounded-lg rounded-tl-sm border px-2.5 py-2 transition"
                                        :class="step === 4 ? 'border-emerald-300 bg-emerald-50 ring-2 ring-emerald-200' : 'border-emerald-200/80 bg-emerald-50/70 opacity-70'"
                                        x-show="step >= 4"
                                        x-transition
                                    >
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-emerald-700">Work packets</p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-emerald-950/85">Approved — apply human marks first; second opinion stays hints only.</p>
                                        <p class="mt-1 font-mono text-[9px] text-emerald-600">next_action: done</p>
                                    </div>
                                </div>

                                <div class="border-t border-zinc-200 bg-white px-3 py-2">
                                    <div class="flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1.5">
                                        <span class="truncate font-mono text-[9px] text-zinc-400">Ask ReviseMy to run another pass…</span>
                                        <span class="ml-auto rounded bg-rose-500 px-1.5 py-0.5 text-[8px] font-medium text-white">↵</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- How you use it --}}
            <section id="how" class="mt-20 scroll-mt-8 sm:mt-24">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">The design checkup loop</h2>
                <ol class="mt-6 max-w-2xl space-y-3 text-[15px] leading-relaxed text-zinc-600">
                    <li><span class="font-medium text-zinc-900">1.</span> Agent screenshots UI and opens a review (<code class="font-mono text-[13px] text-rose-600">create_review</code>).</li>
                    <li><span class="font-medium text-zinc-900">2.</span> Optional: agent drops a second opinion / subagent notes into the same review.</li>
                    <li><span class="font-medium text-zinc-900">3.</span> You open the <code class="font-mono text-[13px]">laravel.cloud</code> link, mark what matters, approve or request changes.</li>
                    <li><span class="font-medium text-zinc-900">4.</span> Agent polls <code class="font-mono text-[13px] text-rose-600">get_review</code>, follows <code class="font-mono text-[13px]">next_action</code> — apply marks, then a new pass if needed.</li>
                    <li><span class="font-medium text-zinc-900">5.</span> Repeat until you approve. You stay in charge every pass.</li>
                </ol>
                <p class="rm-note mt-6 inline max-w-2xl text-[15px] leading-relaxed text-zinc-700">
                    <span class="font-medium">Note:</span> Say “run a design checkup” or “address my feedback” — MCP carries the work packets; you don’t paste screenshots around.
                </p>
            </section>

            {{-- Not just marks --}}
            <section id="cloud" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Not just marks</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    Agentation-style markup is the start. ReviseMy is a shared critique surface on Laravel Cloud — queues, object storage, and work packets agents can actually implement.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li><span class="font-medium text-zinc-800">Cloud-queued second opinion</span> — free design checklist on every shot; optional OpenAI vision when keyed</li>
                    <li><span class="font-medium text-zinc-800">Agent as subagent</span> — <code class="font-mono text-[13px] text-rose-600">add_findings</code> drops suggestion / a11y / polish notes into the same review before you look</li>
                    <li><span class="font-medium text-zinc-800">You stay authoritative</span> — only your marks and approve / request-changes flip the status</li>
                </ul>
            </section>

            {{-- How agents use it --}}
            <section id="agents" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">How agents use it</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    <code class="font-mono text-[13px] text-rose-600">get_review</code> returns work packets plus a clear <code class="font-mono text-[13px]">next_action</code> so the agent knows whether to wait, fix, open the next pass, or stop.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li>Human marks: <span class="font-medium text-zinc-800">must-fix</span>, tweaks (<span class="font-medium text-zinc-800">wording</span> / <span class="font-medium text-zinc-800">spacing</span> / <span class="font-medium text-zinc-800">size</span> / <span class="font-medium text-zinc-800">color</span> / <span class="font-medium text-zinc-800">alignment</span>), <span class="font-medium text-zinc-800">nit</span>, <span class="font-medium text-zinc-800">question</span>, or <span class="font-medium text-zinc-800">keep</span></li>
                    <li><span class="font-medium text-zinc-800">second_opinion</span> findings: suggestion / a11y / polish — hints only</li>
                    <li>After changes requested: <code class="font-mono text-[13px] text-rose-600">create_review</code> with <code class="font-mono text-[13px]">parent_id</code> for pass 2+</li>
                    <li>MCP prompt <code class="font-mono text-[13px]">design_checkup_loop</code> encodes the full cycle</li>
                </ul>
            </section>

            {{-- Setup --}}
            <section id="setup" class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Try it on any project</h2>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600">
                    One click, no account.<br class="hidden sm:block">
                    Paste into Cursor, Claude, VS Code, or ChatGPT, then start reviewing.
                </p>

                @if (! $token)
                    <div class="mt-8">
                        <x-try-token-button />
                    </div>
                @else
                    <div
                        class="mt-8 space-y-5"
                        x-data="{ client: 'cursor' }"
                    >
                        <div class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1">
                            @foreach ([
                                'cursor' => 'Cursor',
                                'claude' => 'Claude',
                                'vscode' => 'VS Code',
                                'chatgpt' => 'ChatGPT',
                            ] as $id => $label)
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                    :class="client === '{{ $id }}' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-800'"
                                    x-on:click="client = '{{ $id }}'"
                                >{{ $label }}</button>
                            @endforeach
                        </div>

                        {{-- Cursor --}}
                        <div x-show="client === 'cursor'" x-cloak class="space-y-4">
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>Open <span class="font-medium text-zinc-800">Cursor Settings → MCP</span></li>
                                <li>Paste the config below (or merge into <code class="font-mono text-[13px]">~/.cursor/mcp.json</code>)</li>
                                <li>Ask your agent to call <code class="font-mono text-[13px] text-rose-600">create_review</code></li>
                            </ol>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-medium text-zinc-700">Cursor MCP config</p>
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.cursorConfig.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <pre x-ref="cursorConfig" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $cursorConfigJson }}</pre>
                            </div>
                        </div>

                        {{-- Claude --}}
                        <div x-show="client === 'claude'" x-cloak class="space-y-4">
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li><span class="font-medium text-zinc-800">Claude Code</span> — run the command below in your project terminal</li>
                                <li><span class="font-medium text-zinc-800">Claude Desktop</span> — paste the JSON into MCP / connector settings</li>
                                <li>Ask Claude to call <code class="font-mono text-[13px] text-rose-600">create_review</code></li>
                            </ol>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-medium text-zinc-700">Claude Code</p>
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.claudeCmd.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <pre x-ref="claudeCmd" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $claudeCodeCommand }}</pre>
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-medium text-zinc-700">Claude Desktop config</p>
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.claudeDesktop.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <pre x-ref="claudeDesktop" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $claudeDesktopConfigJson }}</pre>
                            </div>
                        </div>

                        {{-- VS Code --}}
                        <div x-show="client === 'vscode'" x-cloak class="space-y-4">
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>Create <code class="font-mono text-[13px]">.vscode/mcp.json</code> in your project (or open user MCP config)</li>
                                <li>Paste the config below — VS Code uses <code class="font-mono text-[13px]">servers</code>, not <code class="font-mono text-[13px]">mcpServers</code></li>
                                <li>Enable MCP in Copilot Chat, then ask the agent to call <code class="font-mono text-[13px] text-rose-600">create_review</code></li>
                            </ol>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-medium text-zinc-700">VS Code mcp.json</p>
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.vscodeConfig.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <pre x-ref="vscodeConfig" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $vscodeConfigJson }}</pre>
                            </div>
                        </div>

                        {{-- ChatGPT --}}
                        <div x-show="client === 'chatgpt'" x-cloak class="space-y-4">
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>In ChatGPT, add a <span class="font-medium text-zinc-800">remote MCP / connector</span> (or Custom GPT Action)</li>
                                <li>Use the MCP URL and Bearer token below</li>
                                <li>Ask it to open a design checkup via <code class="font-mono text-[13px] text-rose-600">create_review</code> (MCP) or <code class="font-mono text-[13px]">POST /api/reviews</code> (REST)</li>
                            </ol>
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <p class="text-[13px] leading-relaxed text-zinc-600">
                                    ChatGPT’s connector UI varies by plan. Copy the URL and token, then paste them into the connector’s server URL and Authorization header fields.
                                </p>
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-medium text-zinc-700">Authorization header</p>
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.chatgptAuth.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <pre x-ref="chatgptAuth" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">Authorization: Bearer {{ $token }}</pre>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">MCP URL</p>
                                    <button
                                        type="button"
                                        class="text-xs text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.mcpUrl.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <p x-ref="mcpUrl" class="break-all font-mono text-sm text-zinc-700">{{ $mcpUrl }}</p>
                            </div>
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Bearer token</p>
                                    <button
                                        type="button"
                                        class="text-xs text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.bearerToken.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <p x-ref="bearerToken" class="break-all font-mono text-sm text-zinc-700">{{ $token }}</p>
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

    {{-- Floating mention: challenge + about Derek (manila folder tabs) --}}
    <div
        x-show="showMention"
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
            <div class="relative z-10 flex items-end gap-1 px-3">
                <button
                    type="button"
                    class="rounded-t-lg border px-3 py-1.5 text-[11px] font-medium transition"
                    :class="mentionTab === 'challenge'
                        ? 'relative z-[2] -mb-px border-zinc-900/10 border-b-white bg-white/95 text-zinc-900'
                        : 'border-transparent bg-zinc-100/90 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800'"
                    x-on:click="mentionTab = 'challenge'"
                >
                    The Challenge
                </button>
                <button
                    type="button"
                    class="rounded-t-lg border px-3 py-1.5 text-[11px] font-medium transition"
                    :class="mentionTab === 'derek'
                        ? 'relative z-[2] -mb-px border-zinc-900/10 border-b-white bg-white/95 text-zinc-900'
                        : 'border-transparent bg-zinc-100/90 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800'"
                    x-on:click="mentionTab = 'derek'"
                >
                    The Challenger
                </button>
            </div>

            <div class="relative rounded-2xl rounded-tl-md border border-zinc-900/10 bg-white/95 shadow-[0_20px_50px_-24px_rgba(24,24,27,0.55)] backdrop-blur-md">
                <button
                    type="button"
                    class="absolute right-2 top-2 z-10 flex h-7 w-7 items-center justify-center rounded-full text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                    aria-label="Dismiss"
                    x-on:click.stop="showMention = false; localStorage.setItem('rm-taylor-mention', '0')"
                >
                    <span class="text-lg leading-none">&times;</span>
                </button>

                <div class="grid [&>*]:col-start-1 [&>*]:row-start-1">
                    <div
                        class="p-3 pr-10"
                        :class="mentionTab === 'challenge' ? 'relative z-[1] visible' : 'invisible pointer-events-none'"
                        :aria-hidden="mentionTab !== 'challenge'"
                    >
                        <div class="flex gap-3">
                            <img
                                src="{{ asset('images/taylor-otwell-pink.png') }}"
                                alt="Taylor Otwell"
                                class="h-14 w-14 shrink-0 rounded-xl object-cover ring-1 ring-rose-200"
                                width="56"
                                height="56"
                            />
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-rose-500">The challenge</p>
                                <p class="mt-1 text-[13px] font-semibold leading-snug text-zinc-900">
                                    Taylor Otwell’s Laravel Cloud weekend challenge
                                </p>
                                <p class="mt-1.5 text-[12px] leading-relaxed text-zinc-500">
                                    “Best side project shipped on Laravel Cloud this weekend… reply with a laravel.cloud URL.”
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-[10px]">
                                    <a
                                        href="https://x.com/taylorotwell/status/2075667366646858222"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="text-zinc-400 transition hover:text-rose-500"
                                    >@taylorotwell ↗</a>
                                    <a
                                        href="https://x.com/heyderekj/status/2075675582973501792"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-rose-500 transition hover:text-rose-600"
                                    >GAME ON. ↗</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-3 pr-10"
                        :class="mentionTab === 'derek' ? 'relative z-[1] visible' : 'invisible pointer-events-none'"
                        :aria-hidden="mentionTab !== 'derek'"
                    >
                        <div class="flex gap-3">
                            <img
                                src="{{ asset('images/derek-castelli.png') }}"
                                alt="Derek Castelli"
                                class="h-14 w-14 shrink-0 rounded-xl object-cover ring-1 ring-rose-200 bg-[#ffe8f1]"
                                width="56"
                                height="56"
                            />
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-rose-500">Made by</p>
                                <p class="mt-1 text-[13px] font-semibold leading-snug text-zinc-900">
                                    Derek Castelli
                                </p>
                                <p class="mt-1.5 text-[12px] leading-relaxed text-zinc-500">
                                    Agentic design engineer — still making websites full-time. Writing on faith and technology.
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-[10px]">
                                    <a
                                        href="https://heyderekj.com"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="text-zinc-400 transition hover:text-rose-500"
                                    >heyderekj.com ↗</a>
                                    <a
                                        href="https://x.com/heyderekj/status/2075675582973501792"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-rose-500 transition hover:text-rose-600"
                                    >GAME ON. ↗</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
