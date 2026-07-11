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
                        <li><a href="#features" class="transition hover:text-zinc-900">Features</a></li>
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
                <a href="#features" class="block" x-on:click="mobileNav = false">Features</a>
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
                                    capture: 'Opening a design checkup on the West 04 pollination run…',
                                    hintBody: 'The blossom-rate chart has no target line, so operators cannot tell whether either Pip is on pace.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Add the 05:51 sunrise cutoff to the run header so the remaining window is visible.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'Pip 12 is “mapping,” but the row it is preparing is not named.',
                                    mark2Meta: 'content · M2',
                                    markTarget: 'run.header',
                                    markSeverity: 'must-fix',
                                    markNote: 'Surface “sunrise cutoff 05:51” beside the shift start time.',
                                    mark2Target: 'robot.row',
                                    mark2Severity: 'content',
                                    mark2Note: 'Change the state to “mapping Row 17” so the next move is traceable.',
                                    packets: 'Changes requested — add the run cutoff and mapping destination; retain the target-line hint.',
                                },
                                websites: {
                                    capture: 'Opening a design checkup on the Thrum Robotics product page…',
                                    hintBody: 'The supporting copy explains the motion but buries the labor outcome growers care about.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Keep “before sunrise” together — the line break weakens the overnight promise.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'The run report and field demo compete — make booking the live demo the unmistakable next step.',
                                    mark2Meta: 'hierarchy · M2',
                                    markTarget: 'hero.headline',
                                    markSeverity: 'must-fix',
                                    markNote: 'Rebalance the headline so “before sunrise” reads as one phrase.',
                                    mark2Target: 'hero.actions',
                                    mark2Severity: 'hierarchy',
                                    mark2Note: 'Keep “Read run 118” as a quiet text link so the field demo remains primary.',
                                    packets: 'Changes requested — fix the promise wrap and action hierarchy; sharpen the labor outcome.',
                                },
                                presentations: {
                                    capture: 'Opening a design checkup on the harvest review deck…',
                                    hintBody: 'The comparison has no visible baseline, making the 13-point difference look larger than it is.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Name the harvest window in the title so the 18% yield lift is not read as a permanent baseline.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'The methodology note is presentation detail — move it to speaker notes.',
                                    mark2Meta: 'polish · M2',
                                    markTarget: 'slide.title',
                                    markSeverity: 'must-fix',
                                    markNote: 'Add “across the June harvest” to make the result precise at a glance.',
                                    mark2Target: 'slide.source-note',
                                    mark2Severity: 'polish',
                                    mark2Note: 'Move sample-size and date detail into speaker notes; keep a short source.',
                                    packets: 'Changes requested — qualify the harvest takeaway, simplify the source, then clarify the chart baseline.',
                                },
                                email: {
                                    capture: 'Opening a design checkup on the West 04 dawn report…',
                                    hintBody: 'The footer actions are packed too tightly for a mobile inbox — add clearer separation.',
                                    hintMeta: 'finding: a11y · hint',
                                    markBody: 'Put the 94.2% contact rate in the headline; blossom visits alone do not show pollination quality.',
                                    markMeta: 'must-fix · M1',
                                    mark2Body: 'Name the destination and greenhouse: “Open the West 04 run.”',
                                    mark2Meta: 'wording · M2',
                                    markTarget: 'email.hero',
                                    markSeverity: 'must-fix',
                                    markNote: 'Pair the visit count with contact rate so the result is meaningful.',
                                    mark2Target: 'email.primary-cta',
                                    mark2Severity: 'wording',
                                    mark2Note: 'Change the CTA to “Open the West 04 run” so operators know exactly what opens.',
                                    packets: 'Changes requested — add the quality signal and clarify the CTA; give footer actions more space.',
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
                                                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-thrum-pollen text-[7px] font-bold text-thrum-ink">T</span>
                                                <span class="text-[9px] font-semibold text-zinc-800">Thrum Robotics</span>
                                                <span class="text-[8px] text-zinc-300">/</span>
                                                <span class="text-[8px] text-zinc-500">Glasshouse fleet</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="hidden items-center gap-1 text-[7px] text-thrum-teal sm:flex"><span class="h-1.5 w-1.5 rounded-full bg-thrum-teal"></span>Night shift active</span>
                                                <span class="rounded-md bg-thrum-ink px-2 py-1 text-[8px] font-medium text-white">Dispatch</span>
                                            </div>
                                        </div>

                                        <div class="flex min-h-0 flex-1">
                                            <aside class="hidden w-9 shrink-0 flex-col items-center gap-2.5 border-r border-zinc-200 bg-white py-3 sm:flex">
                                                <span class="h-4 w-4 rounded-md bg-thrum-teal-soft ring-1 ring-thrum-teal/30"></span>
                                                <span class="h-4 w-4 rounded bg-zinc-100"></span>
                                                <span class="h-4 w-4 rounded bg-zinc-100"></span>
                                                <span class="h-4 w-4 rounded bg-zinc-100"></span>
                                            </aside>

                                            <div class="min-w-0 flex-1 p-2.5 sm:p-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="relative">
                                                        <p class="text-[7px] font-semibold uppercase tracking-[0.12em] text-thrum-pollen-dark">Pollination run</p>
                                                        <div class="mt-0.5 flex items-center gap-1.5">
                                                            <h3 class="text-[13px] font-semibold tracking-tight text-zinc-900">West 04</h3>
                                                            <span class="rounded-full bg-thrum-teal-soft px-1.5 py-0.5 text-[7px] font-medium text-thrum-ink">Pollinating</span>
                                                        </div>
                                                        <p class="mt-0.5 text-[7px] text-zinc-500">Almería · Roma · 18 rows</p>
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
                                                        <span>Started 21:00</span>
                                                        <span class="rounded border border-zinc-200 bg-white px-1.5 py-0.5">•••</span>
                                                    </div>
                                                </div>

                                                <div class="mt-2 grid grid-cols-3 gap-1.5">
                                                    <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                        <p class="text-[7px] uppercase tracking-wide text-zinc-500">Blossoms visited</p>
                                                        <p class="mt-0.5 text-[11px] font-semibold text-zinc-800">12,480</p>
                                                        <p class="text-[7px] text-thrum-teal">↑ 8.1% vs plan</p>
                                                    </div>
                                                    <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                        <p class="text-[7px] uppercase tracking-wide text-zinc-500">Contact rate</p>
                                                        <p class="mt-0.5 text-[11px] font-semibold text-zinc-800">94.2%</p>
                                                        <p class="text-[7px] text-zinc-500">Target 92%</p>
                                                    </div>
                                                    <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm">
                                                        <p class="text-[7px] uppercase tracking-wide text-zinc-500">Rows complete</p>
                                                        <p class="mt-0.5 text-[11px] font-semibold text-zinc-800">16 / 18</p>
                                                        <p class="text-[7px] text-thrum-teal">42 min ahead</p>
                                                    </div>
                                                </div>

                                                <div class="mt-2 grid grid-cols-[1.25fr_0.9fr] gap-1.5">
                                                    <div class="relative rounded-md border border-zinc-200 bg-white p-2 shadow-sm">
                                                        <div class="flex items-center justify-between">
                                                            <p class="text-[7px] font-medium text-zinc-700">Blossoms / hour</p>
                                                            <div class="flex gap-1.5 text-[7px] text-zinc-500">
                                                                <span class="flex items-center gap-0.5"><span class="h-1 w-1 rounded-full bg-thrum-pollen"></span>Pip 07</span>
                                                                <span class="flex items-center gap-0.5"><span class="h-1 w-1 rounded-full bg-[#6a9d92]"></span>Pip 12</span>
                                                            </div>
                                                        </div>
                                                        <svg class="mt-1 h-10 w-full overflow-visible" viewBox="0 0 180 48" fill="none" aria-hidden="true">
                                                            <path d="M0 38H180M0 24H180M0 10H180" stroke="#f4f4f5" stroke-width="1"/>
                                                            <path d="M0 34C18 31 20 22 39 25C59 28 67 11 88 17C105 22 115 8 133 12C151 16 161 6 180 8" stroke="#d8a62f" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M0 40C17 38 26 31 43 34C62 37 70 24 91 29C110 33 120 20 140 23C155 25 167 17 180 19" stroke="#6a9d92" stroke-width="2" stroke-linecap="round"/>
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
                                                            <p class="text-[7px] font-medium text-zinc-700">Robots on row</p>
                                                            <span class="text-[7px] text-zinc-500">4 active</span>
                                                        </div>
                                                        <ul class="mt-1.5 space-y-1.5">
                                                            <li class="flex items-center gap-1.5">
                                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-thrum-teal"></span>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="truncate text-[7px] text-zinc-700">Pip 07 · Row 12</p>
                                                                    <p class="text-[7px] text-zinc-500">pollinating</p>
                                                                </div>
                                                                <span class="text-right text-[6.5px] leading-tight text-zinc-500">82% batt.<br>64% brush</span>
                                                            </li>
                                                            <li class="flex items-center gap-1.5">
                                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-thrum-teal"></span>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="truncate text-[7px] text-zinc-700">Pip 12 · Row 16</p>
                                                                    <p class="text-[7px] text-zinc-500">mapping</p>
                                                                </div>
                                                                <span class="text-right text-[6.5px] leading-tight text-zinc-500">76% batt.<br>91% brush</span>
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

                                                <div class="mt-2 overflow-hidden rounded-md border border-zinc-200 bg-[#f4f7f5] shadow-sm">
                                                    <div class="flex items-center justify-between border-b border-zinc-200/80 px-2 py-1.5">
                                                        <p class="text-[7px] font-medium text-zinc-700">Glasshouse map</p>
                                                        <p class="text-[7px] text-zinc-500">Next dispatch · 21:15</p>
                                                    </div>
                                                    <svg class="h-12 w-full" viewBox="0 0 360 48" preserveAspectRatio="none" fill="none" aria-hidden="true">
                                                        <path d="M20 8H340M20 16H340M20 24H340M20 32H340M20 40H340" stroke="#cbd5d1" stroke-width="2" stroke-linecap="round"/>
                                                        <path d="M20 8H310M20 16H286M20 24H330M20 32H268M20 40H318" stroke="#78a99e" stroke-width="2" stroke-linecap="round"/>
                                                        <circle cx="310" cy="8" r="4" fill="#d8a62f" stroke="white" stroke-width="2"/>
                                                        <circle cx="286" cy="16" r="4" fill="#d8a62f" stroke="white" stroke-width="2"/>
                                                        <circle cx="330" cy="24" r="4" fill="#d8a62f" stroke="white" stroke-width="2"/>
                                                        <circle cx="268" cy="32" r="4" fill="#d8a62f" stroke="white" stroke-width="2"/>
                                                        <path d="M20 40H318" stroke="#78a99e" stroke-width="2" stroke-dasharray="4 3"/>
                                                    </svg>
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
                                                <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full bg-thrum-pollen text-[6px] font-bold text-thrum-ink">T</span>
                                                <span class="text-[8px] font-semibold uppercase tracking-[0.2em]">Thrum / Robotics</span>
                                            </div>
                                            <div class="flex items-center gap-3 text-[8px] font-medium text-[#5d6661]">
                                                <span class="hidden sm:inline">Fleet</span>
                                                <span class="hidden sm:inline">Method</span>
                                                <span class="border-b border-[#1f2926] pb-0.5 text-[#1f2926]">Meet Pip</span>
                                            </div>
                                        </nav>

                                        <div class="grid min-h-0 flex-1 grid-cols-[1.08fr_0.92fr]">
                                            <div class="flex min-w-0 flex-col justify-between px-3.5 py-3 sm:px-5 sm:py-4">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-[7px] font-semibold uppercase tracking-[0.18em] text-thrum-pollen-dark">Autonomous pollination / Pip 04</p>
                                                    <span class="text-[7px] text-[#6d7671]">04:42 AM</span>
                                                </div>

                                                <div class="min-w-0">
                                                    <div class="relative max-w-[15rem]">
                                                        <h3 class="font-display text-[clamp(1.2rem,2.8vw,1.8rem)] font-medium leading-[0.98] tracking-[-0.025em] text-[#173f3b]">
                                                            Every open blossom,<br>
                                                            before sunrise.
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
                                                        <p class="text-[9px] leading-relaxed text-[#596661]">Pip rolls the rows at night, finds flowers ready for pollen, and gives each one a careful buzz.</p>
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
                                                        <span class="rounded-full bg-thrum-pollen px-3 py-1.5 text-[8px] font-semibold text-thrum-ink shadow-sm">Book a field demo</span>
                                                        <span class="border-b border-[#70817a] pb-0.5 text-[8px] font-medium text-[#315c56]">Read run 118 ↗</span>
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

                                                <div class="flex items-center gap-2 text-[7px] text-[#69736e]">
                                                    <span class="font-semibold text-[#173f3b]">18 rows</span>
                                                    <span class="h-px w-4 bg-[#b6b2aa]"></span>
                                                    <span>12,480 blossoms · one night</span>
                                                </div>
                                            </div>

                                            <div class="relative min-w-0 overflow-hidden rounded-tl-[2rem] bg-thrum-ink">
                                                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 180 260" preserveAspectRatio="xMidYMid meet" fill="none" aria-hidden="true">
                                                    <path d="M8 230V82C8 44 30 24 53 24s45 20 45 58v148M82 230V82c0-38 22-58 45-58s45 20 45 58v148M0 76h180M0 132h180M0 188h180" stroke="#8eb7a7" stroke-opacity=".24"/>
                                                    <path d="M14 220h152M14 205h152M14 190h152M14 175h152M14 160h152" stroke="#d5e4dc" stroke-opacity=".13"/>
                                                    <g transform="translate(76 126)">
                                                        <rect x="-23" y="-15" width="46" height="31" rx="9" fill="#f4efe6"/>
                                                        <rect x="-14" y="-29" width="27" height="14" rx="5" fill="#d8a62f"/>
                                                        <circle cx="6" cy="-22" r="2.5" fill="#163f3a"/>
                                                        <path d="M-12 0h24M0-10v20" stroke="#d5cec1" stroke-width="1"/>
                                                        <circle cx="-15" cy="20" r="7" fill="#0d3531" stroke="#f4efe6" stroke-width="2.5"/>
                                                        <circle cx="15" cy="20" r="7" fill="#0d3531" stroke="#f4efe6" stroke-width="2.5"/>
                                                        <path d="M23-4 36-10 43-24" stroke="#f4efe6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="m42-24 6 3m-6-3 2-6" stroke="#d8a62f" stroke-width="3" stroke-linecap="round"/>
                                                        <g transform="translate(52 -34)" fill="#f4efe6">
                                                            <circle cx="0" cy="-5" r="4"/><circle cx="5" cy="0" r="4"/><circle cx="0" cy="5" r="4"/><circle cx="-5" cy="0" r="4"/>
                                                            <circle r="3" fill="#d8a62f"/>
                                                        </g>
                                                    </g>
                                                </svg>
                                                <div class="absolute left-3 top-3 rounded-full border border-white/25 bg-[#103f3b]/80 px-2 py-1 text-[7px] font-semibold uppercase tracking-[0.14em] text-[#f2dfba] backdrop-blur-sm">Pip 04 · West 04</div>
                                                <div class="absolute bottom-3 left-3 right-3 border-t border-white/25 pt-2 text-white">
                                                    <p class="font-display text-[13px] leading-none">Brush, map, repeat.</p>
                                                    <div class="mt-1.5 flex items-center justify-between text-[7px] text-white/65">
                                                        <span>Row 12 / Truss 08</span>
                                                        <span>94.2% contact</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- PRESENTATIONS mock --}}
                                <div class="absolute inset-2 overflow-hidden rounded-lg border border-zinc-700 bg-[#242522] sm:inset-3" x-show="scenario === 'presentations'" x-cloak>
                                    <div class="flex h-full">
                                        <aside class="hidden w-[2.75rem] shrink-0 flex-col gap-1.5 border-r border-white/10 bg-[#191a18] p-1 sm:flex">
                                            <div class="relative aspect-video rounded-md border border-thrum-pollen bg-thrum-paper p-1">
                                                <div class="h-1 w-3 rounded-full bg-thrum-pollen"></div>
                                                <div class="mt-1 flex h-2.5 items-end gap-0.5">
                                                    <span class="h-1.5 flex-1 bg-thrum-teal"></span><span class="h-2.5 flex-1 bg-thrum-pollen"></span>
                                                </div>
                                            </div>
                                            <div class="relative aspect-video rounded-md border border-white/10 bg-zinc-800 p-1">
                                                <div class="h-1 w-4 rounded-full bg-zinc-600"></div>
                                                <div class="mt-1 h-2.5 rounded-sm bg-[#325f59]"></div>
                                            </div>
                                            <div class="relative aspect-video rounded-md border border-white/10 bg-zinc-800 p-1">
                                                <div class="h-1 w-3 rounded-full bg-zinc-600"></div>
                                                <div class="mt-1 h-2.5 rounded-sm bg-zinc-700"></div>
                                            </div>
                                        </aside>

                                        <div class="flex min-w-0 flex-1 flex-col">
                                            <div class="flex h-5 items-center justify-between border-b border-white/10 px-2 text-[7px] text-zinc-400">
                                                <span>Harvest review / June 2026</span>
                                                <span class="rounded-full border border-white/10 px-1.5 py-0.5 text-zinc-300">Present ↗</span>
                                            </div>
                                            <div class="flex flex-1 items-center justify-center p-1.5">
                                                <div class="relative aspect-video w-full max-w-[27rem] overflow-hidden rounded-md bg-thrum-paper px-4 py-3 text-thrum-ink shadow-[0_10px_30px_rgb(0_0_0/0.4)] sm:px-5">
                                                    <div class="flex items-center justify-between text-[7px] font-semibold uppercase tracking-[0.16em]">
                                                        <span class="text-thrum-pollen-dark">Thrum / Harvest</span>
                                                        <span class="text-[#7d817b]">07</span>
                                                    </div>

                                                    <div class="mt-3 grid grid-cols-[1.05fr_0.95fr] gap-4">
                                                        <div class="min-w-0">
                                                            <div class="relative">
                                                                <h3 class="font-display text-[15px] font-medium leading-[1.02] tracking-[-0.025em] text-[#183b37] sm:text-[18px]">
                                                                    Night pollination lifted<br>Grade A yield 18%.
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
                                                            <p class="mt-2 max-w-[11rem] text-[8px] leading-relaxed text-[#5f6d67]">
                                                                Pip covered open blossoms at peak receptivity while greenhouse crews were off shift.
                                                            </p>
                                                            <p class="mt-3 inline-flex rounded-full bg-thrum-teal-soft px-2 py-1 text-[7px] font-semibold text-thrum-ink">+13 pts vs hand pollination</p>
                                                        </div>

                                                        <div class="relative">
                                                            <div class="flex h-[4.6rem] items-end gap-2 border-b border-[#a9aaa4] px-2 pb-0.5">
                                                                <div class="flex flex-1 flex-col items-center gap-1">
                                                                    <span class="text-[7px] font-semibold text-[#69736e]">72%</span>
                                                                    <span class="h-[3.1rem] w-full bg-[#7fa49b]"></span>
                                                                </div>
                                                                <div class="flex flex-1 flex-col items-center gap-1">
                                                                    <span class="text-[7px] font-semibold text-thrum-pollen-dark">85%</span>
                                                                    <span class="h-[4.25rem] w-full bg-thrum-pollen"></span>
                                                                </div>
                                                            </div>
                                                            <div class="mt-1 grid grid-cols-2 gap-2 text-center text-[6px] font-semibold uppercase tracking-wide text-[#69736e]">
                                                                <span>Hand</span>
                                                                <span>Pip</span>
                                                            </div>
                                                            <div
                                                                class="pointer-events-none absolute -inset-1 rounded-md border-2 border-dashed border-sky-400 bg-sky-400/10"
                                                                x-show="step === 1"
                                                                x-transition.opacity
                                                                :class="step === 1 ? 'rm-pulse-soft' : ''"
                                                            ></div>
                                                            <div
                                                                class="absolute -left-2 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-thrum-paper text-[8px] font-bold text-sky-700 shadow-sm"
                                                                x-show="step === 1"
                                                                x-transition
                                                            >S1</div>
                                                        </div>
                                                    </div>

                                                    <div class="relative mt-2 border-t border-[#d8d2c8] pt-1.5">
                                                        <p class="text-[6.5px] leading-tight text-[#777c76]">Source: 14 Roma rows · 8,420 trusses · June 2026 harvest · edge rows and replants excluded</p>
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
                                                <span>Dawn report · Run 118</span>
                                            </div>
                                            <div class="flex items-center gap-1 rounded-md border border-[#d8d4cc] bg-white p-0.5">
                                                <span class="rounded bg-[#224e48] px-1.5 py-0.5 text-white">Desktop</span>
                                                <span class="px-1.5 py-0.5">Mobile</span>
                                            </div>
                                        </div>

                                        <div class="min-h-0 flex-1 overflow-hidden p-2 sm:p-2.5">
                                            <div class="relative mx-auto flex h-full max-w-[19rem] flex-col overflow-hidden rounded-md bg-[#fbfaf7] shadow-[0_8px_24px_rgb(55_54_49/0.16)]">
                                                <div class="flex shrink-0 items-center justify-between border-b border-[#ebe7df] px-3 py-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full bg-thrum-pollen text-[6px] font-bold text-thrum-ink">T</span>
                                                        <span class="text-[8px] font-semibold uppercase tracking-[0.14em] text-[#243d39]">Thrum Robotics</span>
                                                    </div>
                                                    <span class="text-[7px] text-[#777d77]">Delivered at sunrise · 05:48</span>
                                                </div>

                                                <div class="border-b border-[#e4ded4] bg-thrum-paper px-4 py-3">
                                                    <p class="text-[7px] font-semibold uppercase tracking-[0.18em] text-thrum-pollen-dark">West 04 / Dawn report 118</p>
                                                    <div class="relative mt-2">
                                                        <h3 class="max-w-[14rem] font-display text-[16px] font-medium leading-[1.02] tracking-[-0.025em] text-thrum-ink sm:text-[18px]">
                                                            12,480 blossoms<br>visited before dawn.
                                                        </h3>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step === 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop' : ''"
                                                        >
                                                            <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-thrum-paper">M1</span>
                                                        </div>
                                                    </div>
                                                    <p class="mt-1.5 max-w-[14rem] text-[8px] leading-relaxed text-[#5f6d67]">Four Pips covered 16 Roma rows overnight. Two rows remain queued for the morning pass.</p>
                                                    <div class="relative mt-2 inline-flex">
                                                        <span class="rounded-full bg-thrum-pollen px-2.5 py-1.5 text-[8px] font-semibold text-thrum-ink shadow-sm">Review this run →</span>
                                                        <div
                                                            class="pointer-events-none absolute -inset-1 rounded-md border-2 border-rose-500 bg-rose-500/10"
                                                            x-show="step === 2"
                                                            x-transition
                                                            :class="step === 2 ? 'rm-pin-pop-2' : ''"
                                                        >
                                                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-semibold text-white shadow ring-2 ring-thrum-paper">M2</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="px-4 py-2.5">
                                                    <div class="flex items-start gap-2 border-b border-[#ece8e0] pb-2">
                                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#dce8e3] text-[6px] font-semibold text-[#225c54]">94</span>
                                                        <div class="min-w-0">
                                                            <p class="text-[8px] font-semibold text-[#303d39]">Pollination contact</p>
                                                            <p class="mt-0.5 truncate text-[7px] text-[#747b75]">94.2% · 2.2 points above target</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-start gap-2 pt-2">
                                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#f4e8c9] text-[6px] font-semibold text-thrum-pollen-dark">P7</span>
                                                        <div class="min-w-0">
                                                            <p class="text-[8px] font-semibold text-[#303d39]">One robot needs a brush</p>
                                                            <p class="mt-0.5 truncate text-[7px] text-[#747b75]">Pip 07 · replace before tonight’s dispatch</p>
                                                        </div>
                                                    </div>

                                                    <div class="mt-2 rounded-md bg-thrum-ink p-2 text-white">
                                                        <div class="flex items-center justify-between">
                                                            <p class="text-[7px] font-semibold">Rows complete</p>
                                                            <p class="text-[7px] text-white/65">16 / 18</p>
                                                        </div>
                                                        <div class="mt-1.5 grid grid-cols-9 gap-0.5">
                                                            @for ($row = 1; $row <= 18; $row++)
                                                                <span class="h-1 rounded-full {{ $row <= 16 ? 'bg-thrum-pollen' : 'bg-white/20' }}"></span>
                                                            @endfor
                                                        </div>
                                                        <p class="mt-1.5 text-[6.5px] text-white/65">Morning pass begins 07:10 · Rows 17–18</p>
                                                    </div>
                                                </div>

                                                <div class="relative mt-auto border-t border-[#ece8e0] px-4 py-2 text-center">
                                                    <p class="text-[6.5px] leading-relaxed text-[#858a84]">Thrum Robotics · Almería field office&nbsp;&nbsp;|&nbsp;&nbsp;Unsubscribe&nbsp;&nbsp;|&nbsp;&nbsp;Report preferences</p>
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
            <section id="features" class="mt-16 scroll-mt-8 sm:mt-20">
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
                        since September 2024, when it was just an idea and a Figma file. Originally I imagined it as my version of a productized design feedback service — inspired by Roasti, now defunct. Also, as a designer I love giving feedback. Not to be a dick, but to be a Derek.
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
