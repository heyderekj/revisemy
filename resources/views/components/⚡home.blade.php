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

    public ?string $claudeCodeCommand = null;

    public ?string $error = null;

    public function getTryToken(TryTokenService $tryTokens): void
    {
        $this->error = null;

        $key = 'try-token-web:'.request()->ip();

        try {
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $this->error = 'Slow down — try again in a minute.';

                return;
            }

            RateLimiter::hit($key, 60);

            $result = $tryTokens->create();
        } catch (\Throwable $e) {
            report($e);

            $this->error = 'Could not start a free try right now. On Laravel Cloud, attach Postgres and run migrations — SQLite does not persist across deploys.';

            return;
        }

        $this->token = $result['token'];
        $this->mcpUrl = $result['mcp_url'];
        $this->cursorConfigJson = json_encode($result['cursor_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->claudeDesktopConfigJson = json_encode($result['claude_desktop_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->claudeCodeCommand = $result['claude_code_command'];

        $this->dispatch('revisemy-try-setup-saved', payload: [
            'token' => $this->token,
            'mcpUrl' => $this->mcpUrl,
            'cursorConfigJson' => $this->cursorConfigJson,
            'claudeDesktopConfigJson' => $this->claudeDesktopConfigJson,
            'claudeCodeCommand' => $this->claudeCodeCommand,
        ]);

        $this->dispatch('scroll-to-setup');
    }

    public function restoreTryTokenSetup(
        string $token,
        string $mcpUrl,
        string $cursorConfigJson,
        string $claudeDesktopConfigJson,
        string $claudeCodeCommand,
    ): void {
        $this->token = $token;
        $this->mcpUrl = $mcpUrl;
        $this->cursorConfigJson = $cursorConfigJson;
        $this->claudeDesktopConfigJson = $claudeDesktopConfigJson;
        $this->claudeCodeCommand = $claudeCodeCommand;
        $this->error = null;
    }

    public function clearTryTokenSetup(): void
    {
        $this->token = null;
        $this->mcpUrl = null;
        $this->cursorConfigJson = null;
        $this->claudeDesktopConfigJson = null;
        $this->claudeCodeCommand = null;
        $this->error = null;

        $this->dispatch('revisemy-try-setup-cleared');
    }
};
?>

<div
    class="rm-wash relative min-h-screen"
    x-data="{
        mobileNav: false,
        pastHero: false,
        atSetup: false,
        get showMobileCta() { return this.pastHero && ! this.atSetup },
        initStickyCta() {
            const hero = document.getElementById('rm-hero-cta');
            const setup = document.getElementById('setup');
            if (hero) {
                // pastHero flips true only once the inline hero button fully leaves the viewport.
                new IntersectionObserver(
                    ([e]) => { this.pastHero = ! e.isIntersecting }
                ).observe(hero);
            }
            if (setup) {
                new IntersectionObserver(
                    ([e]) => { this.atSetup = e.isIntersecting },
                    { rootMargin: '0px 0px -20% 0px' }
                ).observe(setup);
            }
        }
    }"
    x-init="initStickyCta()"
    x-on:scroll-to-setup.window="$nextTick(() => document.getElementById('setup')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
>
    <div class="rm-grid pointer-events-none absolute inset-0"></div>

    <div class="relative z-10 mx-auto flex min-h-screen max-w-[1200px]">
        {{-- Agentation-style sidebar --}}
        <aside class="hidden w-[220px] shrink-0 flex-col border-r border-zinc-900/8 px-6 pb-8 pt-10 lg:flex lg:sticky lg:top-0 lg:h-screen lg:max-h-screen lg:self-start lg:overflow-y-auto">
            <a href="/" class="mt-[0.55rem] inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                <x-revisemy-logo class="!h-auto !w-[128px]" />
            </a>

            <nav class="mt-12 flex flex-1 flex-col gap-8 text-[14px]">
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Overview</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#top" class="transition hover:text-zinc-900">Home</a></li>
                        <li><a href="#how" class="transition hover:text-zinc-900">How it works</a></li>
                        <li><a href="#cloud" class="transition hover:text-zinc-900">Features</a></li>
                        <li><a href="#agents" class="transition hover:text-zinc-900">For agents</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Tools</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#setup" class="transition hover:text-zinc-900">Try with your agent</a></li>
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
                            <a href="https://github.com/sponsors/heyderekj" class="transition hover:text-zinc-900" target="_blank" rel="noreferrer">
                                Sponsor ↗
                            </a>
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
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo class="!h-auto !w-[128px]" />
                </a>
                <button type="button" class="text-sm text-zinc-600" x-on:click="mobileNav = !mobileNav">Menu</button>
            </div>
            <div x-show="mobileNav" x-cloak class="mb-8 space-y-2 text-sm text-zinc-600 lg:hidden">
                <a href="#how" class="block" x-on:click="mobileNav = false">How it works</a>
                <a href="#cloud" class="block" x-on:click="mobileNav = false">Features</a>
                <a href="#agents" class="block" x-on:click="mobileNav = false">For agents</a>
                <a href="#setup" class="block" x-on:click="mobileNav = false">Try with your agent</a>
                <a href="https://github.com/heyderekj/revisemy" target="_blank" rel="noreferrer" class="block">GitHub ↗</a>
            </div>

            {{-- Sticky try CTA (desktop only) until the setup section, where another try button lives --}}
            <div class="relative">
                <div class="pointer-events-none sticky top-5 z-30 hidden h-0 sm:block lg:top-6">
                    <div class="flex justify-end sm:pt-2">
                        <div class="pointer-events-auto">
                            <x-try-token-button />
                        </div>
                    </div>
                </div>

            {{-- Hero --}}
            <section class="rm-fade-up">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <h1 class="max-w-xl text-[clamp(2.4rem,5.5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-zinc-900">
                        <span class="rm-highlight">Visual feedback.</span>
                        <br>
                        <span class="sr-only">With your agent.</span>
                        <span aria-hidden="true">
                            With&nbsp;<span class="rm-agent-cycle">
                                @foreach (['ChatGPT', 'Claude', 'Cursor'] as $i => $label)
                                    <span class="rm-agent-cycle-item" style="--i: {{ $i }}">{{ $label }}.</span>
                                @endforeach
                            </span>
                        </span>
                    </h1>
                    {{-- Mobile: inline button as before. Desktop: invisible spacer keeps hero layout while the sticky one floats. --}}
                    <x-try-token-button id="rm-hero-cta" class="self-start sm:hidden" />
                    <div class="invisible hidden pointer-events-none self-start sm:mt-2 sm:block" aria-hidden="true">
                        <x-try-token-button />
                    </div>
                </div>

                <p class="rm-fade-up-delay mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Human-in-the-loop design review for agents. Capture UI, websites, decks, or email from a screenshot, URL, PDF, or HTML; mark what matters; track fixes on the board; and send clear next steps back over MCP on
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
                                    capture: 'Opening a design checkup on the Field Notes route page…',
                                    hintBody: 'The route description is too faint against the paper tone — increase its contrast before shipping.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Keep “through the coast” together — the line break interrupts the thought.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'The route link and field guide compete — make the guide the unmistakable next step.',
                                    mark2Meta: 'hierarchy · M2',
                                    markTarget: 'hero.headline',
                                    markSeverity: 'must-fix',
                                    markNote: 'Rebalance the headline so “through the coast” reads as one phrase.',
                                    mark2Target: 'hero.actions',
                                    mark2Severity: 'hierarchy',
                                    mark2Note: 'Keep “View route” as a quiet text link so the field guide remains primary.',
                                    packets: 'Changes requested — fix the headline wrap and action hierarchy; retain the contrast hint.',
                                },
                                presentations: {
                                    capture: 'Opening a design checkup on the growth review deck…',
                                    hintBody: 'The comparison bars still depend on color — label control and guided setup directly on the chart.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Name the comparison window in the title so the 28% lift is not read as permanent.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'The methodology note is presentation detail — move it to speaker notes.',
                                    mark2Meta: 'polish · M2',
                                    markTarget: 'slide.title',
                                    markSeverity: 'must-fix',
                                    markNote: 'Add “in the first 30 days” to make the result precise at a glance.',
                                    mark2Target: 'slide.source-note',
                                    mark2Severity: 'polish',
                                    mark2Note: 'Move sample-size and date detail into speaker notes; keep a short source.',
                                    packets: 'Changes requested — qualify the takeaway, simplify the source, then label the comparison.',
                                },
                                email: {
                                    capture: 'Opening a design checkup on the Friday Brief email…',
                                    hintBody: 'The legal and preference links are too faint for inbox viewing — darken them before sending.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Put the five-minute promise in the headline; it is the strongest reason to open the brief.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'Make the action personal and specific: “Open my Friday brief.”',
                                    mark2Meta: 'wording · M2',
                                    markTarget: 'email.hero',
                                    markSeverity: 'must-fix',
                                    markNote: 'Lead with “Your five-minute Friday brief” so the value is immediate.',
                                    mark2Target: 'email.primary-cta',
                                    mark2Severity: 'wording',
                                    mark2Note: 'Change the CTA to “Open my Friday brief” so readers know exactly what opens.',
                                    packets: 'Changes requested — sharpen the time-saving promise and CTA; retain the footer contrast hint.',
                                }
                            },
                            get s() { return this.scenarios[this.scenario]; },
                            get progressPct() { return ((this.step + 1) / 5) * 100; },
                            start() {
                                this.stop();
                                this.paused = false;
                                if (this.reduced) { this.step = 4; return; }
                                // Livewire's Alpine build has no $cleanup — keep one global timer so remounts cannot stack intervals.
                                if (window.__rmStageTimer) { clearInterval(window.__rmStageTimer); }
                                this.timer = window.__rmStageTimer = setInterval(() => {
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
                                if (window.__rmStageTimer) { clearInterval(window.__rmStageTimer); window.__rmStageTimer = null; }
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
                        x-init="start(); window.addEventListener('pagehide', () => stop(), { once: true })"
                    >
                        <div class="border-b border-zinc-200 bg-white px-3 py-2.5">
                            <div class="grid w-full grid-cols-4 gap-1 rounded-lg border border-zinc-200 bg-white p-0.5">
                                <template x-for="tab in [
                                    { id: 'product', label: 'Interfaces' },
                                    { id: 'websites', label: 'Websites' },
                                    { id: 'presentations', label: 'Presentations' },
                                    { id: 'email', label: 'Email' }
                                ]" :key="tab.id">
                                    <button
                                        type="button"
                                        class="relative min-w-0 overflow-hidden rounded-md px-1.5 pb-2.5 pt-2 text-xs font-medium transition sm:px-2.5 sm:pb-3 sm:pt-2.5 sm:text-sm"
                                        :class="scenario === tab.id ? 'bg-zinc-100 text-zinc-900 shadow-sm' : 'text-zinc-500 hover:bg-zinc-50 hover:text-zinc-800'"
                                        x-on:click="setScenario(tab.id)"
                                    >
                                        <span x-text="tab.label"></span>
                                        <template x-for="k in (scenario === tab.id && !reduced ? [cycleKey] : [])" :key="tab.id + '-' + k">
                                            <span class="pointer-events-none absolute inset-x-2 bottom-0.5 h-[3px] overflow-hidden rounded-full bg-zinc-200">
                                                <span
                                                    class="block h-full rounded-full bg-rose-500"
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

                        {{-- Quiet loop rail --}}
                        <div class="border-b border-zinc-200 bg-zinc-50/70 px-3 py-2.5 sm:px-4">
                            <div class="flex items-center justify-between gap-1">
                                <template x-for="(cp, i) in [
                                    { label: 'Capture' },
                                    { label: 'Second opinion' },
                                    { label: 'Your marks' },
                                    { label: 'Decide' },
                                    { label: 'Agent continues' }
                                ]" :key="i">
                                    <div class="contents">
                                        <button
                                            type="button"
                                            class="group flex shrink-0 items-center gap-1.5 text-left"
                                            x-on:click="goToStep(i)"
                                            :aria-label="`${i + 1}. ${cp.label}`"
                                        >
                                            <span
                                                class="flex h-5 w-5 items-center justify-center rounded-full border text-[9px] font-semibold transition"
                                                :class="step === i
                                                    ? 'rm-step-pop border-rose-500 bg-rose-500 text-white shadow-sm'
                                                    : (step > i ? 'border-zinc-300 bg-zinc-200 text-zinc-600' : 'border-zinc-200 bg-white text-zinc-400 group-hover:border-zinc-300')"
                                                x-text="i + 1"
                                            ></span>
                                            <span
                                                class="hidden text-[10px] font-medium transition sm:inline"
                                                :class="step === i ? 'text-zinc-900' : 'text-zinc-400'"
                                                x-text="cp.label"
                                            ></span>
                                        </button>
                                        <span
                                            x-show="i < 4"
                                            class="h-px min-w-2 flex-1 bg-zinc-200"
                                            aria-hidden="true"
                                        ></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="grid md:aspect-video md:grid-cols-[1.55fr_0.75fr]">
                            {{-- Left: scenario mock under review --}}
                            <div
                                class="rm-stage-ui relative min-h-[280px] border-b border-zinc-200 bg-[#f8f8f9] transition duration-500 md:min-h-0 md:border-b-0 md:border-r"
                            >
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
                                                            x-show="step === 2"
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
                                                            x-show="step === 1"
                                                            x-transition.opacity
                                                            :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                        ></div>
                                                        <div
                                                            class="absolute -left-2 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white text-[8px] font-bold text-sky-700 shadow-sm"
                                                            x-show="step === 1"
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
                                                            x-show="step === 2"
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
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-[#d8d0c3] bg-[#f3eee5] sm:inset-3" x-show="scenario === 'websites'" x-cloak>
                                    <div class="flex h-full flex-col text-[#1f2926]">
                                        <nav class="flex h-9 shrink-0 items-center justify-between border-b border-[#d8d0c3] px-3.5 sm:px-5">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2.5 w-2.5 rounded-full bg-[#c84b3f]"></span>
                                                <span class="text-[8px] font-semibold uppercase tracking-[0.2em]">Field / Notes</span>
                                            </div>
                                            <div class="flex items-center gap-3 text-[7px] font-medium text-[#5d6661]">
                                                <span class="hidden sm:inline">Routes</span>
                                                <span class="hidden sm:inline">Journal</span>
                                                <span class="border-b border-[#1f2926] pb-0.5 text-[#1f2926]">Field guides</span>
                                            </div>
                                        </nav>

                                        <div class="grid min-h-0 flex-1 grid-cols-[1.08fr_0.92fr]">
                                            <div class="flex min-w-0 flex-col justify-between px-3.5 py-3 sm:px-5 sm:py-4">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-[6px] font-semibold uppercase tracking-[0.2em] text-[#c84b3f]">Route 024 / Pacific edge</p>
                                                    <span class="text-[6px] text-[#7c827e]">36° 16′ N</span>
                                                </div>

                                                <div class="min-w-0">
                                                    <div class="relative max-w-[15rem]">
                                                        <h3 class="font-display text-[clamp(1.2rem,2.8vw,1.8rem)] font-medium leading-[0.98] tracking-[-0.025em] text-[#173f3b]">
                                                            A quieter way<br>
                                                            through the coast.
                                                        </h3>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 z-[8] rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step === 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop' : ''"
                                                        >
                                                            <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f3eee5]">M1</span>
                                                        </div>
                                                    </div>
                                                    <div class="relative mt-2 max-w-[13.5rem]">
                                                        <p class="text-[8px] leading-relaxed text-[#8b8d87]">Three unhurried days between redwood shade and the last light at Point Reyes.</p>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                            x-show="step === 1"
                                                            x-transition.opacity
                                                            :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                        ></div>
                                                        <div
                                                            class="absolute -left-2 -top-2 z-10 flex h-5 w-5 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-[#f3eee5] text-[8px] font-bold text-sky-700 shadow-sm"
                                                            x-show="step === 1"
                                                            x-transition
                                                        >S1</div>
                                                    </div>
                                                    <div class="relative mt-3 inline-flex items-center gap-2.5">
                                                        <span class="rounded-full bg-[#c84b3f] px-3 py-1.5 text-[8px] font-semibold text-white shadow-sm">Get the field guide</span>
                                                        <span class="border-b border-[#70817a] pb-0.5 text-[8px] font-medium text-[#315c56]">View route ↗</span>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step === 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                        >
                                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f3eee5]">M2</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2 text-[6px] text-[#777e79]">
                                                    <span class="font-semibold text-[#173f3b]">03 days</span>
                                                    <span class="h-px w-4 bg-[#b6b2aa]"></span>
                                                    <span>42 miles · moderate</span>
                                                </div>
                                            </div>

                                            <div class="relative min-w-0 overflow-hidden rounded-tl-[2.25rem] bg-[#164e4a]">
                                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_72%_24%,rgb(229_196_140/0.24),transparent_24%),linear-gradient(160deg,transparent_35%,rgb(8_47_43/0.8)_100%)]"></div>
                                                <svg class="absolute inset-0 h-full w-full text-[#8eb7a7]/35" viewBox="0 0 220 230" preserveAspectRatio="none" fill="none" aria-hidden="true">
                                                    <path d="M-20 35C34 3 70 80 126 46s77-9 119 19M-24 63c54-33 98 49 154 13s77-10 112 13M-18 94c52-29 96 40 150 9s76-8 108 15M-16 126c50-22 93 33 145 7s74-6 108 19M-12 160c46-18 88 24 138 5s72-3 106 22M-5 192c45-13 85 17 133 3s69 1 101 25" stroke="currentColor" stroke-width="1"/>
                                                </svg>
                                                <div class="absolute left-3 top-3 rounded-full border border-white/30 bg-[#103f3b]/70 px-2 py-1 text-[6px] font-semibold uppercase tracking-[0.14em] text-[#f2dfba] backdrop-blur-sm">Point Reyes</div>
                                                <div class="absolute bottom-3 left-3 right-3 border-t border-white/25 pt-2 text-white">
                                                    <p class="font-display text-[13px] leading-none">Sea, fog, redwood.</p>
                                                    <div class="mt-1.5 flex items-center justify-between text-[6px] text-white/60">
                                                        <span>Stop 02 / Limantour</span>
                                                        <span>08:14 PM</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- PRESENTATIONS mock --}}
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-zinc-700 bg-[#242522] sm:inset-3" x-show="scenario === 'presentations'" x-cloak>
                                    <div class="flex h-full">
                                        <aside class="hidden w-[3.25rem] shrink-0 flex-col gap-2 border-r border-white/10 bg-[#191a18] p-1.5 sm:flex">
                                            <div class="relative aspect-video rounded border border-[#d75b4e] bg-[#f4efe6] p-1 shadow-sm">
                                                <div class="h-1 w-3 rounded-full bg-[#d75b4e]"></div>
                                                <div class="mt-1 flex h-2.5 items-end gap-0.5">
                                                    <span class="h-1.5 flex-1 bg-[#1e5a54]"></span><span class="h-2.5 flex-1 bg-[#d75b4e]"></span>
                                                </div>
                                                <span class="absolute -left-1 top-1 text-[6px] text-zinc-500">7</span>
                                            </div>
                                            <div class="relative aspect-video rounded border border-white/10 bg-zinc-800 p-1">
                                                <div class="h-1 w-4 rounded-full bg-zinc-600"></div>
                                                <div class="mt-1 h-2.5 rounded-sm bg-[#325f59]"></div>
                                                <span class="absolute -left-1 top-1 text-[6px] text-zinc-500">8</span>
                                            </div>
                                            <div class="relative aspect-video rounded border border-white/10 bg-zinc-800 p-1">
                                                <div class="h-1 w-3 rounded-full bg-zinc-600"></div>
                                                <div class="mt-1 h-2.5 rounded-sm bg-zinc-700"></div>
                                                <span class="absolute -left-1 top-1 text-[6px] text-zinc-500">9</span>
                                            </div>
                                        </aside>

                                        <div class="flex min-w-0 flex-1 flex-col">
                                            <div class="flex h-6 items-center justify-between border-b border-white/10 px-2 text-[7px] text-zinc-400">
                                                <span>Growth review / June 2026</span>
                                                <span class="rounded-full border border-white/10 px-1.5 py-0.5 text-zinc-300">Present ↗</span>
                                            </div>
                                            <div class="flex flex-1 items-center justify-center p-2">
                                                <div class="relative aspect-video w-full max-w-[25rem] overflow-hidden rounded-sm bg-[#f4efe6] px-4 py-3 text-[#183b37] shadow-[0_10px_30px_rgb(0_0_0/0.4)] sm:px-5">
                                                    <div class="flex items-center justify-between text-[6px] font-semibold uppercase tracking-[0.16em]">
                                                        <span class="text-[#c84b3f]">Northstar / Growth</span>
                                                        <span class="text-[#7d817b]">07</span>
                                                    </div>

                                                    <div class="mt-3 grid grid-cols-[1.05fr_0.95fr] gap-4">
                                                        <div class="min-w-0">
                                                            <div class="relative">
                                                                <h3 class="font-display text-[15px] font-medium leading-[1.02] tracking-[-0.025em] text-[#183b37] sm:text-[18px]">
                                                                    Guided setup drove<br>a 28% activation lift.
                                                                </h3>
                                                            <div
                                                                class="pointer-events-none absolute -inset-1 z-[8] rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                                x-show="step === 2"
                                                                x-transition
                                                                :class="step === 2 ? 'rm-pin-pop' : ''"
                                                            >
                                                                    <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f4efe6]">M1</span>
                                                                </div>
                                                            </div>
                                                            <p class="mt-2 max-w-[11rem] text-[7px] leading-relaxed text-[#69736e]">
                                                                More people reached their first shared project when setup ended with one concrete action.
                                                            </p>
                                                            <div class="mt-3 flex gap-3">
                                                                <div>
                                                                    <p class="text-[11px] font-semibold leading-none text-[#183b37]">61%</p>
                                                                    <p class="mt-0.5 text-[5.5px] uppercase tracking-wide text-[#7d817b]">Guided</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-[11px] font-semibold leading-none text-[#69736e]">47%</p>
                                                                    <p class="mt-0.5 text-[5.5px] uppercase tracking-wide text-[#7d817b]">Control</p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="relative flex items-end gap-2 border-b border-[#c9c4ba] px-2 pb-0.5">
                                                            <div class="flex flex-1 flex-col items-center gap-1">
                                                                <span class="text-[6px] font-semibold text-[#69736e]">47%</span>
                                                                <span class="h-[3.1rem] w-full bg-[#7fa49b]"></span>
                                                            </div>
                                                            <div class="flex flex-1 flex-col items-center gap-1">
                                                                <span class="text-[6px] font-semibold text-[#c84b3f]">61%</span>
                                                                <span class="h-[4.25rem] w-full bg-[#c84b3f]"></span>
                                                            </div>
                                                            <div
                                                                class="pointer-events-none absolute -inset-1 rounded-md border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                                x-show="step === 1"
                                                                x-transition.opacity
                                                                :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                            ></div>
                                                            <div
                                                                class="absolute -left-2 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-[#f4efe6] text-[8px] font-bold text-sky-700 shadow-sm"
                                                                x-show="step === 1"
                                                                x-transition
                                                            >S1</div>
                                                        </div>
                                                    </div>

                                                    <div class="relative mt-2 border-t border-[#d8d2c8] pt-1.5">
                                                        <p class="text-[5.5px] leading-tight text-[#8b8c86]">Source: new-workspace cohort, n=1,842 · 30-day window · Apr–Jun 2026 · invited teammates excluded</p>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step === 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                        >
                                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f4efe6]">M2</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- EMAIL mock --}}
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-[#d4d0c8] bg-[#e8e5df] sm:inset-3" x-show="scenario === 'email'" x-cloak>
                                    <div class="flex h-full flex-col">
                                        <div class="flex h-7 shrink-0 items-center justify-between border-b border-[#d4d0c8] bg-[#f6f4ef] px-3 text-[7px] text-[#6c6d68]">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-[#303633]">Campaign preview</span>
                                                <span class="text-[#b1afa9]">/</span>
                                                <span>The Friday Brief · 07</span>
                                            </div>
                                            <div class="flex items-center gap-1 rounded-md border border-[#d8d4cc] bg-white p-0.5">
                                                <span class="rounded bg-[#224e48] px-1.5 py-0.5 text-white">Desktop</span>
                                                <span class="px-1.5 py-0.5">Mobile</span>
                                            </div>
                                        </div>

                                        <div class="min-h-0 flex-1 overflow-hidden p-2 sm:p-2.5">
                                            <div class="relative mx-auto flex h-full max-w-[19rem] flex-col overflow-hidden rounded-sm bg-[#fbfaf7] shadow-[0_8px_24px_rgb(55_54_49/0.16)]">
                                                <div class="flex shrink-0 items-center justify-between border-b border-[#ebe7df] px-3 py-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-[#c84b3f]"></span>
                                                        <span class="text-[8px] font-semibold uppercase tracking-[0.14em] text-[#243d39]">Northstar</span>
                                                    </div>
                                                    <span class="text-[6px] text-[#8d8d87]">Friday, June 19</span>
                                                </div>

                                                <div class="border-b border-[#e4ded4] bg-[#f1eadd] px-4 py-3">
                                                    <p class="text-[6px] font-semibold uppercase tracking-[0.18em] text-[#c84b3f]">The Friday Brief / Issue 07</p>
                                                    <div class="relative mt-2">
                                                        <h3 class="max-w-[14rem] font-display text-[16px] font-medium leading-[1.02] tracking-[-0.025em] text-[#183b37] sm:text-[18px]">
                                                            Start Monday with<br>fewer loose ends.
                                                        </h3>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step === 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop' : ''"
                                                        >
                                                            <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f1eadd]">M1</span>
                                                        </div>
                                                    </div>
                                                    <p class="mt-1.5 max-w-[14rem] text-[7px] leading-relaxed text-[#67716d]">Five minutes to close open decisions, reset priorities, and hand next week a cleaner start.</p>
                                                    <div class="relative mt-2 inline-flex">
                                                        <span class="rounded-full bg-[#c84b3f] px-2.5 py-1.5 text-[7px] font-semibold text-white shadow-sm">Open this week’s brief →</span>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step === 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                        >
                                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-[#f1eadd]">M2</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="px-4 py-2.5">
                                                    <div class="flex items-start gap-2 border-b border-[#ece8e0] pb-2">
                                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#dce8e3] text-[6px] font-semibold text-[#225c54]">03</span>
                                                        <div class="min-w-0">
                                                            <p class="text-[7px] font-semibold text-[#303d39]">Decisions waiting on you</p>
                                                            <p class="mt-0.5 truncate text-[6px] text-[#878983]">Pricing page · Q3 narrative · Research plan</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-start gap-2 pt-2">
                                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#f2ddd8] text-[6px] font-semibold text-[#a84439]">05</span>
                                                        <div class="min-w-0">
                                                            <p class="text-[7px] font-semibold text-[#303d39]">Priorities for next week</p>
                                                            <p class="mt-0.5 truncate text-[6px] text-[#878983]">Launch QA · Customer calls · Team retro</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="relative mt-auto border-t border-[#ece8e0] px-4 py-2 text-center">
                                                    <p class="text-[5.5px] leading-relaxed text-[#c5c3bd]">Northstar, 123 Market Street · Unsubscribe · Email preferences</p>
                                                    <div
                                                        class="pointer-events-none absolute inset-x-3 inset-y-1 rounded border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                        x-show="step === 1"
                                                        x-transition.opacity
                                                        :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                    ></div>
                                                    <div
                                                        class="absolute -left-1 -top-1 z-10 flex h-5 w-5 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white text-[8px] font-bold text-sky-700 shadow-sm"
                                                        x-show="step === 1"
                                                        x-transition
                                                    >S1</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            {{-- Right: one active handoff at a time --}}
                            <div class="relative flex min-h-[220px] flex-col bg-white p-4 md:min-h-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[9px] font-semibold uppercase tracking-[0.14em] text-zinc-400">Current handoff</p>
                                    <span class="font-mono text-[9px] text-zinc-400" x-text="`${step + 1} / 5`"></span>
                                </div>

                                <div class="flex min-h-0 flex-1 items-center py-4">
                                    <div class="grid min-h-[10.5rem] w-full items-center [&>*]:col-start-1 [&>*]:row-start-1">
                                        <div
                                            class="rounded-xl border border-zinc-200 bg-zinc-50 p-3"
                                            x-show="step === 0"
                                            x-transition:enter="transition-[opacity,transform,filter] duration-[380ms] [transition-timing-function:cubic-bezier(0.22,1,0.36,1)]"
                                            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.97] blur-[2px]"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave="transition-[opacity,transform,filter] duration-150 ease-out"
                                            x-transition:leave-start="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.99] blur-[1px]"
                                        >
                                            <p class="text-[10px] font-semibold text-zinc-800">Review opened</p>
                                            <p class="mt-1.5 text-[11px] leading-relaxed text-zinc-600" x-text="s.capture"></p>
                                            <p class="mt-2 font-mono text-[9px] text-rose-600">create_review</p>
                                        </div>

                                        <div
                                            class="rounded-xl border border-sky-200 bg-sky-50/70 p-3"
                                            x-show="step === 1"
                                            x-transition:enter="transition-[opacity,transform,filter] duration-[380ms] [transition-timing-function:cubic-bezier(0.22,1,0.36,1)]"
                                            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.97] blur-[2px]"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave="transition-[opacity,transform,filter] duration-150 ease-out"
                                            x-transition:leave-start="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.99] blur-[1px]"
                                        >
                                            <div class="flex items-center gap-1.5">
                                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-dashed border-sky-500 text-[7px] font-semibold text-sky-700">S</span>
                                                <p class="text-[10px] font-semibold text-sky-800">Second opinion</p>
                                            </div>
                                            <p class="mt-2 text-[11px] leading-relaxed text-sky-950/75" x-text="s.hintBody"></p>
                                            <p class="mt-2 text-[9px] font-medium text-sky-600">A hint, not a decision</p>
                                        </div>

                                        <div
                                            class="rounded-xl border border-rose-200 bg-rose-50/70 p-3"
                                            x-show="step === 2"
                                            x-transition:enter="transition-[opacity,transform,filter] duration-[380ms] [transition-timing-function:cubic-bezier(0.22,1,0.36,1)]"
                                            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.97] blur-[2px]"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave="transition-[opacity,transform,filter] duration-150 ease-out"
                                            x-transition:leave-start="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.99] blur-[1px]"
                                        >
                                            <p class="text-[10px] font-semibold text-rose-800">Your marks</p>
                                            <p class="mt-2 text-[11px] leading-relaxed text-rose-950/80" x-text="s.markBody"></p>
                                            <p class="mt-1.5 font-mono text-[9px] text-rose-600" x-text="s.markMeta"></p>
                                            <p class="mt-2 border-t border-rose-200/70 pt-2 text-[10px] leading-relaxed text-rose-900/70" x-text="s.mark2Body"></p>
                                        </div>

                                        <div
                                            class="rounded-xl border border-zinc-200 bg-zinc-50 p-3"
                                            x-show="step === 3"
                                            x-transition:enter="transition-[opacity,transform,filter] duration-[380ms] [transition-timing-function:cubic-bezier(0.22,1,0.36,1)]"
                                            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.97] blur-[2px]"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave="transition-[opacity,transform,filter] duration-150 ease-out"
                                            x-transition:leave-start="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.99] blur-[1px]"
                                        >
                                            <p class="text-[10px] font-semibold text-zinc-800">You decide</p>
                                            <p class="mt-1.5 text-[11px] leading-relaxed text-zinc-600">Approve or request changes. ReviseMy waits for your call.</p>
                                            <p class="mt-2 font-mono text-[9px] text-zinc-400">wait_for_human</p>
                                        </div>

                                        <div
                                            class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3"
                                            x-show="step === 4"
                                            x-transition:enter="transition-[opacity,transform,filter] duration-[380ms] [transition-timing-function:cubic-bezier(0.22,1,0.36,1)]"
                                            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.97] blur-[2px]"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave="transition-[opacity,transform,filter] duration-150 ease-out"
                                            x-transition:leave-start="opacity-100 translate-y-0 scale-100 blur-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.99] blur-[1px]"
                                        >
                                            <p class="text-[10px] font-semibold text-emerald-800">Agent continues</p>
                                            <p class="mt-1.5 text-[11px] leading-relaxed text-emerald-950/75" x-text="s.packets"></p>
                                            <p class="mt-2 font-mono text-[9px] text-emerald-600">next_action: apply_feedback</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 border-t border-zinc-100 pt-3 text-[9px] text-zinc-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Connected over MCP</span>
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
                    <li><span class="font-medium text-zinc-900">1.</span> Ask your agent to open a review — from screenshots, or by capturing a page URL, PDF deck, or email HTML.</li>
                    <li><span class="font-medium text-zinc-900">2.</span> An optional second opinion can land first: useful hints, never decisions.</li>
                    <li><span class="font-medium text-zinc-900">3.</span> Open the review link, mark regions, comment, and share a guest link when you want another set of eyes.</li>
                    <li><span class="font-medium text-zinc-900">4.</span> Track marks on the board (open → resolved → verified). Agents can attach before/after evidence when they fix something.</li>
                    <li><span class="font-medium text-zinc-900">5.</span> Approve or request changes. Structured next steps return over MCP — repeat until it feels right.</li>
                </ol>
                <p class="mt-6 max-w-2xl text-[15px] leading-relaxed text-zinc-700">
                    <span class="rm-note box-decoration-clone">
                        <span class="font-medium">Try saying:</span> “Run a design checkup,” “review this URL,” or “address my feedback.” ReviseMy handles the MCP handoff inside the agent workflow you already use.
                    </span>
                </p>
            </section>

            {{-- Feature grid --}}
            <section id="cloud" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Built for the full checkup loop</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    Marks are what you see. Behind them, ReviseMy keeps captures, review type, guest feedback, and lifecycle together so nothing gets lost between passes.
                </p>

                <div class="mt-8 grid gap-x-8 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
                    <article>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                            <flux:icon.photo variant="micro" class="size-[18px]" />
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">UI, websites, decks, and email</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Each review type gets its own checklist and vision lens tuned for sites, decks, or email.</p>
                    </article>

                    <article>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                            <flux:icon.computer-desktop variant="micro" class="size-[18px]" />
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Capture without screenshots</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Agents can send a page URL (mobile + desktop), a PDF deck, or raw email HTML — ReviseMy renders the shots for you.</p>
                    </article>

                    <article>
                        <x-mark-type-icon type="m" />
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Precise marks and priorities</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Point or outline the exact area, set must-fix / tweak / question / keep, and keep a threaded comment thread on each mark.</p>
                    </article>

                    <article>
                        <x-mark-type-icon type="s" />
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Second opinion</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Cloud-queued design hints before you look — with optional Claude, OpenAI, or local Ollama vision.</p>
                    </article>

                    <article>
                        <x-mark-type-icon type="g" />
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Shareable guest links</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Share a private link so clients or teammates can suggest changes — no accounts. Your marks stay authoritative.</p>
                    </article>

                    <article>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                            <flux:icon.check variant="micro" class="size-[18px]" />
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Track every mark to done</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">A board moves marks from open to resolved to verified, so you always know what is left.</p>
                    </article>
                </div>
            </section>

            {{-- How agents use it --}}
            <section id="agents" class="mt-16 scroll-mt-8 sm:mt-20">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">The technical handoff</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    <code class="font-mono text-[13px] text-rose-600">create_review</code> accepts images, a capture URL, PDF, or HTML. When you finish,
                    <code class="font-mono text-[13px] text-rose-600">get_review</code> returns work packets and one clear <code class="font-mono text-[13px]">next_action</code>: wait, apply marks, open another pass, or stop.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li>Marks include intent and priority: <span class="font-medium text-zinc-800">must-fix</span>, tweak, nit, question, or keep</li>
                    <li><code class="font-mono text-[13px]">second_opinion</code> and guest findings stay suggestions until you accept them</li>
                    <li><code class="font-mono text-[13px] text-rose-600">resolve_marks</code> can close marks with notes and optional before/after evidence</li>
                    <li>Requesting changes links a follow-up pass via <code class="font-mono text-[13px] text-rose-600">create_review</code> + <code class="font-mono text-[13px]">parent_id</code></li>
                    <li>The MCP prompt <code class="font-mono text-[13px]">design_checkup_loop</code> can guide the full cycle</li>
                </ul>
            </section>
            </div>

            {{-- Setup --}}
            <section
                id="setup"
                class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16"
                x-data
                x-on:revisemy-try-setup-saved.window="sessionStorage.setItem('revisemy_try_setup', JSON.stringify($event.detail.payload))"
                x-on:revisemy-try-setup-cleared.window="sessionStorage.removeItem('revisemy_try_setup')"
                x-init="
                    const raw = sessionStorage.getItem('revisemy_try_setup');
                    if (raw && ! @js((bool) $token)) {
                        try {
                            const d = JSON.parse(raw);
                            if (d.token) {
                                $wire.restoreTryTokenSetup(
                                    d.token,
                                    d.mcpUrl ?? '',
                                    d.cursorConfigJson ?? '',
                                    d.claudeDesktopConfigJson ?? '',
                                    d.claudeCodeCommand ?? '',
                                );
                            }
                        } catch (e) {}
                    }
                "
            >
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Try it with your agent</h2>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600">
                    Choose the app you already use. Try free — no account required — and start reviewing from ChatGPT, Claude, or Cursor.
                </p>

                @if ($error)
                    <p class="mt-4 text-sm text-rose-600">{{ $error }}</p>
                @endif

                @if (! $token)
                    <div class="mt-8">
                        <x-try-token-button />
                    </div>
                @else
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            class="text-sm text-zinc-500 transition hover:text-zinc-800"
                            wire:click="clearTryTokenSetup"
                        >Start over</button>
                    </div>
                    <div
                        class="mt-4 space-y-5"
                        x-data="{ client: 'chatgpt' }"
                    >
                        <div class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1">
                            @foreach ([
                                'chatgpt' => 'ChatGPT',
                                'claude' => 'Claude',
                                'cursor' => 'Cursor',
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
                <div class="max-w-xl">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">From back burner to weekend ship</h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        ReviseMy sat as a
                        <a
                            href="https://heyderekj.com/projects/revisemy/"
                            target="_blank"
                            rel="noreferrer"
                            class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >side project on the back burner</a>
                        since September 2024, when it was just an idea and a Figma file. Originally I imagined it as my version of a productized design feedback service — inspired by Roasti, now defunct. I love giving feedback. Not to be a dick, but to be a Derek.
                    </p>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        Then
                        <a
                            href="https://x.com/taylorotwell/status/2075667366646858222"
                            target="_blank"
                            rel="noreferrer"
                            class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >Taylor Otwell’s Laravel Cloud weekend challenge</a>
                        asked for the best side project shipped that weekend. That was the nudge to try Laravel and create an MCP.
                    </p>
                    <p class="rm-note mt-6 inline text-[15px] leading-relaxed text-zinc-700">
                        “Best side project shipped on Laravel Cloud this weekend… reply with a laravel.cloud URL.”
                        <a
                            href="https://x.com/heyderekj/status/2075675582973501792"
                            target="_blank"
                            rel="noreferrer"
                            class="ml-1 font-medium text-rose-600 transition hover:text-rose-500"
                        >GAME ON. ↗</a>
                    </p>
                </div>
            </section>

            <footer class="mt-20 border-t border-zinc-900/8 py-10 text-sm text-zinc-500">
                <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
                    <div class="max-w-sm space-y-3">
                        <p class="text-zinc-400">
                            Open source · Laravel + Livewire Flux · Built for Laravel Cloud
                        </p>
                        <a
                            href="https://github.com/sponsors/heyderekj"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center gap-2 font-medium text-zinc-700 transition hover:text-rose-600"
                        >
                            <span class="inline-flex size-5 items-center justify-center rounded-full bg-rose-50 text-rose-500" aria-hidden="true">
                                <svg class="size-3" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                    <path d="M8 14.25c-.2 0-.4-.06-.57-.18C5.6 12.7 2 9.72 2 6.4 2 4.3 3.6 2.75 5.7 2.75c1.1 0 2.1.5 2.8 1.35A3.8 3.8 0 0 1 11.3 2.75C13.4 2.75 15 4.3 15 6.4c0 3.32-3.6 6.3-5.43 7.67A.9.9 0 0 1 8 14.25Z"/>
                                </svg>
                            </span>
                            Sponsor on GitHub
                        </a>
                    </div>

                    <div>
                        <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Also by Derek</p>
                        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-zinc-600 sm:justify-end">
                            <li>
                                <a href="https://harvous.com" target="_blank" rel="noreferrer" class="transition hover:text-zinc-900">
                                    Harvous ↗
                                </a>
                            </li>
                            <li>
                                <a href="https://dinkyfiles.com" target="_blank" rel="noreferrer" class="transition hover:text-zinc-900">
                                    Dinky ↗
                                </a>
                            </li>
                            <li>
                                <a href="https://binkyfiles.com" target="_blank" rel="noreferrer" class="transition hover:text-zinc-900">
                                    Binky ↗
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </footer>
        </main>
    </div>

    {{-- Mobile sticky try CTA: appears after the hero button scrolls away, hides at the setup section --}}
    <div
        class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] sm:hidden"
        x-show="showMobileCta"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-3 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-3 opacity-0"
    >
        <x-try-token-button class="w-full justify-center !py-3 shadow-[0_16px_40px_-12px_rgba(225,29,72,0.6)]" />
    </div>
</div>
