<?php

use Livewire\Component;

/*
 * The homepage. Connecting lives in <livewire:connect-hub>, so the page
 * itself keeps no state.
 */
new class extends Component
{
};
?>

<x-site-shell cta-href="#setup" fathom="Try token" sticky-event="Try token sidebar">
    {{-- Agents the headline cycles through; the hero chat follows the same
         names on the same clock. Values are x-host-icon names. --}}
    @php
        $heroAgents = ['ChatGPT' => 'chatgpt', 'Claude' => 'claude', 'Copilot' => 'copilot', 'Cursor' => 'cursor', 'Grok' => 'grok'];
    @endphp

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
                        @foreach (array_keys($heroAgents) as $i => $label)
                            <span class="rm-agent-cycle-item" style="--i: {{ $i }}">{{ $label }}.</span>
                        @endforeach
                    </span>
                </span>
            </h1>
            {{-- Mobile: hero CTA while in view. Header picks it up once this scrolls away. --}}
            <div class="flex flex-wrap items-center gap-2 self-start lg:hidden">
                <x-try-token-button id="rm-hero-cta" data-rm-hero-cta fathom-event="Try token hero" />
                <x-your-reviews-button />
            </div>
            {{-- Holds the room the sticky Connect button takes on desktop. --}}
            <div class="hidden h-8 w-48 shrink-0 lg:block" aria-hidden="true"></div>
        </div>

        <p class="rm-fade-up-delay mt-5 w-full max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
            Your agent captures the work — a screenshot, a page, a PDF or an email — and sends you a review. You mark what matters and approve or ask for changes. It reads your marks and keeps going.
        </p>

        <x-pitch-video class="rm-fade-up-delay mt-6" />

        <div class="rm-fade-up-delay-2 relative mt-10 sm:mt-12">
            <x-hero-loop-preview :agents="$heroAgents" />
        </div>
    </x-home-section>

    {{-- How it works: joined to hero (no top rule) --}}
    <x-home-section id="how" joined>
        <x-section-eyebrow number="01" label="How it works" />
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">One feedback loop for anything visual</h2>
        <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
            Capture, marks, fixes and approval in one place, so nothing gets lost between passes.
        </p>

        <div class="relative mt-10">
            <div class="grid grid-cols-1 gap-3 min-[30rem]:grid-cols-2 lg:grid-cols-3">
                <article class="rounded-2xl bg-card p-7 lg:row-start-1">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-chip text-zinc-600">
                        <flux:icon.photo variant="micro" class="size-[18px]" />
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-zinc-900">Capture anything visual</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Screenshots, a live page on desktop, phone and tablet, PDF slides or email HTML. Each kind gets its own checklist.</p>
                </article>

                <article class="rounded-2xl bg-card p-7 lg:row-start-1">
                    <x-mark-type-icon type="s" />
                    <h3 class="mt-3 text-sm font-semibold text-zinc-900">Second opinion</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">A checklist on every capture, led by your DESIGN.md when you have one, and vision hints when a key is set. Suggestions, never decisions.</p>
                    <a
                        href="/second-opinion"
                        class="mt-2 inline-block text-sm link"
                    >Learn more</a>
                </article>

                <article class="rounded-2xl bg-card p-7 lg:row-start-1">
                    <x-mark-type-icon type="m" />
                    <h3 class="mt-3 text-sm font-semibold text-zinc-900">Precise marks</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Outline the exact area and say what it is: must fix, nice to have, question or keep. Suggested copy goes straight to the agent.</p>
                </article>

                <article class="rounded-2xl bg-card p-7 lg:row-start-2">
                    <x-mark-type-icon type="g" />
                    <h3 class="mt-3 text-sm font-semibold text-zinc-900">Guest links</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Send a private link for another set of eyes. No accounts, and your marks still decide.</p>
                    <a
                        href="/guest-links"
                        class="mt-2 inline-block text-sm link"
                    >Learn more</a>
                </article>

                <article class="rounded-2xl bg-card p-7 lg:row-start-2">
                    <x-use-case-icon name="queue-list" />
                    <h3 class="mt-3 text-sm font-semibold text-zinc-900">Board to done</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Marks go open, resolved, verified, with a before and after for each fix, across every pass.</p>
                    <a
                        href="/board"
                        class="mt-2 inline-block text-sm link"
                    >Learn more</a>
                </article>

                <article class="rounded-2xl bg-card p-7 lg:row-start-2">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-chip text-zinc-600">
                        <flux:icon.arrow-path variant="micro" class="size-[18px]" />
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-zinc-900">Approve and loop</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Approve or ask for changes. Your agent reads what’s next and keeps going. <a href="/reviews" class="link">Your reviews</a> lists every one.</p>
                </article>
            </div>
        </div>

        <div class="mt-12 flex flex-wrap items-center gap-2">
            <span class="mr-1 text-sm text-muted-foreground">Built for</span>
            @foreach (config('use-cases.pages', []) + config('use-cases.audiences', []) as $slug => $page)
                <a href="{{ url('/for/'.$slug) }}" class="inline-flex h-8 items-center gap-1.5 rounded-full bg-chip px-3 text-sm text-zinc-700 transition-colors hover:bg-chip-hover hover:text-zinc-900">
                    <x-use-case-icon :name="$page['icon']" size="sm" bare />
                    {{ $page['label'] }}
                </a>
            @endforeach
        </div>
    </x-home-section>

    {{-- How agents use it --}}
    <x-home-section id="agents">
        <x-section-eyebrow number="02" label="For agents" />
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">The technical handoff</h2>
        <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
            <code class="font-mono text-[13px] text-zinc-900">create_review</code> takes screenshots, a URL, a PDF or email HTML.
            <code class="font-mono text-[13px] text-zinc-900">get_review</code> returns your marks as work and one <code class="font-mono text-[13px] text-zinc-900">next_action</code>: wait, fix, open the next pass, or stop.
            The full tool list is in <a href="/llms.txt" class="link">llms.txt</a>.
        </p>
    </x-home-section>

    {{-- Setup: the one list of ways to connect (shared with /connect and /connectors). --}}
    <x-home-section id="setup" data-rm-end-cta>
        <x-section-eyebrow number="03" label="Connect" />
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Connect your agent</h2>
        <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600">
            Pick the one you use. Most connect by pasting one address and clicking Connect — no account, no token to copy.
        </p>

        <div class="mt-8">
            <livewire:connect-hub />
        </div>
    </x-home-section>

    {{-- Credits / Pricing --}}
    @php
        $pricingEnabled = (bool) config('billing.pricing_enabled', false);
        $freeCredits = (int) config('billing.plans.free.credits', 20);
        $plusCredits = (int) config('billing.plans.pro.credits', 100);
        $freeRetention = (int) config('billing.plans.free.review_retention_days', 7);
        $plusRetention = (int) config('billing.plans.pro.review_retention_days', 90);
        $plusPrice = (int) config('billing.plans.pro.price_usd', 9);
        $freeRenews = (bool) config('billing.plans.free.renews', true);
        $pack = collect(config('billing.packs', []))->first();
    @endphp
    <x-home-section id="pricing" x-data x-intersect.once.threshold.25="window.fathom && fathom.trackEvent('Pricing viewed')">
        <x-section-eyebrow number="04" :label="$pricingEnabled ? 'Pricing' : 'Credits'" />
        @if ($pricingEnabled)
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">
                Try it free. Keep it with Plus.
            </h2>
            <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                No account to start. Same capture quality on every plan.
                When you like it, your agent opens checkout with
                <span class="font-mono text-[13px] text-zinc-900">create_checkout</span>
                without leaving the chat. Heavy month? Top up with a credit pack.
            </p>
        @else
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Free for now</h2>
            <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                No account. {{ $freeCredits }} credits a month at full capture quality. Paid plans are paused while we work out pricing.
            </p>
        @endif

        <div class="relative mt-10">

            <div @class([
                'grid grid-cols-1 gap-3',
                'min-[30rem]:grid-cols-2' => $pricingEnabled,
            ])>
                <article class="rounded-2xl bg-card p-7 sm:p-8">
                    <p class="text-sm font-medium text-muted-foreground">Try</p>
                    <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">
                        $0
                    </p>
                    <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
                        @if ($freeRenews)
                            {{ $freeCredits }} credits each month — no rollover.<br>
                        @else
                            {{ $freeCredits }} credits once — no monthly refill.<br>
                        @endif
                        Reviews stick around {{ $freeRetention }}&nbsp;days.
                    </p>
                    <ul class="mt-6 space-y-2 text-[14px] text-zinc-600">
                        <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Full capture quality</li>
                        <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> No account</li>
                        <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Agent-driven checkup loop</li>
                    </ul>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <x-try-token-button fathom-event="Try token pricing free" />
                        <flux:modal.trigger name="credit-cost-breakdown">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                class="btn-quiet !rounded-full"
                                onclick="if(window.fathom)fathom.trackEvent('Pricing credit costs')"
                            >
                                Credit costs
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </article>

                @if ($pricingEnabled)
                    <article class="relative overflow-hidden rounded-2xl bg-card p-7 sm:p-8">
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-muted-foreground">Plus</p>
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
                                    class="btn-quiet !rounded-full"
                                    onclick="if(window.fathom)fathom.trackEvent('Pricing plus cta')"
                                >
                                    Upgrade via your agent
                                </flux:button>
                                <p class="mt-3 max-w-xs text-[13px] leading-relaxed text-zinc-500">
                                    After you try it, ask your agent for
                                    <code class="bg-zinc-100 px-1 py-0.5 text-[12px] text-zinc-800">create_checkout</code>
                                    and open the checkout link.
                                </p>
                            </div>
                        </div>
                    </article>

                    @if ($pack)
                        {{-- An add-on, not a plan: always a slim row under Try and Plus, which stay two columns. --}}
                        <article class="rounded-2xl bg-card p-7 sm:p-8 min-[30rem]:col-span-2 min-[30rem]:py-6">
                            <div class="min-[30rem]:flex min-[30rem]:items-center min-[30rem]:gap-8">
                                <div class="shrink-0">
                                    <p class="text-sm font-medium text-muted-foreground">Credit pack</p>
                                    <p class="mt-1 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">
                                        ${{ (int) $pack['price_usd'] }}<span class="text-lg font-medium text-zinc-500"> once</span>
                                    </p>
                                </div>
                                <div class="mt-3 min-[30rem]:mt-0">
                                    <p class="text-[15px] leading-relaxed text-pretty text-zinc-600">
                                        {{ (int) $pack['credits'] }} extra credits that never expire. Works on Try or Plus, used after your monthly credits. No subscription.
                                    </p>
                                    <p class="mt-2 text-[13px] leading-relaxed text-zinc-500">
                                        Ask your agent for <span class="font-mono text-[12px] text-zinc-900">create_checkout</span> with a credit pack.
                                    </p>
                                </div>
                            </div>
                        </article>
                    @endif
                @endif
            </div>
        </div>

        <flux:modal name="credit-cost-breakdown" class="max-w-md">
            <div class="space-y-1">
                <flux:heading size="lg">Credit costs</flux:heading>
                <flux:text class="text-[14px] !text-zinc-500">
                    @if ($pricingEnabled)
                        Try vs Plus. Burn is the same; only the pack size differs.
                    @else
                        Burn table for the free monthly pack.
                    @endif
                </flux:text>
            </div>
            <div class="mt-5">
                <x-billing.credit-costs :compare="$pricingEnabled" />
                <p class="mt-4 text-[14px] leading-relaxed text-zinc-500">
                    @if ($pricingEnabled)
                        Monthly credits reset each period (no rollover). Credit packs never expire and are spent last.
                    @else
                        Credits reset monthly (no rollover).
                    @endif
                </p>
            </div>
        </flux:modal>
    </x-home-section>

    {{-- FAQ --}}
    <x-home-section id="faq">
        @php
            $faq = \App\Support\HomeFaq::all();
        @endphp
        <div class="max-w-xl">
            <x-section-eyebrow number="05" label="FAQ" />
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Questions</h2>

            <div class="mt-6 space-y-2">
                @foreach ($faq as [$question, $answer])
                    <details class="group rounded-xl bg-card px-4 py-3">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-4 text-[15px] font-medium text-zinc-900 [&::-webkit-details-marker]:hidden">
                            <span>{{ $question }}</span>
                            <flux:icon.chevron-down variant="micro" class="mt-1 size-4 shrink-0 text-zinc-400 transition group-open:rotate-180" />
                        </summary>
                        <p class="mt-2 text-[15px] leading-relaxed text-zinc-600">{!! $answer !!}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </x-home-section>

    {{-- Closing: open source, with the repo marked up like a review, then the maker. --}}
    <x-home-section id="open-source">
        @php
            $stars = \App\Support\GitHubStars::count();
        @endphp
        <x-section-eyebrow number="06" label="Open source" />
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Built in the open</h2>
        <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
            Agents are getting fast at shipping what we see. ReviseMy keeps a person’s eye in that loop: your marks decide what ships. It’s open source so anyone can read how it works, run their own, and make it better.
        </p>

        <div class="rm-grid mt-6 flex flex-col items-start gap-5 rounded-2xl bg-card px-6 py-10 sm:flex-row sm:items-center sm:gap-6 sm:px-10 sm:py-12">
            {{-- The button, outlined as a mark: the M1 badge sits on the frame's corner. --}}
            <div class="relative shrink-0 border border-dashed border-key p-2">
                <span class="absolute -left-px -top-4 flex h-4 items-center bg-key px-1 text-[9px] font-semibold text-accent-contrast" aria-hidden="true">M1</span>
                <a
                    href="https://github.com/heyderekj/revisemy"
                    target="_blank"
                    rel="noreferrer"
                    onclick="if(window.fathom)fathom.trackEvent('GitHub star')"
                    class="group inline-flex h-9 items-stretch overflow-hidden rounded-full bg-zinc-900 text-sm font-medium text-white shadow-xs transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    <span class="inline-flex items-center gap-2 pl-3.5 pr-3">
                        <svg class="size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                        Star on GitHub
                    </span>
                    @if ($stars !== null)
                        <span class="inline-flex items-center gap-1 border-l border-white/20 pl-3 pr-3.5 tabular-nums dark:border-zinc-900/15">
                            <flux:icon.star variant="micro" class="size-3.5 text-key" />
                            {{ number_format($stars) }}
                            <span class="sr-only">{{ $stars === 1 ? 'star' : 'stars' }} so far</span>
                        </span>
                    @endif
                </a>
            </div>

            {{-- The note on the mark. --}}
            <p class="flex items-center gap-3 font-mono text-xs leading-relaxed text-muted-foreground">
                <span class="hidden h-px w-10 shrink-0 border-t border-dashed border-border-strong sm:block" aria-hidden="true"></span>
                <span>
                    <a href="https://osaasy.dev/" target="_blank" rel="noreferrer" class="text-zinc-700 underline decoration-border-strong underline-offset-2 transition-colors hover:text-zinc-900">O’Saasy License</a>.
                    Use it, fork it, run your own copy. Issues and PRs welcome.
                    Building on it? Read the <a href="/docs" class="text-zinc-700 underline decoration-border-strong underline-offset-2 transition-colors hover:text-zinc-900">developer docs</a>.
                </span>
            </p>
        </div>

        <p class="mt-6 max-w-xl text-[15px] leading-relaxed text-zinc-600">
            Made by <a href="https://heyderekj.com" target="_blank" rel="noreferrer" class="link">Derek</a>, who loves giving design feedback.
            Say hi at <a href="mailto:derekj@hey.com" class="link">derekj@hey.com</a>
            or <a href="https://x.com/heyderekj" target="_blank" rel="noreferrer" class="link">@heyderekj</a>.
        </p>
    </x-home-section>
</x-site-shell>
