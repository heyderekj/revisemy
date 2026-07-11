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
                        <li><a href="#cloud" class="transition hover:text-zinc-900">Beyond marks</a></li>
                        <li><a href="#agents" class="transition hover:text-zinc-900">Technical handoff</a></li>
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
                                href="#challenge"
                                class="transition hover:text-zinc-900"
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
                <a href="#how" class="block" x-on:click="mobileNav = false">The loop</a>
                <a href="#cloud" class="block" x-on:click="mobileNav = false">Beyond marks</a>
                <a href="#agents" class="block" x-on:click="mobileNav = false">Technical handoff</a>
                <a href="#setup" class="block" x-on:click="mobileNav = false">Try token</a>
                <a href="https://github.com/heyderekj/revisemy" target="_blank" rel="noreferrer" class="block">GitHub ↗</a>
            </div>

            {{-- Hero --}}
            <section class="rm-fade-up">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <h1 class="max-w-xl text-[clamp(2.4rem,5.5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-zinc-900">
                        <span class="rm-highlight">Visual feedback.</span>
                        <br>
                        <span class="rm-underline-mark">For agents.</span>
                    </h1>
                    <x-try-token-button class="self-start sm:mt-2" />
                </div>

                <p class="rm-fade-up-delay mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Review interfaces, websites, presentations, and email templates with your agent. Mark what matters, approve or request changes, and send clear next steps back over MCP on
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

                {{-- Product stage: multi-use-case checkup loop --}}
                <div class="rm-fade-up-delay-2 relative mt-10 sm:mt-12">
                    <div
                        class="rm-stage overflow-hidden rounded-xl border border-zinc-900/10 bg-white shadow-[0_18px_50px_-28px_rgba(24,24,27,0.45)]"
                        x-data="{
                            step: 0,
                            scenario: 'product',
                            order: ['product', 'websites', 'presentations', 'email'],
                            stepMs: 2800,
                            cycleKey: 0,
                            paused: false,
                            reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                            timer: null,
                            resumeTimer: null,
                            scenarios: {
                                product: {
                                    title: 'revisemy · interfaces checkup',
                                    capture: 'Opening a design checkup on the production environment…',
                                    hintBody: 'Traffic series rely on rose vs sky alone — label each line directly for faster scanning.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: '“Production” needs context — surface region and service count in the page header.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'Recent deploys are missing commit context — show SHA and author beside status.',
                                    mark2Meta: 'content · M2',
                                    markTarget: 'environment.header',
                                    markSeverity: 'must-fix',
                                    markNote: 'Add “US East · 3 services” under Production so the environment is identifiable.',
                                    mark2Target: 'deploy.row',
                                    mark2Severity: 'content',
                                    mark2Note: 'Include commit SHA and author so each deploy can be traced without opening it.',
                                    packets: 'Changes requested — add environment context and deploy metadata; retain the chart-label hint.',
                                },
                                websites: {
                                    title: 'revisemy · website checkup',
                                    capture: 'Opening a design checkup on the Atlas marketing site…',
                                    hintBody: 'Hero subcopy is too light over the photo — darken it before shipping.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Keep “worth keeping” together — the break softens the promise.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'Primary and secondary CTAs share the same weight — make Explore lead.',
                                    mark2Meta: 'hierarchy · M2',
                                    markTarget: 'hero.headline',
                                    markSeverity: 'must-fix',
                                    markNote: 'Rebalance the headline so “worth keeping” lands on one line.',
                                    mark2Target: 'hero.actions',
                                    mark2Severity: 'hierarchy',
                                    mark2Note: 'Demote “Watch film” to a text link so Explore is the clear path.',
                                    packets: 'Changes requested — fix the headline wrap and CTA hierarchy; retain the contrast hint.',
                                },
                                presentations: {
                                    title: 'revisemy · presentation checkup',
                                    capture: 'Opening a design checkup on the quarterly deck…',
                                    hintBody: 'The chart depends on rose vs sky alone — add direct labels so the series survive projection.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Turn “Launch impact” into the takeaway: activation rose 28% after onboarding.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'The methodology note is presentation detail — move it to speaker notes.',
                                    mark2Meta: 'polish · M2',
                                    markTarget: 'slide.title',
                                    markSeverity: 'must-fix',
                                    markNote: 'Use the slide title to state the conclusion, not the topic.',
                                    mark2Target: 'slide.source-note',
                                    mark2Severity: 'polish',
                                    mark2Note: 'Move sample-size and date detail into speaker notes; keep a short source.',
                                    packets: 'Changes requested — rewrite the takeaway title, simplify the source, then label the chart.',
                                },
                                email: {
                                    title: 'revisemy · email template checkup',
                                    capture: 'Opening a design checkup on the launch email…',
                                    hintBody: 'Footer links are too low-contrast for inbox viewing — darken them before sending.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'The hero promise is generic — lead with the specific benefit readers will get.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: '“Learn more” hides the destination — make the primary action explicit.',
                                    mark2Meta: 'wording · M2',
                                    markTarget: 'email.hero',
                                    markSeverity: 'must-fix',
                                    markNote: 'Replace “A better way to work” with the concrete value of the new workspace.',
                                    mark2Target: 'email.primary-cta',
                                    mark2Severity: 'wording',
                                    mark2Note: 'Change the CTA to “Explore the new workspace” so readers know what opens.',
                                    packets: 'Changes requested — sharpen the hero promise and CTA; retain the footer contrast hint.',
                                }
                            },
                            get s() { return this.scenarios[this.scenario]; },
                            get progressPct() { return ((this.step + 1) / 5) * 100; },
                            start() {
                                this.stop();
                                this.paused = false;
                                if (this.reduced) { this.step = 4; return; }
                                this.timer = setInterval(() => {
                                    if (this.step >= 4) {
                                        this.advanceScenario();
                                        this.step = 0;
                                    } else {
                                        this.step += 1;
                                    }
                                }, this.stepMs);
                            },
                            stop() {
                                if (this.timer) { clearInterval(this.timer); this.timer = null; }
                                if (this.resumeTimer) { clearTimeout(this.resumeTimer); this.resumeTimer = null; }
                            },
                            advanceScenario() {
                                const i = this.order.indexOf(this.scenario);
                                this.scenario = this.order[(i + 1) % this.order.length];
                                this.cycleKey += 1;
                            },
                            setScenario(id) {
                                this.scenario = id;
                                this.step = this.reduced ? 4 : 0;
                                this.cycleKey += 1;
                                this.start();
                            },
                            goToStep(i) {
                                this.step = i;
                                this.stop();
                                this.paused = true;
                                if (this.reduced) return;
                                this.resumeTimer = setTimeout(() => this.start(), 6000);
                            }
                        }"
                        x-init="start(); $cleanup(() => stop())"
                    >
                        <div class="flex flex-col gap-2 border-b border-zinc-200 bg-zinc-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-zinc-300"></span>
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-zinc-300"></span>
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-zinc-300"></span>
                                <span class="ml-2 truncate font-mono text-[11px] text-zinc-400" x-text="s.title"></span>
                            </div>
                            <div class="flex flex-wrap gap-1 rounded-lg border border-zinc-200 bg-white p-0.5">
                                <template x-for="tab in [
                                    { id: 'product', label: 'Interfaces' },
                                    { id: 'websites', label: 'Websites' },
                                    { id: 'presentations', label: 'Presentations' },
                                    { id: 'email', label: 'Email' }
                                ]" :key="tab.id">
                                    <button
                                        type="button"
                                        class="relative overflow-hidden rounded-md px-2.5 py-1.5 text-[11px] font-medium transition"
                                        :class="scenario === tab.id ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-800'"
                                        x-on:click="setScenario(tab.id)"
                                    >
                                        <span x-text="tab.label"></span>
                                        <template x-for="k in (scenario === tab.id && !reduced ? [cycleKey] : [])" :key="tab.id + '-' + k">
                                            <span class="pointer-events-none absolute inset-x-1.5 bottom-0.5 h-[3px] overflow-hidden rounded-full bg-white/20">
                                                <span
                                                    class="block h-full rounded-full bg-rose-300"
                                                    x-init="$el.style.width = '0%'; requestAnimationFrame(() => { $el.style.width = progressPct + '%' })"
                                                    :style="{
                                                        width: progressPct + '%',
                                                        transition: paused ? 'none' : ('width ' + stepMs + 'ms linear')
                                                    }"
                                                ></span>
                                            </span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Checkpoint rail --}}
                        <div class="border-b border-zinc-200 bg-white px-2 py-2 sm:px-3">
                            <div class="flex gap-1 overflow-x-auto pb-0.5 sm:grid sm:grid-cols-5 sm:gap-1.5 sm:overflow-visible">
                                <template x-for="(cp, i) in [
                                    { label: '1 · Capture', sub: 'create_review', tone: 'zinc' },
                                    { label: '2 · Second opinion', sub: 'hints only', tone: 'sky' },
                                    { label: '3 · Your marks', sub: 'authoritative', tone: 'rose' },
                                    { label: '4 · Decide', sub: 'you own it', tone: 'amber' },
                                    { label: '5 · Work packets', sub: 'agent continues', tone: 'emerald' }
                                ]" :key="i">
                                    <button
                                        type="button"
                                        class="rm-checkpoint flex min-w-[7.5rem] flex-1 flex-col rounded-lg border px-2 py-1.5 text-left transition duration-300 sm:min-w-0"
                                        x-on:click="goToStep(i)"
                                        :class="{
                                            'border-zinc-900 bg-zinc-900 text-white shadow-sm scale-[1.02]': step === i && cp.tone === 'zinc',
                                            'border-sky-500 bg-sky-50 text-sky-900 shadow-sm shadow-sky-100 scale-[1.02]': step === i && cp.tone === 'sky',
                                            'border-rose-500 bg-rose-50 text-rose-900 shadow-sm shadow-rose-100 scale-[1.02]': step === i && cp.tone === 'rose',
                                            'border-amber-500 bg-amber-50 text-amber-950 shadow-sm shadow-amber-100 scale-[1.02]': step === i && cp.tone === 'amber',
                                            'border-emerald-500 bg-emerald-50 text-emerald-900 shadow-sm shadow-emerald-100 scale-[1.02]': step === i && cp.tone === 'emerald',
                                            'border-zinc-200 bg-zinc-50/80 text-zinc-400 hover:border-zinc-300': step !== i && step < i,
                                            'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300': step !== i && step > i
                                        }"
                                    >
                                        <span class="text-[10px] font-semibold leading-tight" x-text="cp.label"></span>
                                        <span
                                            class="mt-0.5 font-mono text-[8px] leading-tight"
                                            :class="step === i ? 'opacity-90' : 'opacity-70'"
                                            x-text="cp.sub"
                                        ></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="grid md:aspect-video md:grid-cols-[1.35fr_1fr]">
                            {{-- Left: scenario mock under review --}}
                            <div
                                class="rm-stage-ui relative min-h-[280px] border-b border-zinc-200 bg-[#f8f8f9] transition duration-500 md:min-h-0 md:border-b-0 md:border-r"
                            >
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

                                {{-- PRODUCT mock --}}
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-zinc-200/80 bg-white sm:inset-3" x-show="scenario === 'product'" x-cloak>
                                    <div class="flex h-full flex-col bg-zinc-50/70">
                                        <div class="flex h-8 shrink-0 items-center justify-between border-b border-zinc-200 bg-white px-3">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-4 w-4 items-center justify-center rounded bg-zinc-900 text-[7px] font-semibold text-white">N</span>
                                                <span class="text-[9px] font-semibold text-zinc-800">Northstar</span>
                                                <span class="text-[8px] text-zinc-300">/</span>
                                                <span class="text-[8px] text-zinc-500">Environments</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="hidden items-center gap-1 text-[7px] text-emerald-600 sm:flex"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>All systems operational</span>
                                                <span class="rounded-md bg-zinc-900 px-2 py-1 text-[8px] font-medium text-white">Deploy</span>
                                            </div>
                                        </div>

                                        <div class="flex min-h-0 flex-1">
                                            <aside class="hidden w-9 shrink-0 flex-col items-center gap-2.5 border-r border-zinc-200 bg-white py-3 sm:flex">
                                                <span class="h-4 w-4 rounded bg-rose-100 ring-1 ring-rose-200"></span>
                                                <span class="h-4 w-4 rounded bg-zinc-100"></span>
                                                <span class="h-4 w-4 rounded bg-zinc-100"></span>
                                                <span class="h-4 w-4 rounded bg-zinc-100"></span>
                                            </aside>

                                            <div class="min-w-0 flex-1 p-2.5 sm:p-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="relative">
                                                        <p class="text-[7px] font-medium uppercase tracking-[0.12em] text-zinc-400">Environment</p>
                                                        <div class="mt-0.5 flex items-center gap-1.5">
                                                            <h3 class="text-[13px] font-semibold tracking-tight text-zinc-900">Production</h3>
                                                            <span class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[6px] font-medium text-emerald-700">Healthy</span>
                                                        </div>
                                                        <p class="mt-0.5 text-[7px] text-zinc-400">Environment overview</p>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 z-[8] rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step >= 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop' : ''"
                                                        >
                                                            <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M1</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2 text-[7px] text-zinc-400">
                                                        <span>Last 24 hours</span>
                                                        <span class="rounded border border-zinc-200 bg-white px-1.5 py-0.5">•••</span>
                                                    </div>
                                                </div>

                                                <div class="mt-2 grid grid-cols-3 gap-1.5">
                                                    <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                        <p class="text-[6px] uppercase tracking-wide text-zinc-400">Requests</p>
                                                        <p class="mt-0.5 text-[11px] font-semibold text-zinc-800">1.84M</p>
                                                        <p class="text-[6px] text-emerald-600">↑ 12.4%</p>
                                                    </div>
                                                    <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                        <p class="text-[6px] uppercase tracking-wide text-zinc-400">Error rate</p>
                                                        <p class="mt-0.5 text-[11px] font-semibold text-zinc-800">0.06%</p>
                                                        <p class="text-[6px] text-zinc-400">Within target</p>
                                                    </div>
                                                    <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                        <p class="text-[6px] uppercase tracking-wide text-zinc-400">P95 latency</p>
                                                        <p class="mt-0.5 text-[11px] font-semibold text-zinc-800">182ms</p>
                                                        <p class="text-[6px] text-emerald-600">↓ 8ms</p>
                                                    </div>
                                                </div>

                                                <div class="mt-2 grid grid-cols-[1.25fr_0.9fr] gap-1.5">
                                                    <div class="relative rounded-md border border-zinc-200 bg-white p-2 shadow-sm">
                                                        <div class="flex items-center justify-between">
                                                            <p class="text-[7px] font-medium text-zinc-700">Traffic</p>
                                                            <div class="flex gap-1.5 text-[6px] text-zinc-400">
                                                                <span class="flex items-center gap-0.5"><span class="h-1 w-1 rounded-full bg-rose-400"></span>API</span>
                                                                <span class="flex items-center gap-0.5"><span class="h-1 w-1 rounded-full bg-sky-400"></span>Web</span>
                                                            </div>
                                                        </div>
                                                        <svg class="mt-1 h-10 w-full overflow-visible" viewBox="0 0 180 48" fill="none" aria-hidden="true">
                                                            <path d="M0 38H180M0 24H180M0 10H180" stroke="#f4f4f5" stroke-width="1"/>
                                                            <path d="M0 34C18 31 20 22 39 25C59 28 67 11 88 17C105 22 115 8 133 12C151 16 161 6 180 8" stroke="#fb7185" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M0 40C17 38 26 31 43 34C62 37 70 24 91 29C110 33 120 20 140 23C155 25 167 17 180 19" stroke="#7dd3fc" stroke-width="2" stroke-linecap="round"/>
                                                        </svg>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                            x-show="step >= 1"
                                                            x-transition.opacity
                                                            :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                        ></div>
                                                        <div
                                                            class="absolute -left-2 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white text-[8px] font-bold text-sky-700 shadow-sm"
                                                            x-show="step >= 1 && step < 4"
                                                            x-transition
                                                        >S1</div>
                                                    </div>

                                                    <div class="relative rounded-md border border-zinc-200 bg-white p-2 shadow-sm">
                                                        <div class="flex items-center justify-between">
                                                            <p class="text-[7px] font-medium text-zinc-700">Recent deploys</p>
                                                            <span class="text-[6px] text-zinc-400">View all</span>
                                                        </div>
                                                        <ul class="mt-1.5 space-y-1.5">
                                                            <li class="flex items-center gap-1">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-[7px] text-zinc-700">Checkout polish</p>
                                                                    <p class="text-[6px] text-zinc-400">2m ago</p>
                                                                </div>
                                                            </li>
                                                            <li class="flex items-center gap-1">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-[7px] text-zinc-700">Token onboarding</p>
                                                                    <p class="text-[6px] text-zinc-400">34m ago</p>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step >= 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                        >
                                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M2</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- WEBSITES mock --}}
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-zinc-200/80 bg-zinc-950 sm:inset-3" x-show="scenario === 'websites'" x-cloak>
                                    {{-- Photographic hero plane --}}
                                    <div class="absolute inset-0 bg-[linear-gradient(115deg,rgb(9_9_11/0.72)_0%,rgb(9_9_11/0.28)_48%,rgb(9_9_11/0.55)_100%),radial-gradient(ellipse_at_70%_40%,rgb(56_189_248/0.35),transparent_42%),radial-gradient(ellipse_at_30%_80%,rgb(16_185_129/0.28),transparent_38%),linear-gradient(160deg,#0c4a6e_0%,#134e4a_42%,#1c1917_100%)]"></div>
                                    <div class="absolute inset-x-0 bottom-0 h-24 bg-[linear-gradient(to_top,rgb(9_9_11/0.85),transparent)]"></div>
                                    {{-- Soft horizon / ridge silhouette --}}
                                    <svg class="pointer-events-none absolute inset-x-0 bottom-8 h-16 w-full text-zinc-950/40 sm:bottom-10 sm:h-20" viewBox="0 0 400 80" preserveAspectRatio="none" aria-hidden="true">
                                        <path fill="currentColor" d="M0 80V48c28-10 46-28 78-28s42 18 72 14 48-22 86-18 54 24 84 22 48-16 80-10v52H0Z"/>
                                    </svg>

                                    <div class="relative flex h-full flex-col">
                                        <nav class="flex items-center justify-between px-3.5 py-2.5 sm:px-5">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-white/30 text-[7px] font-semibold text-white">A</span>
                                                <span class="font-display text-[12px] font-medium tracking-tight text-white">Atlas</span>
                                            </div>
                                            <div class="flex items-center gap-3 text-[8px] text-white/65">
                                                <span class="hidden sm:inline">Routes</span>
                                                <span class="hidden sm:inline">Guides</span>
                                                <span class="hidden sm:inline">Journal</span>
                                                <span class="rounded-full border border-white/25 bg-white/10 px-2 py-0.5 text-white backdrop-blur-sm">Sign in</span>
                                            </div>
                                        </nav>

                                        <div class="grid min-h-0 flex-1 grid-cols-[1.15fr_0.85fr] items-end gap-3 px-3.5 pb-3 pt-1 sm:gap-4 sm:px-5 sm:pb-4">
                                            <div class="min-w-0 pb-1">
                                                <p class="text-[7px] font-medium uppercase tracking-[0.16em] text-emerald-300/90">Summer routes · 2026</p>
                                                <div class="relative mt-1.5 max-w-[14.5rem]">
                                                    <h3 class="font-display text-[clamp(1.15rem,2.6vw,1.65rem)] font-medium leading-[1.05] tracking-[-0.02em] text-white">
                                                        Places worth<br>
                                                        keeping.
                                                    </h3>
                                                    <div
                                                        class="pointer-events-none absolute -inset-1 z-[8] rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                        x-show="step >= 2"
                                                        x-transition
                                                        :class="step === 2 ? 'rm-pin-pop' : ''"
                                                    >
                                                        <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M1</span>
                                                    </div>
                                                </div>
                                                <div class="relative mt-2 max-w-[13.5rem]">
                                                    <p class="text-[9px] leading-relaxed text-white/35">Quiet coastlines, alpine passes, and the maps that help you find them again.</p>
                                                    <div
                                                        class="pointer-events-none absolute -inset-1 rounded-md border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                        x-show="step >= 1"
                                                        x-transition.opacity
                                                        :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                    ></div>
                                                    <div
                                                        class="absolute -left-2 -top-2 z-10 flex h-5 w-5 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white text-[8px] font-bold text-sky-700 shadow-sm"
                                                        x-show="step >= 1 && step < 4"
                                                        x-transition
                                                    >S1</div>
                                                </div>
                                                <div class="relative mt-3 inline-flex items-center gap-2">
                                                    <span class="rounded-md bg-white px-2.5 py-1.5 text-[9px] font-semibold text-zinc-900 shadow-sm">Explore routes</span>
                                                    <span class="rounded-md border border-white/40 bg-white/15 px-2.5 py-1.5 text-[9px] font-semibold text-white backdrop-blur-sm">Watch film</span>
                                                    <div
                                                        class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                        x-show="step >= 2"
                                                        x-transition
                                                        :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                    >
                                                        <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M2</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="relative mb-0.5 hidden min-w-0 sm:block">
                                                <div class="overflow-hidden rounded-lg border border-white/15 bg-white/10 shadow-[0_16px_32px_-18px_rgb(0_0_0/0.7)] backdrop-blur-md">
                                                    <div class="relative h-16 bg-[linear-gradient(145deg,#0369a1_0%,#0f766e_55%,#44403c_100%)]">
                                                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_40%,rgb(255_255_255/0.18),transparent_45%)]"></div>
                                                        <span class="absolute left-2 top-2 rounded bg-black/35 px-1.5 py-0.5 text-[6px] font-medium uppercase tracking-wide text-white/90">Featured</span>
                                                    </div>
                                                    <div class="space-y-1.5 p-2">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div>
                                                                <p class="text-[9px] font-semibold text-white">Big Sur overlook</p>
                                                                <p class="text-[7px] text-white/50">California · 3-day route</p>
                                                            </div>
                                                            <span class="text-[8px] font-medium text-emerald-300">4.9</span>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <span class="rounded-full bg-white/10 px-1.5 py-0.5 text-[6px] text-white/70">Coast</span>
                                                            <span class="rounded-full bg-white/10 px-1.5 py-0.5 text-[6px] text-white/70">Camping</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3 border-t border-white/10 bg-black/25 px-3.5 py-1.5 backdrop-blur-sm sm:px-5">
                                            <span class="text-[6px] font-medium uppercase tracking-[0.14em] text-white/40">Editors’ picks</span>
                                            <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden text-[7px] text-white/55">
                                                <span class="truncate">Amalfi dawn</span>
                                                <span class="h-0.5 w-0.5 shrink-0 rounded-full bg-white/30"></span>
                                                <span class="truncate">Patagonia traverse</span>
                                                <span class="h-0.5 w-0.5 shrink-0 rounded-full bg-white/30"></span>
                                                <span class="hidden truncate sm:inline">Kyoto alleys</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- PRESENTATIONS mock --}}
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-zinc-700 bg-[#202124] sm:inset-3" x-show="scenario === 'presentations'" x-cloak>
                                    <div class="flex h-full">
                                        <aside class="hidden w-[3.25rem] shrink-0 flex-col gap-2 border-r border-white/10 bg-[#17181a] p-1.5 sm:flex">
                                            <div class="relative aspect-video rounded border border-rose-400 bg-white p-1 shadow-sm">
                                                <div class="h-1 w-3 rounded-full bg-zinc-300"></div>
                                                <div class="mt-1 h-2.5 rounded-sm bg-gradient-to-r from-rose-200 to-sky-100"></div>
                                                <span class="absolute -left-1 top-1 text-[6px] text-zinc-500">3</span>
                                            </div>
                                            <div class="relative aspect-video rounded border border-white/10 bg-zinc-800 p-1">
                                                <div class="h-1 w-4 rounded-full bg-zinc-600"></div>
                                                <div class="mt-1 grid grid-cols-3 gap-px">
                                                    <span class="h-2 rounded-sm bg-zinc-700"></span>
                                                    <span class="h-2 rounded-sm bg-zinc-700"></span>
                                                    <span class="h-2 rounded-sm bg-zinc-700"></span>
                                                </div>
                                                <span class="absolute -left-1 top-1 text-[6px] text-zinc-500">4</span>
                                            </div>
                                            <div class="relative aspect-video rounded border border-white/10 bg-zinc-800 p-1">
                                                <div class="h-1 w-3 rounded-full bg-zinc-600"></div>
                                                <div class="mt-1 h-2.5 rounded-sm bg-zinc-700"></div>
                                                <span class="absolute -left-1 top-1 text-[6px] text-zinc-500">5</span>
                                            </div>
                                        </aside>

                                        <div class="flex min-w-0 flex-1 flex-col">
                                            <div class="flex h-6 items-center justify-between border-b border-white/10 px-2 text-[7px] text-zinc-400">
                                                <span>Q3 product review</span>
                                                <span class="rounded bg-white/5 px-1.5 py-0.5">Present</span>
                                            </div>
                                            <div class="flex flex-1 items-center justify-center p-2">
                                                <div class="relative aspect-video w-full max-w-[25rem] overflow-hidden rounded-sm bg-[#f8f7f4] px-4 py-3 shadow-[0_10px_30px_rgb(0_0_0/0.38)] sm:px-5">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="relative">
                                                            <p class="text-[7px] font-medium uppercase tracking-[0.16em] text-rose-500">Onboarding</p>
                                                            <h3 class="mt-0.5 text-[13px] font-semibold tracking-tight text-zinc-900 sm:text-[15px]">Launch impact</h3>
                                                            <div
                                                                class="pointer-events-none absolute -inset-1 z-[8] rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                                x-show="step >= 2"
                                                                x-transition
                                                                :class="step === 2 ? 'rm-pin-pop' : ''"
                                                            >
                                                                <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f8f7f4]">M1</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-[16px] font-semibold leading-none text-zinc-900 sm:text-[20px]">+28%</p>
                                                            <p class="mt-0.5 text-[7px] text-zinc-400">activation</p>
                                                        </div>
                                                    </div>

                                                    <div class="mt-2 grid grid-cols-[1fr_4.5rem] gap-3">
                                                        <div class="relative">
                                                            <div class="flex h-[4.5rem] items-end gap-1.5 border-b border-l border-zinc-200 px-1 pb-0.5">
                                                                <span class="h-[28%] flex-1 rounded-t-sm bg-sky-300"></span>
                                                                <span class="h-[38%] flex-1 rounded-t-sm bg-rose-300"></span>
                                                                <span class="h-[43%] flex-1 rounded-t-sm bg-sky-300"></span>
                                                                <span class="h-[58%] flex-1 rounded-t-sm bg-rose-300"></span>
                                                                <span class="h-[54%] flex-1 rounded-t-sm bg-sky-300"></span>
                                                                <span class="h-[82%] flex-1 rounded-t-sm bg-rose-300"></span>
                                                            </div>
                                                            <div class="mt-1 flex justify-between text-[6px] text-zinc-400">
                                                                <span>Apr</span><span>May</span><span>Jun</span>
                                                            </div>
                                                            <div
                                                                class="pointer-events-none absolute -inset-1 rounded-md border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                                x-show="step >= 1"
                                                                x-transition.opacity
                                                                :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                            ></div>
                                                            <div
                                                                class="absolute -left-2 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-[#f8f7f4] text-[8px] font-bold text-sky-700 shadow-sm"
                                                                x-show="step >= 1 && step < 4"
                                                                x-transition
                                                            >S1</div>
                                                        </div>
                                                        <div class="space-y-1.5">
                                                            <div class="rounded border border-zinc-200 bg-white p-1.5">
                                                                <p class="text-[6px] uppercase text-zinc-400">Completed</p>
                                                                <p class="mt-0.5 text-[10px] font-semibold text-zinc-800">4,821</p>
                                                            </div>
                                                            <div class="rounded border border-zinc-200 bg-white p-1.5">
                                                                <p class="text-[6px] uppercase text-zinc-400">Time saved</p>
                                                                <p class="mt-0.5 text-[10px] font-semibold text-zinc-800">12 min</p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="relative mt-1.5">
                                                        <p class="text-[6px] leading-tight text-zinc-400">Source: onboarding cohort, n=1,842 · Apr–Jun 2026 · excludes invited teammates and test workspaces</p>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step >= 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                        >
                                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f8f7f4]">M2</span>
                                                        </div>
                                                    </div>
                                                    <span class="absolute bottom-2 right-2 text-[6px] text-zinc-400">03</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- EMAIL mock --}}
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-zinc-300/80 bg-[#e9eaed] sm:inset-3" x-show="scenario === 'email'" x-cloak>
                                    <div class="flex h-full flex-col">
                                        <div class="flex h-7 shrink-0 items-center justify-between border-b border-zinc-300 bg-[#f7f7f8] px-3 text-[7px] text-zinc-500">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-zinc-700">Campaign preview</span>
                                                <span class="text-zinc-300">/</span>
                                                <span>Workspace launch</span>
                                            </div>
                                            <div class="flex items-center gap-1 rounded-md border border-zinc-200 bg-white p-0.5">
                                                <span class="rounded bg-zinc-900 px-1.5 py-0.5 text-white">Desktop</span>
                                                <span class="px-1.5 py-0.5">Mobile</span>
                                            </div>
                                        </div>

                                        <div class="min-h-0 flex-1 overflow-hidden p-2 sm:p-2.5">
                                            <div class="relative mx-auto flex h-full max-w-[20rem] flex-col overflow-hidden rounded-sm bg-white shadow-[0_8px_24px_rgb(39_39_42/0.16)]">
                                                <div class="flex shrink-0 items-center justify-between border-b border-zinc-100 px-3 py-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="flex h-4 w-4 items-center justify-center rounded bg-indigo-600 text-[7px] font-semibold text-white">N</span>
                                                        <span class="text-[9px] font-semibold text-zinc-800">Northstar</span>
                                                    </div>
                                                    <span class="text-[6px] text-zinc-400">View in browser</span>
                                                </div>

                                                <div class="bg-[radial-gradient(circle_at_88%_12%,rgb(196_181_253/0.65),transparent_34%),linear-gradient(135deg,#eef2ff_0%,#f5f3ff_55%,#ecfeff_100%)] px-4 py-3">
                                                    <span class="rounded-full border border-indigo-200 bg-white/70 px-1.5 py-0.5 text-[6px] font-medium uppercase tracking-[0.12em] text-indigo-600">New this week</span>
                                                    <div class="relative mt-2">
                                                        <h3 class="max-w-[13rem] text-[16px] font-semibold leading-[1.05] tracking-[-0.03em] text-zinc-900 sm:text-[18px]">
                                                            A better way<br>to work.
                                                        </h3>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step >= 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop' : ''"
                                                        >
                                                            <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M1</span>
                                                        </div>
                                                    </div>
                                                    <p class="mt-1.5 max-w-[14rem] text-[7px] leading-relaxed text-zinc-500">Bring projects, notes, and decisions into one calm workspace built for focus.</p>
                                                    <div class="relative mt-2 inline-flex">
                                                        <span class="rounded-md bg-indigo-600 px-2.5 py-1 text-[7px] font-semibold text-white shadow-sm">Learn more</span>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step >= 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                        >
                                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-white">M2</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-2 gap-2 px-4 py-2.5">
                                                    <div class="rounded-md border border-zinc-100 bg-zinc-50 p-2">
                                                        <span class="flex h-4 w-4 items-center justify-center rounded bg-rose-100 text-[7px] text-rose-600">✓</span>
                                                        <p class="mt-1 text-[7px] font-semibold text-zinc-700">Clear priorities</p>
                                                        <p class="mt-0.5 text-[6px] leading-snug text-zinc-400">See what needs attention next.</p>
                                                    </div>
                                                    <div class="rounded-md border border-zinc-100 bg-zinc-50 p-2">
                                                        <span class="flex h-4 w-4 items-center justify-center rounded bg-sky-100 text-[7px] text-sky-600">↗</span>
                                                        <p class="mt-1 text-[7px] font-semibold text-zinc-700">Fewer handoffs</p>
                                                        <p class="mt-0.5 text-[6px] leading-snug text-zinc-400">Keep every decision in context.</p>
                                                    </div>
                                                </div>

                                                <div class="relative mt-auto border-t border-zinc-100 px-4 py-2 text-center">
                                                    <p class="text-[5.5px] leading-relaxed text-zinc-300">Northstar, 123 Market Street · Unsubscribe · Email preferences</p>
                                                    <div
                                                        class="pointer-events-none absolute inset-x-3 inset-y-1 rounded border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                        x-show="step >= 1"
                                                        x-transition.opacity
                                                        :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                    ></div>
                                                    <div
                                                        class="absolute -left-1 -top-1 z-10 flex h-5 w-5 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white text-[8px] font-bold text-sky-700 shadow-sm"
                                                        x-show="step >= 1 && step < 4"
                                                        x-transition
                                                    >S1</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Legends --}}
                                <div
                                    class="absolute bottom-3 left-3 z-30 flex items-center gap-1.5 rounded-full border border-sky-300 bg-sky-50 px-2 py-1 text-[9px] font-medium text-sky-800 shadow-sm sm:bottom-4 sm:left-4"
                                    x-show="step === 1"
                                    x-transition
                                >
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full border border-dashed border-sky-500 text-[8px]">S</span>
                                    Hint — not a decision
                                </div>
                                <div
                                    class="absolute bottom-3 left-3 z-30 flex items-center gap-1.5 rounded-full border border-rose-300 bg-rose-50 px-2 py-1 text-[9px] font-medium text-rose-800 shadow-sm sm:bottom-4 sm:left-4"
                                    x-show="step === 2"
                                    x-transition
                                >
                                    Your marks
                                </div>

                                {{-- Annotation popover (M1) --}}
                                <div
                                    class="absolute bottom-[12%] left-[8%] z-30 w-[min(92%,230px)] rounded-lg border border-rose-200 bg-white p-2.5 shadow-xl sm:left-auto sm:right-[8%]"
                                    x-show="step >= 2 && step < 4"
                                    x-transition.opacity
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-mono text-[9px] text-zinc-400" x-text="s.markTarget"></p>
                                        <span class="rounded-full bg-rose-50 px-1.5 py-0.5 text-[8px] font-semibold uppercase text-rose-600" x-text="s.markSeverity"></span>
                                    </div>
                                    <p class="mt-1.5 text-[11px] leading-snug text-zinc-700" x-text="s.markNote"></p>
                                    <p class="mt-2 border-t border-zinc-100 pt-2 text-[10px] leading-snug text-zinc-500">
                                        <span class="font-semibold text-rose-600">M2</span>
                                        <span x-text="s.mark2Note"></span>
                                    </p>
                                </div>

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

                                <div
                                    class="rm-packet-chip absolute right-2 top-1/2 z-30 hidden -translate-y-1/2 rounded-full border border-sky-300 bg-sky-50 px-2 py-1 font-mono text-[8px] font-semibold text-sky-700 shadow-md md:block"
                                    x-show="step === 1"
                                    x-transition
                                >second opinion →</div>
                            </div>

                            {{-- Right: Agent chat --}}
                            <div
                                class="rm-stage-chat relative flex min-h-[240px] flex-col bg-zinc-50 transition duration-500 md:min-h-0"
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
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-zinc-600" x-text="s.capture"></p>
                                        <p class="mt-1 font-mono text-[9px] text-rose-600">create_review</p>
                                    </div>

                                    <div
                                        class="rm-msg max-w-[95%] rounded-lg rounded-tl-sm border px-2.5 py-2 transition"
                                        :class="step === 1 ? 'border-sky-300 bg-sky-50 ring-2 ring-sky-200' : 'border-sky-200/80 bg-sky-50/70 opacity-70'"
                                        x-show="step >= 1"
                                        x-transition
                                    >
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-sky-700">Second opinion</p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-sky-900/80" x-text="s.hintBody"></p>
                                        <p class="mt-1 font-mono text-[9px] text-sky-600" x-text="s.hintMeta"></p>
                                    </div>

                                    <div
                                        class="rm-msg ml-auto max-w-[92%] rounded-lg rounded-tr-sm border px-2.5 py-2 transition"
                                        :class="step === 2 ? 'border-rose-300 bg-rose-50 ring-2 ring-rose-200' : 'border-rose-200/80 bg-rose-50/70 opacity-70'"
                                        x-show="step >= 2"
                                        x-transition
                                    >
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-700">Your authority</p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-rose-950/85" x-text="s.markBody"></p>
                                        <p class="mt-1 font-mono text-[9px] text-rose-600" x-text="s.markMeta"></p>
                                        <p class="mt-1.5 border-t border-rose-200/60 pt-1.5 text-[10px] leading-relaxed text-rose-900/70" x-text="s.mark2Body"></p>
                                        <p class="mt-0.5 font-mono text-[9px] text-rose-500" x-text="s.mark2Meta"></p>
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
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-emerald-950/85" x-text="s.packets"></p>
                                        <p class="mt-1 font-mono text-[9px] text-emerald-600">next_action: apply_feedback</p>
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
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">One feedback loop for anything visual</h2>
                <ol class="mt-6 max-w-2xl space-y-3 text-[15px] leading-relaxed text-zinc-600">
                    <li><span class="font-medium text-zinc-900">1.</span> Ask your agent to open a review for anything you can screenshot.</li>
                    <li><span class="font-medium text-zinc-900">2.</span> It can add a second opinion before you look: useful hints, never decisions.</li>
                    <li><span class="font-medium text-zinc-900">3.</span> Open the review link, mark what matters, then approve or request changes.</li>
                    <li><span class="font-medium text-zinc-900">4.</span> Your feedback returns to the agent as clear, structured next steps.</li>
                    <li><span class="font-medium text-zinc-900">5.</span> Repeat until it feels right. You stay in charge every pass.</li>
                </ol>
                <p class="rm-note mt-6 inline max-w-2xl text-[15px] leading-relaxed text-zinc-700">
                    <span class="font-medium">Try saying:</span> “Run a design checkup” or “address my feedback.” ReviseMy handles the MCP handoff inside the agent workflow you already use.
                </p>
            </section>

            {{-- Not just marks --}}
            <section id="cloud" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">More than screenshot markup</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    Marks are the simple part you see. Behind them, ReviseMy keeps the screenshot, context, decisions, and next steps together so feedback does not get lost between you and your agent.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li><span class="font-medium text-zinc-800">Optional second opinion</span> catches accessibility, hierarchy, and polish issues before you review</li>
                    <li><span class="font-medium text-zinc-800">Agent-ready feedback</span> carries each mark, its priority, and the reason it matters</li>
                    <li><span class="font-medium text-zinc-800">Your decision stays final</span> because suggestions remain hints until you accept them</li>
                </ul>
            </section>

            {{-- How agents use it --}}
            <section id="agents" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">The technical handoff</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    When you finish a review, <code class="font-mono text-[13px] text-rose-600">get_review</code> gives your agent structured work packets and one clear <code class="font-mono text-[13px]">next_action</code>: wait, make changes, open another pass, or stop.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li>Marks include intent and priority: <span class="font-medium text-zinc-800">must-fix</span>, tweak, nit, question, or keep</li>
                    <li><code class="font-mono text-[13px]">second_opinion</code> findings stay suggestions unless you accept them</li>
                    <li>Requesting changes creates a linked follow-up with <code class="font-mono text-[13px] text-rose-600">create_review</code> and <code class="font-mono text-[13px]">parent_id</code></li>
                    <li>The MCP prompt <code class="font-mono text-[13px]">design_checkup_loop</code> can guide the full cycle</li>
                </ul>
            </section>

            {{-- Setup --}}
            <section id="setup" class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Try it with your agent</h2>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600">
                    Choose the app you already use. A free try token connects ReviseMy with no account required, so you can start reviewing from ChatGPT, Claude, Cursor, or VS Code.
                </p>

                @if (! $token)
                    <div class="mt-8">
                        <x-try-token-button />
                    </div>
                @else
                    <div
                        class="mt-8 space-y-5"
                        x-data="{ client: 'chatgpt' }"
                    >
                        <div class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1">
                            @foreach ([
                                'chatgpt' => 'ChatGPT',
                                'claude' => 'Claude',
                                'cursor' => 'Cursor',
                                'vscode' => 'VS Code',
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

            {{-- Challenge --}}
            <section id="challenge" class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
                <div class="max-w-2xl">
                    <div class="relative">
                        <div class="relative z-10 flex items-end gap-1">
                            <button
                                type="button"
                                class="rounded-t-lg border px-3 py-1.5 text-[11px] font-medium transition"
                                :class="mentionTab === 'challenge'
                                    ? 'relative z-[2] -mb-px rounded-bl-none border-zinc-900/10 border-b-white bg-white text-zinc-900'
                                    : 'border-transparent bg-zinc-100 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800'"
                                x-on:click="mentionTab = 'challenge'"
                            >
                                The challenge
                            </button>
                            <button
                                type="button"
                                class="rounded-t-lg border px-3 py-1.5 text-[11px] font-medium transition"
                                :class="mentionTab === 'derek'
                                    ? 'relative z-[2] -mb-px rounded-bl-none border-zinc-900/10 border-b-white bg-white text-zinc-900'
                                    : 'border-transparent bg-zinc-100 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800'"
                                x-on:click="mentionTab = 'derek'"
                            >
                                The challenger
                            </button>
                        </div>

                        <div
                            class="relative rounded-2xl rounded-tr-2xl rounded-b-2xl border border-zinc-900/10 bg-white shadow-sm"
                            :class="mentionTab === 'challenge' ? 'rounded-tl-none' : 'rounded-tl-md'"
                        >
                            <div class="grid [&>*]:col-start-1 [&>*]:row-start-1">
                                <div
                                    class="p-4 sm:p-5"
                                    :class="mentionTab === 'challenge' ? 'relative z-[1] visible' : 'invisible pointer-events-none'"
                                    :aria-hidden="mentionTab !== 'challenge'"
                                >
                                    <div class="flex gap-4">
                                        <img
                                            src="{{ asset('images/taylor-otwell-pink.png') }}"
                                            alt="Taylor Otwell"
                                            class="h-16 w-16 shrink-0 rounded-xl object-cover ring-1 ring-rose-200 sm:h-[4.5rem] sm:w-[4.5rem]"
                                            width="72"
                                            height="72"
                                        />
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-rose-500">The challenge</p>
                                            <p class="mt-1 text-base font-semibold leading-snug text-zinc-900">
                                                Taylor Otwell’s Laravel Cloud weekend challenge
                                            </p>
                                            <p class="mt-2 text-[15px] leading-relaxed text-zinc-600">
                                                “Best side project shipped on Laravel Cloud this weekend… reply with a laravel.cloud URL.”
                                            </p>
                                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-[11px]">
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
                                    class="p-4 sm:p-5"
                                    :class="mentionTab === 'derek' ? 'relative z-[1] visible' : 'invisible pointer-events-none'"
                                    :aria-hidden="mentionTab !== 'derek'"
                                >
                                    <div class="flex gap-4">
                                        <img
                                            src="{{ asset('images/derek-castelli.png') }}"
                                            alt="Derek Castelli"
                                            class="h-16 w-16 shrink-0 rounded-xl object-cover ring-1 ring-rose-200 bg-[#ffe8f1] sm:h-[4.5rem] sm:w-[4.5rem]"
                                            width="72"
                                            height="72"
                                        />
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-rose-500">Made by</p>
                                            <p class="mt-1 text-base font-semibold leading-snug text-zinc-900">
                                                Derek Castelli
                                            </p>
                                            <p class="mt-2 text-[15px] leading-relaxed text-zinc-600">
                                                ReviseMy was actually a
                                                <a
                                                    href="https://heyderekj.com/projects/revisemy/"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                                                >side project on the back burner</a>
                                                and I thought this was a good time to explore it on Laravel Cloud.
                                                As a full-time freelance web designer building apps on the side, I made it to pull design feedback into my agent workflow.
                                            </p>
                                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-[11px]">
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
            </section>

            <footer class="mt-20 border-t border-zinc-900/8 py-8 text-sm text-zinc-400">
                Open source · Laravel + Livewire Flux · Built for Laravel Cloud
            </footer>
        </main>
    </div>
</div>
