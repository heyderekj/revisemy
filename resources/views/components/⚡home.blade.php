<?php

use App\Services\TryTokenGate;
use App\Services\TryTokenService;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

new class extends Component
{
    public ?string $token = null;

    public ?string $mcpUrl = null;

    public ?string $cursorConfigJson = null;

    public ?string $claudeDesktopConfigJson = null;

    public ?string $copilotConfigJson = null;

    public ?string $claudeCodeCommand = null;

    public ?string $setupPromptsJson = null;

    public ?string $checkupPromptsJson = null;

    public ?string $tokenExpiresAt = null;

    public ?string $error = null;

    public function getTryToken(TryTokenService $tryTokens, TryTokenGate $gate): void
    {
        $this->error = null;

        try {
            $gate->assertCanMint(request());
            $result = $tryTokens->create();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === TryTokenGate::MESSAGE) {
                $this->error = $e->getMessage();

                return;
            }

            report($e);

            $this->error = 'Could not start a try right now. On Laravel Cloud, attach Postgres and run migrations — SQLite does not persist across deploys.';

            return;
        } catch (\Throwable $e) {
            report($e);

            $this->error = 'Could not start a try right now. On Laravel Cloud, attach Postgres and run migrations — SQLite does not persist across deploys.';

            return;
        }

        $this->token = $result['token'];
        $this->tokenExpiresAt = $result['token_expires_at'];
        $this->mcpUrl = $result['mcp_url'];
        $this->cursorConfigJson = json_encode($result['cursor_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->claudeDesktopConfigJson = json_encode($result['claude_desktop_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->copilotConfigJson = json_encode($result['copilot_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->claudeCodeCommand = $result['claude_code_command'];
        $this->setupPromptsJson = json_encode($result['setup_prompts'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->checkupPromptsJson = json_encode($result['checkup_prompts'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->dispatch('revisemy-try-setup-saved', payload: [
            'token' => $this->token,
            'tokenExpiresAt' => $this->tokenExpiresAt,
            'mcpUrl' => $this->mcpUrl,
            'cursorConfigJson' => $this->cursorConfigJson,
            'claudeDesktopConfigJson' => $this->claudeDesktopConfigJson,
            'copilotConfigJson' => $this->copilotConfigJson,
            'claudeCodeCommand' => $this->claudeCodeCommand,
            'setupPromptsJson' => $this->setupPromptsJson,
            'checkupPromptsJson' => $this->checkupPromptsJson,
        ]);

        $this->dispatch('scroll-to-setup');
    }

    public function restoreTryTokenSetup(
        string $token,
        string $mcpUrl,
        string $cursorConfigJson,
        string $claudeDesktopConfigJson,
        string $copilotConfigJson,
        string $claudeCodeCommand,
        string $setupPromptsJson = '',
        string $checkupPromptsJson = '',
        string $tokenExpiresAt = '',
    ): void {
        $this->token = $token;
        $this->tokenExpiresAt = $tokenExpiresAt ?: null;
        $this->mcpUrl = $mcpUrl;
        $this->cursorConfigJson = $cursorConfigJson;
        $this->claudeDesktopConfigJson = $claudeDesktopConfigJson;
        $this->copilotConfigJson = $copilotConfigJson ?: null;
        $this->claudeCodeCommand = $claudeCodeCommand;
        $this->setupPromptsJson = $setupPromptsJson ?: null;
        $this->checkupPromptsJson = $checkupPromptsJson ?: null;
        $this->error = null;

        if (! $this->tokenExpiresAt && $this->token) {
            $this->tokenExpiresAt = PersonalAccessToken::findToken($this->token)?->expires_at?->toIso8601String();
        }

        // Backfill sessionStorage when an older saved try setup lacked expiry.
        if ($this->tokenExpiresAt) {
            $this->dispatch('revisemy-try-setup-saved', payload: [
                'token' => $this->token,
                'tokenExpiresAt' => $this->tokenExpiresAt,
                'mcpUrl' => $this->mcpUrl,
                'cursorConfigJson' => $this->cursorConfigJson,
                'claudeDesktopConfigJson' => $this->claudeDesktopConfigJson,
                'copilotConfigJson' => $this->copilotConfigJson,
                'claudeCodeCommand' => $this->claudeCodeCommand,
                'setupPromptsJson' => $this->setupPromptsJson,
                'checkupPromptsJson' => $this->checkupPromptsJson,
            ]);
        }
    }

    public function clearTryTokenSetup(): void
    {
        $this->token = null;
        $this->tokenExpiresAt = null;
        $this->mcpUrl = null;
        $this->cursorConfigJson = null;
        $this->claudeDesktopConfigJson = null;
        $this->copilotConfigJson = null;
        $this->claudeCodeCommand = null;
        $this->setupPromptsJson = null;
        $this->checkupPromptsJson = null;
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
        get showHeaderTry() { return this.pastHero && ! this.atSetup },
        openFaqFromHash() {
            const hash = window.location.hash;
            if (! hash.startsWith('#faq-')) return;
            const details = document.querySelector(hash);
            if (details instanceof HTMLDetailsElement) {
                details.open = true;
            }
        },
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
        },
        initMobileNav() {
            this.$watch('mobileNav', (open) => {
                document.documentElement.classList.toggle('overflow-hidden', open);
            });
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.mobileNav) {
                    this.mobileNav = false;
                }
            });
        }
    }"
    x-init="
        initStickyCta();
        initMobileNav();
        openFaqFromHash();
        window.addEventListener('hashchange', () => openFaqFromHash());
    "
    x-on:scroll-to-setup.window="$nextTick(() => document.getElementById('setup')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
>
    {{-- Inset the framed column from the viewport so rails + crosshairs stay
         visible on small screens (and the top rule isn't flush with the edge). --}}
    <div class="relative z-10 mx-auto max-w-[1200px] px-4 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pt-16">
        <div class="rm-rails relative flex min-h-screen border-t border-zinc-200 bg-canvas/60">
            <x-cross-mark left="0" top="0" />
            <x-cross-mark left="220px" top="0" visibility="hidden lg:block" />
            <x-cross-mark left="100%" top="0" />

        {{-- Agentation-style sidebar --}}
        <aside class="hidden w-[220px] shrink-0 flex-col border-r border-zinc-200 bg-zinc-50/90 px-6 pb-8 pt-10 backdrop-blur lg:flex lg:sticky lg:top-0 lg:h-screen lg:max-h-screen lg:self-start lg:overflow-y-auto">
            <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                <x-revisemy-logo variant="wordmark" size="lg" />
            </a>

            <nav class="mt-12 flex flex-1 flex-col gap-8 text-[14px]">
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Overview</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#top" class="transition hover:text-zinc-900">Home</a></li>
                        <li><a href="#how" class="transition hover:text-zinc-900">How it works</a></li>
                        <li><a href="#agents" class="transition hover:text-zinc-900">For agents</a></li>
                        <li><a href="#pricing" class="transition hover:text-zinc-900">Pricing</a></li>
                        <li><a href="#faq" class="transition hover:text-zinc-900">FAQ</a></li>
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
                            <a href="/connectors" class="inline-flex items-center gap-2 transition hover:text-zinc-900">
                                Connectors
                                <span class="rounded bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-accent-contrast">MCP</span>
                            </a>
                        </li>
                        <li>
                            <a href="/guest-links" class="transition hover:text-zinc-900">Guest links</a>
                        </li>
                        <li>
                            <a href="/alternatives" class="transition hover:text-zinc-900">Alternatives</a>
                        </li>
                        <li>
                            <a href="https://github.com/heyderekj/revisemy" class="transition hover:text-zinc-900" target="_blank" rel="noreferrer">
                                GitHub ↗
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <p class="mt-auto pt-8 font-mono text-[11px] text-zinc-400">v{{ config('revisemy.version') }}</p>
        </aside>

        {{-- Main --}}
        <main id="top" class="min-w-0 flex-1 lg:pt-10 [--rm-pad:1.25rem] sm:[--rm-pad:2rem] lg:[--rm-pad:3rem]">
            {{-- Mobile top bar: logo | try + two-line menu (aligned).
                 Wordmark slides under the app icon in sync with the header CTA. --}}
            <div class="sticky top-0 z-40 flex items-center justify-between gap-3 bg-canvas/85 px-[var(--rm-pad)] py-3 backdrop-blur-md lg:hidden">
                <a href="/" class="inline-flex min-w-0 shrink items-center hover:opacity-90" aria-label="ReviseMy home">
                    <span class="inline-flex items-center">
                        <img
                            src="{{ \App\Support\BrandAssets::appIconUrl() }}"
                            alt=""
                            width="40"
                            height="40"
                            class="relative z-10 block size-10 shrink-0"
                            decoding="async"
                        />
                        <span
                            class="max-w-[7.5rem] overflow-hidden whitespace-nowrap pl-2.5 text-lg font-semibold tracking-tight text-zinc-900 opacity-100 transition-[max-width,opacity,transform,padding] duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                            x-bind:class="showHeaderTry && '!max-w-0 !-translate-x-2 !pl-0 !opacity-0'"
                            aria-hidden="true"
                        >ReviseMy</span>
                    </span>
                </a>
                <div class="flex shrink-0 items-center gap-2">
                    <div
                        x-show="showHeaderTry"
                        x-cloak
                        x-transition:enter="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                        x-transition:enter-start="translate-x-2 opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-2 opacity-0"
                    >
                        <x-try-token-button fathom-event="Try token header" />
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-md text-zinc-800 transition hover:bg-zinc-100 active:scale-[0.97]"
                        x-on:click="mobileNav = ! mobileNav"
                        x-bind:aria-expanded="mobileNav.toString()"
                        x-bind:aria-label="mobileNav ? 'Close menu' : 'Open menu'"
                        aria-controls="rm-mobile-nav"
                    >
                        <span class="relative flex h-[14px] w-[18px] flex-col justify-between" aria-hidden="true">
                            <span
                                class="block h-[1.5px] w-full origin-center rounded-full bg-current transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                                x-bind:class="mobileNav && 'translate-y-[6.25px] rotate-45'"
                            ></span>
                            <span
                                class="block h-[1.5px] w-full origin-center rounded-full bg-current transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                                x-bind:class="mobileNav && '-translate-y-[6.25px] -rotate-45'"
                            ></span>
                        </span>
                    </button>
                </div>
            </div>

            {{-- Sticky try CTA (desktop only): top-10 matches aside pt-10. --}}
            <div class="relative">
                <div class="pointer-events-none sticky top-10 z-30 hidden h-0 lg:block">
                    <div class="flex justify-end px-[var(--rm-pad)]">
                        <div class="pointer-events-auto">
                            <x-try-token-button fathom-event="Try token sidebar" />
                        </div>
                    </div>
                </div>

            {{-- Hero — bottom padding tightened so it reads with How it works below --}}
            <x-home-section first class="rm-fade-up !pb-8 sm:!pb-10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <h1 class="max-w-xl text-[clamp(2.4rem,5.5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-zinc-900">
                        <span class="rm-hero-mark">
                            <svg class="rm-hero-mark-frame" aria-hidden="true" focusable="false">
                                <rect />
                            </svg>
                            Visual feedback
                        </span>
                        <br>
                        <span class="sr-only">with your agent.</span>
                        <span aria-hidden="true">
                            with&nbsp;<span class="rm-agent-cycle">
                                @foreach (['ChatGPT', 'Claude', 'Copilot', 'Cursor', 'Grok'] as $i => $label)
                                    <span class="rm-agent-cycle-item" style="--i: {{ $i }}">{{ $label }}.</span>
                                @endforeach
                            </span>
                        </span>
                    </h1>
                    {{-- Mobile: hero CTA while in view. Header picks it up once this scrolls away. --}}
                    <x-try-token-button id="rm-hero-cta" fathom-event="Try token hero" class="self-start lg:hidden" />
                    <div class="invisible hidden pointer-events-none self-start lg:block" aria-hidden="true">
                        <x-try-token-button fathom-event="Try token setup" />
                    </div>
                </div>

                <p class="rm-fade-up-delay mt-5 w-full max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    ReviseMy is human-in-the-loop design review for agents. Drop in a screenshot, URL, PDF, or email HTML, mark what matters, and send clear next steps back over MCP — without leaving your AI chat.
                </p>

                @if ($error)
                    <p class="mt-3 text-sm text-rose-600">{{ $error }}</p>
                @endif

                <div class="rm-fade-up-delay-2 relative mt-10 sm:mt-12">
                    <x-hero-loop-preview />
                </div>
            </x-home-section>

            {{-- How it works: joined to hero (no top rule) --}}
            <x-home-section id="how" joined>
                <x-section-eyebrow number="01" label="How it works" />
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">One feedback loop for anything visual</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    From capture to approval, ReviseMy keeps reviews, marks, guest feedback, and lifecycle together — so nothing gets lost between passes.
                </p>

                <div class="rm-bleed relative mt-10 border-y border-zinc-200">
                    {{-- Top / bottom column intersections (on the outer border-y). --}}
                    <x-cross-mark left="33.3333%" top="0%" visibility="hidden lg:block" />
                    <x-cross-mark left="66.6667%" top="0%" visibility="hidden lg:block" />
                    <x-cross-mark left="33.3333%" top="100%" visibility="hidden lg:block" />
                    <x-cross-mark left="66.6667%" top="100%" visibility="hidden lg:block" />
                    {{-- 2-col midpoint (sm–md only). --}}
                    <x-cross-mark left="50%" top="50%" visibility="hidden min-[30rem]:block lg:hidden" />

                    {{-- Below lg: one gap grid. At lg: two content rows + a real 1px
                         middle rule so crosshairs sit on the hairline (not geometric 50%,
                         which drifts when the rows are different heights). --}}
                    <div class="grid grid-cols-1 gap-px bg-[var(--color-border)] min-[30rem]:grid-cols-2 lg:grid-cols-3 lg:grid-rows-[auto_1px_auto] lg:gap-y-0">
                        <article class="bg-[var(--color-canvas)] p-7 lg:row-start-1">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                                <flux:icon.photo variant="micro" class="size-[18px]" />
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-zinc-900">Capture anything visual</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Ask your agent to open a review from screenshots, a page URL (desktop + mobile), PDF slides, or email HTML — each type gets its own checklist and vision lens.</p>
                        </article>

                        <article class="bg-[var(--color-canvas)] p-7 lg:row-start-1">
                            <x-mark-type-icon type="s" />
                            <h3 class="mt-3 text-sm font-semibold text-zinc-900">Second opinion</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Optional hints can land first — checklist immediately, optional Claude or OpenAI vision when a key is set. Useful suggestions, never decisions.</p>
                            <a
                                href="/second-opinion"
                                class="mt-2 inline-block text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                            >Learn more</a>
                        </article>

                        <article class="bg-[var(--color-canvas)] p-7 lg:row-start-1">
                            <x-mark-type-icon type="m" />
                            <h3 class="mt-3 text-sm font-semibold text-zinc-900">Precise marks</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Open the review link, point or outline the exact area, set must-fix / nice to have / question / keep, and keep a threaded comment on each mark.</p>
                        </article>

                        <div
                            class="relative col-span-full hidden h-px bg-[var(--color-border)] lg:block lg:row-start-2"
                            aria-hidden="true"
                        >
                            <x-cross-mark left="0" top="50%" />
                            <x-cross-mark left="33.3333%" top="50%" />
                            <x-cross-mark left="66.6667%" top="50%" />
                            <x-cross-mark left="100%" top="50%" />
                        </div>

                        <article class="bg-[var(--color-canvas)] p-7 lg:row-start-3">
                            <x-mark-type-icon type="g" />
                            <h3 class="mt-3 text-sm font-semibold text-zinc-900">Guest links</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Share a private guest link when you want another set of eyes — no accounts. Your marks stay authoritative.</p>
                            <a
                                href="/guest-links"
                                class="mt-2 inline-block text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                            >Learn more</a>
                        </article>

                        <article class="bg-[var(--color-canvas)] p-7 lg:row-start-3">
                            <x-use-case-icon name="queue-list" />
                            <h3 class="mt-3 text-sm font-semibold text-zinc-900">Board to done</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Track marks from open → resolved → verified on the board. Agents can attach before/after evidence when they fix something.</p>
                            <a
                                href="/board"
                                class="mt-2 inline-block text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                            >Learn more</a>
                        </article>

                        <article class="bg-[var(--color-canvas)] p-7 lg:row-start-3">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                                <flux:icon.arrow-path variant="micro" class="size-[18px]" />
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-zinc-900">Approve and loop</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Approve or request changes. Structured next steps return over MCP — repeat until it feels right.</p>
                        </article>
                    </div>
                </div>

                <div class="mt-14">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Built for everyone in the loop</h2>
                    <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                        Humans mark and approve. Agents ship the next pass. Same loop whether you’re reviewing, designing, building, or signing off.
                    </p>

                    <ul class="mt-8 grid grid-cols-1 gap-x-8 gap-y-6 min-[30rem]:grid-cols-2">
                        @foreach (config('use-cases.audiences', []) as $slug => $audience)
                            <li>
                                <a href="{{ url('/for/'.$slug) }}" class="group flex items-start gap-3">
                                    <x-use-case-icon
                                        :name="$audience['icon']"
                                        class="mt-0.5 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-zinc-900 transition group-hover:text-rose-600">
                                            {{ $audience['label'] }}
                                        </span>
                                        <span class="mt-1 block text-sm leading-relaxed text-zinc-500">
                                            {{ $audience['teaser'] ?? $audience['headline'] }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-14 border-t border-zinc-200 pt-12">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">By review type</h2>
                    <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                        Pick the artifact — each type gets its own checklist and vision lens.
                    </p>
                    <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-3">
                        @foreach (config('use-cases.pages', []) as $slug => $useCase)
                            <li>
                                <a href="{{ url('/for/'.$slug) }}" class="group inline-flex items-center gap-2">
                                    <x-use-case-icon
                                        :name="$useCase['icon']"
                                        size="sm"
                                        class="transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80"
                                    />
                                    <span class="text-sm font-medium text-zinc-700 transition group-hover:text-rose-600">
                                        {{ $useCase['label'] }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div id="prompts" class="mt-14 scroll-mt-8 border-t border-zinc-200 pt-12">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Example prompts</h2>
                    <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                        Paste one into your agent — each review takes exactly one source, then the same mark → fix → approve loop.
                    </p>
                    <ul class="mt-6 space-y-4">
                        <li class="flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:gap-4">
                            <span class="shrink-0 text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 sm:w-28">Screenshots</span>
                            <x-sample-prompt text="Run a design checkup on these UI screenshots." />
                        </li>
                        <li class="flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:gap-4">
                            <span class="shrink-0 text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 sm:w-28">Live URL</span>
                            <x-sample-prompt text="Review this URL — capture desktop and mobile and share the review link." />
                        </li>
                        <li class="flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:gap-4">
                            <span class="shrink-0 text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 sm:w-28">PDF / slides</span>
                            <x-sample-prompt text="Review this pitch deck PDF — one screenshot per slide." />
                        </li>
                        <li class="flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:gap-4">
                            <span class="shrink-0 text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 sm:w-28">Email HTML</span>
                            <x-sample-prompt text="Review this email HTML before we send — share the review link." />
                        </li>
                        <li class="flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:gap-4">
                            <span class="shrink-0 text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 sm:w-28">Method</span>
                            <x-sample-prompt text="Address my feedback and attach after shots when you resolve each mark." />
                        </li>
                    </ul>
                </div>

                <div class="mt-14 border-t border-zinc-200 pt-12">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Agents we support</h2>
                    <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                        One MCP endpoint. Paste the config into the host you already use.
                    </p>
                    <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-3">
                        @foreach ([
                            'chatgpt' => 'ChatGPT',
                            'claude' => 'Claude',
                            'copilot' => 'Copilot',
                            'cursor' => 'Cursor',
                            'grok' => 'Grok',
                        ] as $id => $label)
                            <li>
                                <a href="{{ url('/for/'.$id) }}" class="group inline-flex items-center gap-2">
                                    <span class="inline-flex size-7 items-center justify-center rounded-md bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80">
                                        <x-host-icon :name="$id" />
                                    </span>
                                    <span class="text-sm font-medium text-zinc-700 transition group-hover:text-rose-600">
                                        {{ $label }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-14 border-t border-zinc-200 pt-12">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Helpful guides</h2>
                    <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                        Setup, inline review, CI gates, and fair comparisons — when you need the longer path.
                    </p>
                    <ul class="mt-6 space-y-3 text-[15px]">
                        <li>
                            <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                                Setup guides
                            </a>
                            <span class="text-zinc-400"> — </span>
                            <span class="text-zinc-500">ChatGPT, Claude, Copilot, Cursor, Grok</span>
                        </li>
                        <li>
                            <a href="/mcp-apps" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                                MCP Apps
                            </a>
                            <span class="text-zinc-400"> — </span>
                            <span class="text-zinc-500">Inline review vs review_url</span>
                        </li>
                        <li>
                            <a href="/webhooks" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                                Webhooks
                            </a>
                            <span class="text-zinc-400"> — </span>
                            <span class="text-zinc-500">Gate CI on review.decided</span>
                        </li>
                        <li>
                            <a href="/alternatives" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                                Alternatives
                            </a>
                            <span class="text-zinc-400"> — </span>
                            <span class="text-zinc-500">Figma comments, Marker, Pastel, Lucidly, MarkUp, Workflow, Simple Commenter, AI chat apps</span>
                        </li>
                    </ul>
                </div>
            </x-home-section>

            {{-- How agents use it --}}
            <x-home-section id="agents">
                <x-section-eyebrow number="02" label="For agents" />
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">The technical handoff</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    <code class="font-mono text-[13px] text-rose-600">create_review</code> accepts images, a capture URL, PDF, or HTML. When you finish,
                    <code class="font-mono text-[13px] text-rose-600">get_review</code> returns work packets and one clear <code class="font-mono text-[13px]">next_action</code>: wait, apply marks, open another pass, or stop.
                    In <a href="/connectors#claude" class="text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">MCP Apps hosts</a>, the review renders inline in chat; CLI hosts still use the <code class="font-mono text-[13px]">review_url</code> link.
                </p>
                <ul class="mt-5 max-w-2xl list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                    <li>Marks include intent and priority: <span class="font-medium text-zinc-800">must-fix</span>, nice to have, question, or keep</li>
                    <li><code class="font-mono text-[13px]">second_opinion</code> and guest findings stay suggestions until you accept them</li>
                    <li><code class="font-mono text-[13px] text-rose-600">resolve_marks</code> can close marks with notes and optional before/after evidence</li>
                    <li>Requesting changes links a follow-up pass via <code class="font-mono text-[13px] text-rose-600">create_review</code> + <code class="font-mono text-[13px]">parent_id</code></li>
                    <li>The MCP prompt <code class="font-mono text-[13px]">design_checkup_loop</code> can guide the full cycle</li>
                </ul>
            </x-home-section>
            </div>

            {{-- Setup --}}
            <x-home-section
                id="setup"
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
                                    d.copilotConfigJson ?? '',
                                    d.claudeCodeCommand ?? '',
                                    d.setupPromptsJson ?? '',
                                    d.checkupPromptsJson ?? '',
                                    d.tokenExpiresAt ?? '',
                                );
                            }
                        } catch (e) {}
                    }
                "
            >
                <x-section-eyebrow number="03" label="Setup" />
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Try it with your agent</h2>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600">
                    Choose the app you already use. Try free — no account required — and connect ChatGPT, Claude, Copilot, Cursor, or Grok.
                    After you have a token, open
                    <a href="/reviews" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 hover:text-rose-500">Recent reviews</a>
                    for the same list your agent sees via <code class="font-mono text-[13px]">list_reviews</code>.
                    Any MCP client can use the same URL and Bearer token; see
                    <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 hover:text-rose-500">Connectors</a>
                    for the full host list.
                </p>

                @if ($error)
                    <p class="mt-4 text-sm text-rose-600">{{ $error }}</p>
                @endif

                @if (! $token)
                    <div class="mt-8">
                        <x-try-token-button fathom-event="Try token setup" />
                    </div>
                @else
                    <div
                        class="mt-8 flex flex-col gap-5"
                        x-data="{
                            client: 'chatgpt',
                            mode: 'agent',
                            init() {
                                try {
                                    const savedClient = sessionStorage.getItem('revisemy_try_client');
                                    const savedMode = sessionStorage.getItem('revisemy_try_setup_mode');
                                    if (savedClient) this.client = savedClient;
                                    if (savedMode === 'agent' || savedMode === 'manual') this.mode = savedMode;
                                } catch (e) {}
                            },
                            setClient(id) {
                                this.client = id;
                                try { sessionStorage.setItem('revisemy_try_client', id) } catch (e) {}
                            },
                            setMode(next) {
                                this.mode = next;
                                try { sessionStorage.setItem('revisemy_try_setup_mode', next) } catch (e) {}
                            },
                        }"
                    >
                        @php
                            $setupPrompts = json_decode($setupPromptsJson ?: '[]', true) ?: [];
                            $checkupPrompts = json_decode($checkupPromptsJson ?: '[]', true) ?: [];
                            $fallbackCheckups = \App\Services\TryTokenService::checkupPrompts();
                            $checkupPrompts = array_merge($fallbackCheckups, $checkupPrompts);
                        @endphp
                        <div class="order-1">
                            <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">
                                Agent
                            </p>
                            <div class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1">
                                @foreach ([
                                    'chatgpt' => 'ChatGPT',
                                    'claude' => 'Claude',
                                    'copilot' => 'Copilot',
                                    'cursor' => 'Cursor',
                                    'grok' => 'Grok',
                                ] as $id => $label)
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                        :class="client === '{{ $id }}' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-800'"
                                        x-on:click="setClient('{{ $id }}')"
                                    >
                                        <x-host-icon :name="$id" class="opacity-90" />
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div :class="mode === 'manual' ? 'order-2' : 'order-4'">
                            <x-try-credentials
                                :mcp-url="$mcpUrl"
                                :token="$token"
                                :token-expires-at="$tokenExpiresAt"
                            />
                        </div>

                        <div class="order-3 space-y-5">
                        {{-- ChatGPT --}}
                        <div x-show="client === 'chatgpt'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Add ReviseMy as a <span class="font-medium text-zinc-800">remote MCP connector</span> (or a Custom GPT Action for REST).
                                If the host supports MCP Apps, the review can open inline; otherwise your agent shares a <code class="font-mono text-[13px]">review_url</code>.
                            </p>
                            <x-host-setup>
                                <x-slot:agent>
                                    <p class="text-[15px] leading-relaxed text-zinc-600">
                                        Copy this into ChatGPT and let it walk you through Connectors (or Custom GPT Actions). Values are already filled in.
                                    </p>
                                    <x-setup-journey>
                                        @if (! empty($setupPrompts['chatgpt']))
                                            <x-copy-prompt
                                                step="1"
                                                label="Paste this to ChatGPT"
                                                :text="$setupPrompts['chatgpt']"
                                                multiline
                                            />
                                        @else
                                            <p class="text-sm text-zinc-500">Generate a new token from the credentials card to refresh agent setup prompts.</p>
                                        @endif
                                        <x-copy-prompt
                                            step="2"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['chatgpt']"
                                        />
                                    </x-setup-journey>
                                </x-slot:agent>
                                <x-slot:manual>
                                    <x-setup-journey>
                                        <x-setup-step step="1" label="Open Connectors in ChatGPT">
                                            <p class="text-[15px] leading-relaxed text-zinc-600">
                                                Open <span class="font-medium text-zinc-800">Settings → Connectors</span>
                                                (or Custom GPT → Actions for REST) and add a remote MCP connector named
                                                <span class="font-medium text-zinc-800">revisemy</span>.
                                            </p>
                                            <p class="text-[13px] leading-relaxed text-zinc-500">
                                                ChatGPT’s connector UI varies by plan. Look for server URL and Authorization / Bearer fields — not a full JSON paste.
                                            </p>
                                        </x-setup-step>
                                        <x-setup-step step="2" label="Paste the Authorization header">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Authorization</p>
                                                <button
                                                    type="button"
                                                    class="text-sm text-rose-600 hover:text-rose-500"
                                                    x-data
                                                    x-on:click="navigator.clipboard.writeText($refs.chatgptAuth.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                                >Copy</button>
                                            </div>
                                            <pre x-ref="chatgptAuth" class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-950 p-3 font-mono text-[12px] leading-relaxed text-rose-100/90">Authorization: Bearer {{ $token }}</pre>
                                            <p class="text-[13px] leading-relaxed text-zinc-500">
                                                Also paste the MCP URL from the credentials card into the connector’s server URL field.
                                            </p>
                                        </x-setup-step>
                                        <x-copy-prompt
                                            step="3"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['chatgpt']"
                                        />
                                    </x-setup-journey>
                                </x-slot:manual>
                            </x-host-setup>
                        </div>

                        {{-- Claude --}}
                        <div x-show="client === 'claude'" x-cloak class="space-y-5">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Claude has two paths: <span class="font-medium text-zinc-800">Desktop</span> can render the review
                                <span class="font-medium text-zinc-800">inline in chat</span> via MCP Apps.
                                <span class="font-medium text-zinc-800">Claude Code</span> is CLI-only — it shares a <code class="font-mono text-[13px]">review_url</code> instead.
                            </p>
                            <x-host-setup>
                                <x-slot:agent>
                                    <div x-data="{ claudePath: 'desktop' }" class="space-y-4">
                                        <x-claude-path-picker />
                                        <x-setup-journey>
                                            <div x-show="claudePath === 'code'" x-cloak class="space-y-4 sm:space-y-5">
                                                @if (! empty($setupPrompts['claude_code']))
                                                    <x-copy-prompt
                                                        step="1"
                                                        label="Paste this to Claude Code"
                                                        :text="$setupPrompts['claude_code']"
                                                        multiline
                                                    />
                                                @else
                                                    <p class="text-sm text-zinc-500">Generate a new token from the credentials card to refresh agent setup prompts.</p>
                                                @endif
                                                <x-copy-prompt
                                                    step="2"
                                                    label="Then run a checkup"
                                                    :text="$checkupPrompts['claude_code']"
                                                />
                                            </div>
                                            <div x-show="claudePath === 'desktop'" x-cloak class="space-y-4 sm:space-y-5">
                                                @if (! empty($setupPrompts['claude_desktop']))
                                                    <x-copy-prompt
                                                        step="1"
                                                        label="Paste this to Claude Desktop"
                                                        :text="$setupPrompts['claude_desktop']"
                                                        multiline
                                                    />
                                                @else
                                                    <p class="text-sm text-zinc-500">Generate a new token from the credentials card to refresh agent setup prompts.</p>
                                                @endif
                                                <x-copy-prompt
                                                    step="2"
                                                    label="Then run a checkup"
                                                    :text="$checkupPrompts['claude_desktop']"
                                                />
                                            </div>
                                        </x-setup-journey>
                                    </div>
                                </x-slot:agent>
                                <x-slot:manual>
                                    <div x-data="{ claudePath: 'desktop' }" class="space-y-4">
                                        <x-claude-path-picker />
                                        <x-setup-journey>
                                            <div x-show="claudePath === 'code'" x-cloak class="space-y-4 sm:space-y-5">
                                                <x-setup-step step="1" label="Run this in your project terminal">
                                                    <p class="text-[15px] leading-relaxed text-zinc-600">
                                                        Claude Code will share a <code class="font-mono text-[13px]">review_url</code> after
                                                        <code class="font-mono text-[13px] text-rose-600">create_review</code> — open it to mark and approve.
                                                    </p>
                                                    <div>
                                                        <div class="mb-2 flex items-center justify-between">
                                                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Claude Code</p>
                                                            <button
                                                                type="button"
                                                                class="text-sm text-rose-600 hover:text-rose-500"
                                                                x-data
                                                                x-on:click="navigator.clipboard.writeText($refs.claudeCmd.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                                            >Copy</button>
                                                        </div>
                                                        <pre x-ref="claudeCmd" class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-950 p-3 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $claudeCodeCommand }}</pre>
                                                    </div>
                                                </x-setup-step>
                                                <x-copy-prompt
                                                    step="2"
                                                    label="Then run a checkup"
                                                    :text="$checkupPrompts['claude_code']"
                                                />
                                            </div>
                                            <div x-show="claudePath === 'desktop'" x-cloak class="space-y-4 sm:space-y-5">
                                                <x-setup-step step="1" label="Edit Claude Desktop config">
                                                    <p class="text-[15px] leading-relaxed text-zinc-600">
                                                        Open <span class="font-medium text-zinc-800">Settings → Developer → Edit Config</span>
                                                        (<code class="font-mono text-[13px]">claude_desktop_config.json</code>).
                                                        Merge the JSON below into <code class="font-mono text-[13px]">mcpServers</code>, save, then fully quit and reopen Claude Desktop.
                                                    </p>
                                                    <div class="rounded-lg border border-amber-200/80 bg-amber-50/80 px-3 py-2.5 text-[13px] leading-relaxed text-amber-950/80">
                                                        Don’t use <span class="font-medium">Connectors → Add custom connector</span> — that UI is OAuth-oriented and has no Bearer header field. Needs Node.js (<code class="font-mono text-[12px]">npx</code>).
                                                    </div>
                                                    <div>
                                                        <div class="mb-2 flex items-center justify-between">
                                                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Claude Desktop config</p>
                                                            <button
                                                                type="button"
                                                                class="text-sm text-rose-600 hover:text-rose-500"
                                                                x-data
                                                                x-on:click="navigator.clipboard.writeText($refs.claudeDesktop.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                                            >Copy</button>
                                                        </div>
                                                        <pre x-ref="claudeDesktop" class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-950 p-3 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $claudeDesktopConfigJson }}</pre>
                                                    </div>
                                                </x-setup-step>
                                                <x-copy-prompt
                                                    step="2"
                                                    label="Then run a checkup"
                                                    :text="$checkupPrompts['claude_desktop']"
                                                />
                                            </div>
                                        </x-setup-journey>
                                    </div>
                                </x-slot:manual>
                            </x-host-setup>
                        </div>

                        {{-- Copilot --}}
                        <div x-show="client === 'copilot'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Copilot supports MCP Apps — after <code class="font-mono text-[13px] text-rose-600">create_review</code>, the review renders
                                <span class="font-medium text-zinc-800">inline in Copilot Chat</span> so you can mark and approve without leaving the editor.
                            </p>
                            <x-host-setup>
                                <x-slot:agent>
                                    <p class="text-[15px] leading-relaxed text-zinc-600">
                                        Copy this into Copilot Chat and let it merge the MCP config for you.
                                    </p>
                                    <x-setup-journey>
                                        @if (! empty($setupPrompts['copilot']))
                                            <x-copy-prompt
                                                step="1"
                                                label="Paste this to Copilot"
                                                :text="$setupPrompts['copilot']"
                                                multiline
                                            />
                                        @else
                                            <p class="text-sm text-zinc-500">Generate a new token from the credentials card to refresh agent setup prompts.</p>
                                        @endif
                                        <x-copy-prompt
                                            step="2"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['copilot']"
                                        />
                                    </x-setup-journey>
                                </x-slot:agent>
                                <x-slot:manual>
                                    <x-setup-journey>
                                        <x-setup-step step="1" label="Open Copilot → MCP">
                                            <p class="text-[15px] leading-relaxed text-zinc-600">
                                                Open <span class="font-medium text-zinc-800">Copilot → MCP</span>
                                                (user or workspace <code class="font-mono text-[13px]">mcp.json</code>) and merge the config below under
                                                <code class="font-mono text-[13px]">servers</code>. Reload Copilot / the window if tools don’t appear.
                                            </p>
                                            @if ($copilotConfigJson)
                                                <div>
                                                    <div class="mb-2 flex items-center justify-between">
                                                        <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Copilot MCP config</p>
                                                        <button
                                                            type="button"
                                                            class="text-sm text-rose-600 hover:text-rose-500"
                                                            x-data
                                                            x-on:click="navigator.clipboard.writeText($refs.copilotConfig.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                                        >Copy</button>
                                                    </div>
                                                    <pre x-ref="copilotConfig" class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-950 p-3 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $copilotConfigJson }}</pre>
                                                </div>
                                            @else
                                                <p class="text-sm text-zinc-500">
                                                    Generate a new token from the credentials card to refresh Copilot config (older saved sessions may not include it).
                                                </p>
                                            @endif
                                        </x-setup-step>
                                        <x-copy-prompt
                                            step="2"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['copilot']"
                                        />
                                    </x-setup-journey>
                                </x-slot:manual>
                            </x-host-setup>
                        </div>

                        {{-- Cursor --}}
                        <div x-show="client === 'cursor'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Cursor agents use MCP tools in the IDE. After <code class="font-mono text-[13px] text-rose-600">create_review</code>, the agent shares a
                                <code class="font-mono text-[13px]">review_url</code> — open it in the browser to mark and approve. (No inline MCP Apps UI in Cursor yet.)
                            </p>
                            <x-host-setup>
                                <x-slot:agent>
                                    <p class="text-[15px] leading-relaxed text-zinc-600">
                                        Copy this into Cursor Agent chat and let it edit <code class="font-mono text-[13px]">~/.cursor/mcp.json</code> for you.
                                    </p>
                                    <x-setup-journey>
                                        @if (! empty($setupPrompts['cursor']))
                                            <x-copy-prompt
                                                step="1"
                                                label="Paste this to Cursor"
                                                :text="$setupPrompts['cursor']"
                                                multiline
                                            />
                                        @else
                                            <p class="text-sm text-zinc-500">Generate a new token from the credentials card to refresh agent setup prompts.</p>
                                        @endif
                                        <x-copy-prompt
                                            step="2"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['cursor']"
                                        />
                                    </x-setup-journey>
                                </x-slot:agent>
                                <x-slot:manual>
                                    <x-setup-journey>
                                        <x-setup-step step="1" label="Add ReviseMy in Cursor MCP">
                                            <p class="text-[15px] leading-relaxed text-zinc-600">
                                                Open <span class="font-medium text-zinc-800">Cursor Settings → MCP</span>,
                                                then add a server or merge the JSON below into
                                                <code class="font-mono text-[13px]">~/.cursor/mcp.json</code>.
                                                Enable <span class="font-medium text-zinc-800">revisemy</span> when it appears.
                                            </p>
                                            <div>
                                                <div class="mb-2 flex items-center justify-between">
                                                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Cursor MCP config</p>
                                                    <button
                                                        type="button"
                                                        class="text-sm text-rose-600 hover:text-rose-500"
                                                        x-data
                                                        x-on:click="navigator.clipboard.writeText($refs.cursorConfig.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                                    >Copy</button>
                                                </div>
                                                <pre x-ref="cursorConfig" class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-950 p-3 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $cursorConfigJson }}</pre>
                                            </div>
                                        </x-setup-step>
                                        <x-copy-prompt
                                            step="2"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['cursor']"
                                        />
                                    </x-setup-journey>
                                </x-slot:manual>
                            </x-host-setup>
                        </div>

                        {{-- Grok --}}
                        <div x-show="client === 'grok'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Add ReviseMy as a <span class="font-medium text-zinc-800">custom MCP connector</span> on Grok.
                                After <code class="font-mono text-[13px] text-rose-600">create_review</code>, open the <code class="font-mono text-[13px]">review_url</code> to mark and approve.
                            </p>
                            <x-host-setup>
                                <x-slot:agent>
                                    <p class="text-[15px] leading-relaxed text-zinc-600">
                                        Copy this into Grok and let it walk you through the custom connector UI. Values are already filled in.
                                    </p>
                                    <x-setup-journey>
                                        @if (! empty($setupPrompts['grok']))
                                            <x-copy-prompt
                                                step="1"
                                                label="Paste this to Grok"
                                                :text="$setupPrompts['grok']"
                                                multiline
                                            />
                                        @else
                                            <p class="text-sm text-zinc-500">Generate a new token from the credentials card to refresh agent setup prompts.</p>
                                        @endif
                                        <x-copy-prompt
                                            step="2"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['grok']"
                                        />
                                    </x-setup-journey>
                                </x-slot:agent>
                                <x-slot:manual>
                                    <x-setup-journey>
                                        <x-setup-step step="1" label="Add a custom connector on Grok">
                                            <p class="text-[15px] leading-relaxed text-zinc-600">
                                                Go to
                                                <a href="https://grok.com/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 hover:text-rose-500" target="_blank" rel="noreferrer">grok.com/connectors</a>,
                                                click <span class="font-medium text-zinc-800">New Connector → Custom</span>,
                                                name it <span class="font-medium text-zinc-800">revisemy</span>, paste the MCP URL, and set Authorization to the header below.
                                            </p>
                                            <p class="text-[13px] leading-relaxed text-zinc-500">
                                                Use the connector’s server URL and Authorization fields — not the full JSON block.
                                            </p>
                                            <div>
                                                <div class="mb-2 flex items-center justify-between">
                                                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Authorization</p>
                                                    <button
                                                        type="button"
                                                        class="text-sm text-rose-600 hover:text-rose-500"
                                                        x-data
                                                        x-on:click="navigator.clipboard.writeText($refs.grokAuth.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                                    >Copy</button>
                                                </div>
                                                <pre x-ref="grokAuth" class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-950 p-3 font-mono text-[12px] leading-relaxed text-rose-100/90">Authorization: Bearer {{ $token }}</pre>
                                            </div>
                                        </x-setup-step>
                                        <x-copy-prompt
                                            step="2"
                                            label="Then run a checkup"
                                            :text="$checkupPrompts['grok']"
                                        />
                                    </x-setup-journey>
                                </x-slot:manual>
                            </x-host-setup>
                        </div>
                        </div>
                    </div>
                @endif
            </x-home-section>

            {{-- Pricing --}}
            @php
                $freeCredits = (int) config('billing.plans.free.credits', 20);
                $plusCredits = (int) config('billing.plans.pro.credits', 100);
                $freeRetention = (int) config('billing.plans.free.review_retention_days', 7);
                $plusRetention = (int) config('billing.plans.pro.review_retention_days', 90);
                $plusPrice = (int) config('billing.plans.pro.price_usd', 9);
            @endphp
            <x-home-section id="pricing" flush-bottom>
                <x-section-eyebrow number="04" label="Pricing" />
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">
                    Try it free. Keep it with Plus.
                </h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    Start with a try token — no ReviseMy account. Same capture quality on Try and Plus;
                    Try is a one-time credit pack so you can feel the loop. When you like it, your agent
                    opens Paddle Checkout via
                    <code class="bg-zinc-100 px-1 py-0.5 text-[13px] text-zinc-800">create_checkout</code>
                    — still without leaving your chat.
                </p>

                <div class="rm-bleed relative mt-10 border-t border-zinc-200">
                    <x-cross-mark left="0" top="0" />
                    <x-cross-mark left="100%" top="0" />
                    <x-cross-mark left="50%" top="0" visibility="hidden min-[30rem]:block" />

                    <div class="grid grid-cols-1 gap-px bg-[var(--color-border)] min-[30rem]:grid-cols-2">
                        <article class="bg-[var(--color-canvas)] p-7 sm:p-8">
                            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Try</p>
                            <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">
                                $0
                            </p>
                            <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
                                {{ $freeCredits }} credits once — no monthly refill.<br>
                                Reviews stick around {{ $freeRetention }}&nbsp;days.
                            </p>
                            <ul class="mt-6 space-y-2 text-[14px] text-zinc-600">
                                <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Same full capture quality</li>
                                <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> MCP try token, no account</li>
                                <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Agent-driven checkup loop</li>
                            </ul>
                            <div class="mt-8 flex flex-wrap items-center gap-3">
                                <x-try-token-button fathom-event="Try token pricing free" />
                                <flux:modal.trigger name="credit-cost-breakdown">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        class="!border !border-zinc-200 !bg-white hover:!border-zinc-300 hover:!bg-zinc-50"
                                    >
                                        Credit costs
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        </article>

                        <article class="relative overflow-hidden bg-[var(--color-canvas)] p-7 sm:p-8">
                            <div class="rm-plus-grid" aria-hidden="true"></div>
                            <div class="relative z-10">
                                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Plus</p>
                                <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">
                                    ${{ $plusPrice }}<span class="text-lg font-medium text-zinc-500">/mo</span>
                                </p>
                                <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
                                    {{ $plusCredits }} credits each month.<br>
                                    Reviews stick around {{ $plusRetention }}&nbsp;days.
                                </p>
                                <ul class="mt-6 space-y-2 text-[14px] text-zinc-600">
                                    <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Same full capture quality</li>
                                    <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Keep using after your try</li>
                                    <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Cancel anytime via your agent</li>
                                </ul>
                                <div class="mt-8">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        href="#setup"
                                        class="!border !border-zinc-200 !bg-white hover:!border-zinc-300 hover:!bg-zinc-50"
                                    >
                                        Upgrade via your agent
                                    </flux:button>
                                    <p class="mt-3 max-w-xs text-[13px] leading-relaxed text-zinc-500">
                                        After you try it, ask your agent for
                                        <code class="bg-zinc-100 px-1 py-0.5 text-[12px] text-zinc-800">create_checkout</code>
                                        and open the Paddle link.
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <flux:modal name="credit-cost-breakdown" class="max-w-md">
                    <div class="space-y-1">
                        <flux:heading size="lg">Credit costs</flux:heading>
                        <flux:text class="text-[14px] !text-zinc-500">
                            Try vs Plus. Burn is the same; only the pack size differs.
                        </flux:text>
                    </div>
                    <div class="mt-5">
                        <x-billing.credit-costs compare :show-label="false" />
                        <p class="mt-4 text-[14px] leading-relaxed text-zinc-500">
                            Plus credits reset monthly (no rollover). Try is one-time.
                        </p>
                    </div>
                </flux:modal>
            </x-home-section>

            {{-- FAQ --}}
            <x-home-section id="faq">
                <div class="max-w-xl">
                    <x-section-eyebrow number="05" label="FAQ" />
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">FAQ</h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        Short answers on hosts, links, marks, passes, billing, and sharing.
                    </p>

                    <div class="mt-8 divide-y divide-zinc-200 border-t border-zinc-200">
                        <details class="group py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>Got a link — where’s the inline review?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                You always get a <code class="font-mono text-[13px]">review_url</code>. Open it, mark, approve — works everywhere. On
                                <a href="/connectors#claude" class="text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">MCP Apps</a>
                                hosts (Claude web/Desktop, Copilot, …) the same review can also show up <span class="font-medium text-zinc-800">inline in chat</span>. Cursor, Claude Code, and Grok are link-only for now — same loop, just in a tab.
                            </p>
                        </details>

                        <details class="group py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>Do I need to sign up?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                Nope. Grab a try token in <a href="#setup" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Try with your agent</a> — no ReviseMy account. See <a href="#pricing" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Pricing</a> for Try vs Plus (same full quality; Try is one-time credits, Plus is monthly via your agent’s <code class="font-mono text-[13px]">create_checkout</code>).
                            </p>
                        </details>

                        <details id="faq-upgrade-cancel" class="group scroll-mt-24 py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>How do I upgrade or cancel?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                Ask your agent for <code class="font-mono text-[13px]">create_checkout</code> — it opens a Paddle link for Plus (${{ (int) config('billing.plans.pro.price_usd', 9) }}/mo). For card or receipts, use <code class="font-mono text-[13px]">create_portal</code>. To leave Plus, <code class="font-mono text-[13px]">cancel_subscription</code> with <code class="font-mono text-[13px]">confirm:true</code> — you keep Plus until the period ends, then Try with leftover credits only (no new grant). See <a href="#pricing" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Pricing</a>.
                            </p>
                        </details>

                        <details id="faq-credits" class="group scroll-mt-24 py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>What happens when I run out of credits?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                On Try, new checkups pause until you upgrade — there’s no monthly refill. On Plus, unused credits don’t roll over; you get a fresh grant next month. Burn is the same (images/PDF&nbsp;=&nbsp;1, HTML&nbsp;=&nbsp;3, live URL&nbsp;=&nbsp;5). Your agent can check with <code class="font-mono text-[13px]">get_billing</code>, or open <code class="font-mono text-[13px]">create_checkout</code> for Plus.
                            </p>
                        </details>

                        <details id="faq-cancel-reviews" class="group scroll-mt-24 py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>If I cancel Plus, what happens to my reviews?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                You keep Plus access through the end of the billing period. After that you’re on Try — no new credit grant, and review retention drops from {{ (int) config('billing.plans.pro.review_retention_days', 90) }} days to {{ (int) config('billing.plans.free.review_retention_days', 7) }}. Older reviews may age out once they’re past Try’s window, so finish or export anything you still need before the period ends.
                            </p>
                        </details>

                        <details class="group py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>My marks vs second opinion vs guest — who’s in charge?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                You. <span class="font-medium text-zinc-800">M# marks</span> are the brief — that’s what the agent should fix. <span class="font-medium text-zinc-800">S#</span> second opinion and <span class="font-medium text-zinc-800">G#</span> guest notes stay suggestions until you accept them.
                            </p>
                        </details>

                        <details id="faq-second-opinion-keys" class="group scroll-mt-24 py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>Do I need API keys for second opinion?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                For the free <span class="font-medium text-zinc-800">checklist</span>, no — it runs on every upload. For <span class="font-medium text-zinc-800">vision</span> hints that mark regions on the capture, add <span class="font-medium text-zinc-800">your own</span> <code class="font-mono text-[13px] text-rose-600">ANTHROPIC_API_KEY</code> or <code class="font-mono text-[13px] text-rose-600">OPENAI_API_KEY</code> on the server (your deploy, your usage). Optional: <code class="font-mono text-[13px] text-rose-600">REVISEMY_VISION_PROVIDER</code> to pick Claude vs OpenAI, custom models, or <code class="font-mono text-[13px] text-rose-600">REVISEMY_OPENAI_BASE_URL</code> for Ollama and other OpenAI-compatible endpoints. Then <span class="font-medium text-zinc-800">Refresh second opinion</span> on the review. Still suggestions — your marks win. See <a href="/second-opinion" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">second opinion</a> for the full picture.
                            </p>
                        </details>

                        <details class="group py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>What can my agent send?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                One source per review: screenshots, a live URL (desktop + mobile captures), a PDF deck, or email HTML. See <a href="#how" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">how it works</a> for the full loop.
                            </p>
                        </details>

                        <details class="group py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>What does the agent do after I decide?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                Poll <code class="font-mono text-[13px] text-rose-600">get_review</code> and follow <code class="font-mono text-[13px]">next_action</code>: wait while you mark, fix your pins and spin up the next pass, or stop when you approve.
                            </p>
                        </details>

                        <details class="group py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>What’s the board — and what’s a pass?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                The board is your checklist: each mark moves <span class="font-medium text-zinc-800">open → resolved → verified</span>. Request changes and your agent fixes what you marked, then uploads fresh captures for <span class="font-medium text-zinc-800">pass 2</span> (and on). Agents can attach before/after shots when they resolve a mark — you verify when it actually looks right. See <a href="/board" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">the board</a>.
                            </p>
                        </details>

                        <details class="group py-4">
                            <summary class="cursor-pointer list-none text-[15px] font-medium text-zinc-900 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="flex items-start justify-between gap-4">
                                    <span>Can I share a review with someone else?</span>
                                    <span class="mt-0.5 shrink-0 text-zinc-400 transition group-open:rotate-180" aria-hidden="true">▾</span>
                                </span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
                                Yep. Your review link is secret — anyone with it can mark and decide. Need a teammate or client? Use <span class="font-medium text-zinc-800">Share</span> to copy or regenerate a <span class="font-medium text-zinc-800">guest link</span> (suggestions only; your marks still run the show). Set expiry to <span class="font-medium text-zinc-800">7 days</span> (default), <span class="font-medium text-zinc-800">14 days</span>, <span class="font-medium text-zinc-800">never</span>, or a custom date. See <a href="/guest-links" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">guest links</a>.
                            </p>
                        </details>
                    </div>
                </div>
            </x-home-section>

            {{-- Closing: maker + why + contact --}}
            <x-home-section id="feedback">
                <div class="max-w-xl">
                    <x-section-eyebrow number="06" label="Maker" />
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Why I made ReviseMy</h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        I’m
                        <a
                            href="https://heyderekj.com"
                            target="_blank"
                            rel="noreferrer"
                            class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >Derek</a>
                        — I love giving design feedback. Not to be a dick, but to be a Derek. Agents are getting fast at shipping UI; what’s still missing is a clear place for a human to mark what matters and send the next pass back without leaving the chat.
                    </p>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        ReviseMy started as a
                        <a
                            href="https://heyderekj.com/projects/revisemy/"
                            target="_blank"
                            rel="noreferrer"
                            class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >side project</a>
                        in 2024 — an idea and a Figma file — then
                        <a
                            href="https://x.com/taylorotwell/status/2075667366646858222"
                            target="_blank"
                            rel="noreferrer"
                            class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >Taylor’s Laravel Cloud weekend challenge</a>
                        was the nudge to ship it as an MCP on Laravel. Built in a weekend on the side; it works, it passes tests, and there’s plenty left to improve.
                        <a
                            href="https://github.com/sponsors/heyderekj"
                            target="_blank"
                            rel="noreferrer"
                            class="ml-1.5 inline-flex translate-y-[-1px] items-center gap-1 border border-rose-200/80 bg-rose-50 px-2 py-0.5 text-[12px] font-medium text-zinc-700 transition hover:border-rose-300 hover:bg-rose-100 hover:text-rose-700"
                        >
                            <svg class="size-3 text-rose-500" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M8 14.25c-.2 0-.4-.06-.57-.18C5.6 12.7 2 9.72 2 6.4 2 4.3 3.6 2.75 5.7 2.75c1.1 0 2.1.5 2.8 1.35A3.8 3.8 0 0 1 11.3 2.75C13.4 2.75 15 4.3 15 6.4c0 3.32-3.6 6.3-5.43 7.67A.9.9 0 0 1 8 14.25Z"/>
                            </svg>
                            Sponsor on GitHub
                        </a>
                    </p>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        Source is on
                        <a
                            href="https://github.com/heyderekj/revisemy"
                            target="_blank"
                            rel="noreferrer"
                            class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >GitHub</a>
                        under the
                        <a
                            href="https://osaasy.dev/"
                            target="_blank"
                            rel="noreferrer"
                            class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >O’Saasy License</a>
                        — use it, fork it, file issues, send PRs.
                    </p>
                    <p class="mt-6 text-[15px] leading-relaxed text-zinc-600">
                        Say hi anytime —
                        <a
                            href="mailto:derekj@hey.com"
                            class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >derekj@hey.com</a>
                        or
                        <a
                            href="https://x.com/heyderekj"
                            target="_blank"
                            rel="noreferrer"
                            class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >@heyderekj on X.com</a>.
                    </p>

                    <div class="mt-8">
                        <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Also by Derek</p>
                        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-[15px] text-zinc-600">
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
            </x-home-section>

            <x-site-footer />
        </main>
        </div>
    </div>

    {{-- Mobile nav drawer --}}
    <div class="lg:hidden" x-cloak>
        <div
            class="fixed inset-0 z-50 bg-zinc-900/25 backdrop-blur-[2px]"
            x-show="mobileNav"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="mobileNav = false"
            aria-hidden="true"
        ></div>
        <aside
            id="rm-mobile-nav"
            class="fixed inset-y-0 right-0 z-50 flex w-[min(100%,20rem)] flex-col border-l border-zinc-200 bg-zinc-50/95 shadow-xl backdrop-blur-md"
            role="dialog"
            aria-modal="true"
            aria-label="Site menu"
            x-show="mobileNav"
            x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-3">
                <a href="/" class="inline-flex items-center hover:opacity-90" aria-label="ReviseMy home" x-on:click="mobileNav = false">
                    <x-revisemy-logo variant="wordmark" size="lg" />
                </a>
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-md text-zinc-800 transition hover:bg-zinc-100 active:scale-[0.97]"
                    x-on:click="mobileNav = false"
                    aria-label="Close menu"
                >
                    <span class="relative flex h-[14px] w-[18px]" aria-hidden="true">
                        <span class="absolute left-0 top-[6.25px] block h-[1.5px] w-full rotate-45 rounded-full bg-current"></span>
                        <span class="absolute left-0 top-[6.25px] block h-[1.5px] w-full -rotate-45 rounded-full bg-current"></span>
                    </span>
                </button>
            </div>

            <nav class="flex flex-1 flex-col gap-8 overflow-y-auto px-5 py-8 text-[14px]">
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Overview</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#top" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">Home</a></li>
                        <li><a href="#how" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">How it works</a></li>
                        <li><a href="#agents" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">For agents</a></li>
                        <li><a href="#pricing" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">Pricing</a></li>
                        <li><a href="#faq" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Tools</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li><a href="#setup" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">Try with your agent</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Resources</p>
                    <ul class="space-y-2.5 text-zinc-600">
                        <li>
                            <a href="/connectors" class="inline-flex items-center gap-2 py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">
                                Connectors
                                <span class="rounded bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-accent-contrast">MCP</span>
                            </a>
                        </li>
                        <li><a href="/guest-links" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">Guest links</a></li>
                        <li><a href="/alternatives" class="block py-0.5 transition hover:text-zinc-900" x-on:click="mobileNav = false">Alternatives</a></li>
                        <li>
                            <a href="https://github.com/heyderekj/revisemy" class="block py-0.5 transition hover:text-zinc-900" target="_blank" rel="noreferrer" x-on:click="mobileNav = false">
                                GitHub ↗
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="mt-auto space-y-4 border-t border-zinc-200 px-5 py-5">
                <x-try-token-button fathom-event="Try token drawer" class="w-full justify-center sm:hidden" />
                <p class="font-mono text-[11px] text-zinc-400">v{{ config('revisemy.version') }}</p>
            </div>
        </aside>
    </div>

    {{-- Mobile sticky try CTA removed: sticky header shows Try once #rm-hero-cta leaves view. --}}
</div>
