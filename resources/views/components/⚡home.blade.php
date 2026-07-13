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

    public ?string $copilotConfigJson = null;

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
        $this->copilotConfigJson = json_encode($result['copilot_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->claudeCodeCommand = $result['claude_code_command'];

        $this->dispatch('revisemy-try-setup-saved', payload: [
            'token' => $this->token,
            'mcpUrl' => $this->mcpUrl,
            'cursorConfigJson' => $this->cursorConfigJson,
            'claudeDesktopConfigJson' => $this->claudeDesktopConfigJson,
            'copilotConfigJson' => $this->copilotConfigJson,
            'claudeCodeCommand' => $this->claudeCodeCommand,
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
    ): void {
        $this->token = $token;
        $this->mcpUrl = $mcpUrl;
        $this->cursorConfigJson = $cursorConfigJson;
        $this->claudeDesktopConfigJson = $claudeDesktopConfigJson;
        $this->copilotConfigJson = $copilotConfigJson ?: null;
        $this->claudeCodeCommand = $claudeCodeCommand;
        $this->error = null;
    }

    public function clearTryTokenSetup(): void
    {
        $this->token = null;
        $this->mcpUrl = null;
        $this->cursorConfigJson = null;
        $this->claudeDesktopConfigJson = null;
        $this->copilotConfigJson = null;
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
        }
    }"
    x-init="
        initStickyCta();
        openFaqFromHash();
        window.addEventListener('hashchange', () => openFaqFromHash());
    "
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
                        <li><a href="#agents" class="transition hover:text-zinc-900">For agents</a></li>
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
                            <a href="https://github.com/heyderekj/revisemy" class="transition hover:text-zinc-900" target="_blank" rel="noreferrer">
                                GitHub ↗
                            </a>
                        </li>
                        <li>
                            <a href="/connectors" class="inline-flex items-center gap-2 transition hover:text-zinc-900">
                                Connectors
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
                <a href="#agents" class="block" x-on:click="mobileNav = false">For agents</a>
                <a href="#faq" class="block" x-on:click="mobileNav = false">FAQ</a>
                <a href="#setup" class="block" x-on:click="mobileNav = false">Try with your agent</a>
                <a href="https://github.com/heyderekj/revisemy" target="_blank" rel="noreferrer" class="block">GitHub ↗</a>
            </div>

            {{-- Sticky try CTA (desktop only) until the setup section, where another try button lives --}}
            <div class="relative">
                <div class="pointer-events-none sticky top-5 z-30 hidden h-0 sm:block lg:top-6">
                    <div class="flex justify-end sm:pt-2">
                        <div class="pointer-events-auto">
                            <x-try-token-button fathom-event="Try token sidebar" />
                        </div>
                    </div>
                </div>

            {{-- Hero --}}
            <section class="rm-fade-up">
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
                    {{-- Mobile: inline button as before. Desktop: invisible spacer keeps hero layout while the sticky one floats. --}}
                    <x-try-token-button id="rm-hero-cta" fathom-event="Try token hero" class="self-start sm:hidden" />
                    <div class="invisible hidden pointer-events-none self-start sm:mt-2 sm:block" aria-hidden="true">
                        <x-try-token-button fathom-event="Try token setup" />
                    </div>
                </div>

                <p class="rm-fade-up-delay mt-5 w-full max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Human-in-the-loop design review for agents. Capture UI, websites, slides, or email from a screenshot, URL, PDF, or HTML; mark what matters; track fixes on the board; and send clear next steps back over MCP on
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

                {{-- Product shot: drop HQ files in public/images/hero/{desktop,mobile}.{png|webp|jpg} --}}
                @php
                    $heroDir = public_path('images/hero');
                    $heroPick = function (string $stem) use ($heroDir): array {
                        foreach (['png', 'webp', 'jpg', 'jpeg'] as $ext) {
                            $path = "{$heroDir}/{$stem}.{$ext}";
                            if (! is_file($path)) {
                                continue;
                            }
                            $size = @getimagesize($path) ?: [null, null];

                            return [
                                'url' => asset("images/hero/{$stem}.{$ext}"),
                                'width' => $size[0],
                                'height' => $size[1],
                            ];
                        }

                        return ['url' => null, 'width' => null, 'height' => null];
                    };
                    $heroDesktop = $heroPick('desktop');
                    $heroMobile = $heroPick('mobile');
                @endphp
                @if ($heroDesktop['url'] || $heroMobile['url'])
                    <div class="rm-fade-up-delay-2 relative mt-10 sm:mt-12">
                        <div class="overflow-hidden rounded-xl border border-zinc-900/10 bg-zinc-100 shadow-[0_18px_50px_-28px_rgba(24,24,27,0.45)]">
                            <picture>
                                @if ($heroDesktop['url'])
                                    <source
                                        media="(min-width: 640px)"
                                        srcset="{{ $heroDesktop['url'] }}"
                                        @if ($heroDesktop['width']) width="{{ $heroDesktop['width'] }}" @endif
                                        @if ($heroDesktop['height']) height="{{ $heroDesktop['height'] }}" @endif
                                    >
                                @endif
                                <img
                                    src="{{ $heroMobile['url'] ?? $heroDesktop['url'] }}"
                                    @if (($heroMobile['width'] ?? $heroDesktop['width'])) width="{{ $heroMobile['width'] ?? $heroDesktop['width'] }}" @endif
                                    @if (($heroMobile['height'] ?? $heroDesktop['height'])) height="{{ $heroMobile['height'] ?? $heroDesktop['height'] }}" @endif
                                    alt="ReviseMy review — mark feedback on a capture, then approve or request changes"
                                    class="block h-auto w-full"
                                    decoding="async"
                                    fetchpriority="high"
                                >
                            </picture>
                        </div>
                    </div>
                @endif
            </section>

            {{-- How it works: one loop in icon blocks --}}
            <section id="how" class="mt-20 scroll-mt-8 sm:mt-24">
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">One feedback loop for anything visual</h2>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    From capture to approval, ReviseMy keeps reviews, marks, guest feedback, and lifecycle together — so nothing gets lost between passes.
                </p>

                <div class="mt-8 grid grid-cols-1 gap-x-8 gap-y-9 min-[30rem]:grid-cols-2 lg:grid-cols-3">
                    <article>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                            <flux:icon.photo variant="micro" class="size-[18px]" />
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Capture anything visual</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Ask your agent to open a review from screenshots, a page URL (desktop + mobile), PDF slides, or email HTML — each type gets its own checklist and vision lens.</p>
                    </article>

                    <article>
                        <x-mark-type-icon type="s" />
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Second opinion</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Optional hints can land first — checklist immediately, optional Claude or OpenAI vision when a key is set. Useful suggestions, never decisions.</p>
                        <a
                            href="/second-opinion"
                            class="mt-2 inline-block text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                        >Learn more</a>
                    </article>

                    <article>
                        <x-mark-type-icon type="m" />
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Precise marks</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Open the review link, point or outline the exact area, set must-fix / nice to have / question / keep, and keep a threaded comment on each mark.</p>
                    </article>

                    <article>
                        <x-mark-type-icon type="g" />
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Guest eyes</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Share a private guest link when you want another set of eyes — no accounts. Your marks stay authoritative.</p>
                    </article>

                    <article>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                            <flux:icon.check variant="micro" class="size-[18px]" />
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Board to done</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Track marks from open → resolved → verified on the board. Agents can attach before/after evidence when they fix something.</p>
                    </article>

                    <article>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200">
                            <flux:icon.arrow-path variant="micro" class="size-[18px]" />
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-zinc-900">Approve and loop</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Approve or request changes. Structured next steps return over MCP — repeat until it feels right.</p>
                    </article>
                </div>

                <p class="mt-8 max-w-2xl text-[15px] leading-relaxed text-zinc-700">
                    <span class="rm-note box-decoration-clone">
                        <span class="font-medium">Try saying:</span> “Run a design checkup,” “review this URL,” or “address my feedback.” ReviseMy handles the MCP handoff inside the agent workflow you already use.
                    </span>
                </p>

                <div class="mt-14 border-t border-zinc-900/8 pt-12">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Built for every visual you ship</h2>
                    <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                        Same loop, different checklist — pick the artifact you’re reviewing.
                    </p>

                    <ul class="mt-8 grid grid-cols-1 gap-x-8 gap-y-6 min-[30rem]:grid-cols-2">
                        @foreach (config('use-cases.pages', []) as $slug => $useCase)
                            <li>
                                <a href="{{ url('/for/'.$slug) }}" class="group flex items-start gap-3">
                                    <x-use-case-icon
                                        :name="$useCase['icon']"
                                        class="mt-0.5 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-zinc-900 transition group-hover:text-rose-600">
                                            {{ $useCase['label'] }}
                                        </span>
                                        <span class="mt-1 block text-sm leading-relaxed text-zinc-500">
                                            {{ $useCase['teaser'] ?? $useCase['headline'] }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-8 text-sm text-zinc-500">
                        Also:
                        <a href="/for/reviewers" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">for reviewers</a>,
                        <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">connectors</a>,
                        or
                        <a href="/for" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">see all paths</a>.
                    </p>
                </div>
            </section>

            {{-- How agents use it --}}
            <section id="agents" class="mt-16 scroll-mt-8 sm:mt-20">
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
                                    d.copilotConfigJson ?? '',
                                    d.claudeCodeCommand ?? '',
                                );
                            }
                        } catch (e) {}
                    }
                "
            >
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Try it with your agent</h2>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600">
                    Choose the app you already use. Try free — no account required — and connect ChatGPT, Claude, Copilot, Cursor, or Grok.
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
                                'copilot' => 'Copilot',
                                'cursor' => 'Cursor',
                                'grok' => 'Grok',
                            ] as $id => $label)
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                    :class="client === '{{ $id }}' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-800'"
                                    x-on:click="client = '{{ $id }}'"
                                >{{ $label }}</button>
                            @endforeach
                        </div>

                        {{-- ChatGPT --}}
                        <div x-show="client === 'chatgpt'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Add ReviseMy as a <span class="font-medium text-zinc-800">remote MCP connector</span> (or Custom GPT Action for REST).
                                When the host supports MCP Apps, the review can render inline in chat; otherwise the agent shares a <code class="font-mono text-[13px]">review_url</code> link.
                            </p>
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>In ChatGPT, add a <span class="font-medium text-zinc-800">remote MCP / connector</span> (or Custom GPT Action)</li>
                                <li>Use the MCP URL and Bearer token below</li>
                                <li>Ask it to run a design checkup via <code class="font-mono text-[13px] text-rose-600">create_review</code> (MCP) or <code class="font-mono text-[13px]">POST /api/reviews</code> (REST)</li>
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

                        {{-- Claude --}}
                        <div x-show="client === 'claude'" x-cloak class="space-y-5">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Claude has two paths: <span class="font-medium text-zinc-800">Desktop and claude.ai</span> render the review
                                <span class="font-medium text-zinc-800">inline in chat</span> via MCP Apps (mark and approve there). <span class="font-medium text-zinc-800">Claude Code</span> is CLI-only — the agent shares a <code class="font-mono text-[13px]">review_url</code> link instead.
                            </p>

                            <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4">
                                <p class="text-sm font-medium text-zinc-800">Claude Desktop &amp; claude.ai — inline review (MCP Apps)</p>
                                <ol class="list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                    <li>Paste the JSON below into MCP / connector settings</li>
                                    <li>Ask Claude to capture your work and call <code class="font-mono text-[13px] text-rose-600">create_review</code></li>
                                    <li>Mark feedback and approve in the review panel inside the chat</li>
                                </ol>
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

                            <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                                <p class="text-sm font-medium text-zinc-800">Claude Code — terminal CLI</p>
                                <ol class="list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                    <li>Run the command below in your project terminal</li>
                                    <li>Ask Claude to call <code class="font-mono text-[13px] text-rose-600">create_review</code> and open the returned <code class="font-mono text-[13px]">review_url</code></li>
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
                            </div>
                        </div>

                        {{-- Copilot --}}
                        <div x-show="client === 'copilot'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Copilot supports MCP Apps — after <code class="font-mono text-[13px] text-rose-600">create_review</code>, the review renders
                                <span class="font-medium text-zinc-800">inline in Copilot Chat</span> so you can mark feedback and approve without leaving the editor.
                            </p>
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>Open <span class="font-medium text-zinc-800">Copilot → MCP</span> (user or workspace <code class="font-mono text-[13px]">mcp.json</code>)</li>
                                <li>Paste or merge the config below</li>
                                <li>Ask Copilot to capture your work and call <code class="font-mono text-[13px] text-rose-600">create_review</code></li>
                            </ol>
                            @if ($copilotConfigJson)
                                <div>
                                    <div class="mb-2 flex items-center justify-between">
                                        <p class="text-sm font-medium text-zinc-700">Copilot MCP config</p>
                                        <button
                                            type="button"
                                            class="text-sm text-rose-600 hover:text-rose-500"
                                            x-data
                                            x-on:click="navigator.clipboard.writeText($refs.copilotConfig.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                        >Copy</button>
                                    </div>
                                    <pre x-ref="copilotConfig" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">{{ $copilotConfigJson }}</pre>
                                </div>
                            @else
                                <p class="text-sm text-zinc-500">
                                    Start over above to generate a fresh try token with Copilot config (older saved sessions may not include it).
                                </p>
                            @endif
                        </div>

                        {{-- Cursor --}}
                        <div x-show="client === 'cursor'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Cursor agents use MCP tools in the IDE. After <code class="font-mono text-[13px] text-rose-600">create_review</code>, the agent shares a
                                <code class="font-mono text-[13px]">review_url</code> link — open it in the browser to mark feedback and approve. (No inline MCP Apps UI in Cursor.)
                            </p>
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>Open <span class="font-medium text-zinc-800">Cursor Settings → MCP</span></li>
                                <li>Paste the config below (or merge into <code class="font-mono text-[13px]">~/.cursor/mcp.json</code>)</li>
                                <li>Ask your agent to capture your work and call <code class="font-mono text-[13px] text-rose-600">create_review</code></li>
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

                        {{-- Grok --}}
                        <div x-show="client === 'grok'" x-cloak class="space-y-4">
                            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                                Add ReviseMy as a <span class="font-medium text-zinc-800">custom MCP connector</span> on Grok.
                                After <code class="font-mono text-[13px] text-rose-600">create_review</code>, open the <code class="font-mono text-[13px]">review_url</code> link to mark feedback and approve (no MCP Apps inline UI assumed yet).
                            </p>
                            <ol class="max-w-2xl list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>Go to <a href="https://grok.com/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 hover:text-rose-500" target="_blank" rel="noreferrer">grok.com/connectors</a></li>
                                <li>Click <span class="font-medium text-zinc-800">New Connector → Custom</span></li>
                                <li>Paste the MCP URL below and authorize with the Bearer token</li>
                                <li>Ask Grok to capture your work and call <code class="font-mono text-[13px] text-rose-600">create_review</code></li>
                            </ol>
                            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                                <p class="text-[13px] leading-relaxed text-zinc-600">
                                    The MCP server must be reachable on the public internet (Laravel Cloud is fine). Copy the URL and token into the custom connector’s server URL and Authorization fields.
                                </p>
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-medium text-zinc-700">Authorization header</p>
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-500"
                                        x-data
                                        x-on:click="navigator.clipboard.writeText($refs.grokAuth.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                                    >Copy</button>
                                </div>
                                <pre x-ref="grokAuth" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-rose-100/90">Authorization: Bearer {{ $token }}</pre>
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

                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                            <p class="text-sm font-medium text-zinc-800">After you connect</p>
                            <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
                                <li>Ask your agent to capture your work and call <code class="font-mono text-[13px] text-rose-600">create_review</code> (or try the MCP prompt <code class="font-mono text-[13px]">design_checkup_loop</code>)</li>
                                <li>
                                    <span class="font-medium text-zinc-800">MCP Apps hosts</span> (Claude Desktop, claude.ai, Copilot): mark and approve inline in chat
                                </li>
                                <li>
                                    <span class="font-medium text-zinc-800">CLI / link hosts</span> (Claude Code, Cursor, Grok): open the <code class="font-mono text-[13px]">review_url</code> the agent returns
                                </li>
                                <li>Your agent polls <code class="font-mono text-[13px] text-rose-600">get_review</code> and follows <code class="font-mono text-[13px]">next_action</code> until approved or a follow-up pass is needed</li>
                            </ol>
                        </div>
                    </div>
                @endif
            </section>

            {{-- FAQ --}}
            <section id="faq" class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
                <div class="max-w-xl">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">FAQ</h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        Short answers on hosts, links, marks, passes, and sharing.
                    </p>

                    <div class="mt-8 divide-y divide-zinc-900/8 border-t border-zinc-900/8">
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
                                Nope. Grab a free try token in <a href="#setup" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Try with your agent</a>, paste the MCP config, and go — no ReviseMy account.
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
                                The board is your checklist: each mark moves <span class="font-medium text-zinc-800">open → resolved → verified</span>. Request changes and your agent fixes what you marked, then uploads fresh captures for <span class="font-medium text-zinc-800">pass 2</span> (and on). Agents can attach before/after shots when they resolve a mark — you verify when it actually looks right.
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
                                Yep. Your review link is secret — anyone with it can mark and decide. Need a teammate or client? Use <span class="font-medium text-zinc-800">Share</span> to copy or regenerate a <span class="font-medium text-zinc-800">guest link</span> (suggestions only; your marks still run the show). Set expiry to <span class="font-medium text-zinc-800">7 days</span> (default), <span class="font-medium text-zinc-800">14 days</span>, <span class="font-medium text-zinc-800">never</span>, or a custom date.
                            </p>
                        </details>
                    </div>
                </div>
            </section>

            {{-- Closing: origin + feedback --}}
            <section id="feedback" class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
                <div class="max-w-xl">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Shipped, not finished</h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        ReviseMy sat as a
                        <a
                            href="https://heyderekj.com/projects/revisemy/"
                            target="_blank"
                            rel="noreferrer"
                            class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >side project on the back burner</a>
                        since September 2024 — an idea and a Figma file, because I love giving design feedback. Not to be a dick, but to be a Derek. Then
                        <a
                            href="https://x.com/taylorotwell/status/2075667366646858222"
                            target="_blank"
                            rel="noreferrer"
                            class="text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >Taylor Otwell’s Laravel Cloud weekend challenge</a>
                        was the nudge to ship an MCP on Laravel.
                    </p>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        Built in a weekend on the side — it passes tests, and of course it can still be improved. Curious what you think; any feedback is welcome.
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
                    <p class="mt-6 text-[15px] leading-relaxed text-zinc-600">
                        Email me at
                        <a
                            href="mailto:derekj@hey.com"
                            class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >derekj@hey.com</a>
                        or DM me on X at
                        <a
                            href="https://x.com/heyderekj"
                            target="_blank"
                            rel="noreferrer"
                            class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >x.com/heyderekj</a>.
                    </p>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        Open source on GitHub — code, issues, and PRs welcome.
                        <a
                            href="https://github.com/heyderekj/revisemy"
                            target="_blank"
                            rel="noreferrer"
                            class="ml-1 font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 transition hover:text-rose-600 hover:decoration-rose-300"
                        >View on GitHub ↗</a>
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
        <x-try-token-button fathom-event="Try token mobile" class="w-full justify-center !py-3 shadow-[0_16px_40px_-12px_rgba(225,29,72,0.6)]" />
    </div>
</div>
