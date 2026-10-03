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

    <div
    class="relative min-h-screen"
    x-data="{
        mobileNav: false,
        pastHero: false,
        atSetup: false,
        get showHeaderTry() { return this.pastHero && ! this.atSetup },
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
    "
>
    {{-- The page column, inset from the viewport. --}}
    <div class="relative z-10 mx-auto max-w-[1200px] px-4 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pt-16">
        <div class="relative flex min-h-screen gap-6">

        {{-- Sidebar --}}
        <aside class="hidden w-[220px] shrink-0 flex-col rounded-2xl bg-card px-6 pb-8 pt-8 lg:flex lg:sticky lg:top-4 lg:h-[calc(100vh-2rem)] lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto">
            <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                <x-revisemy-logo variant="wordmark" size="lg" />
            </a>

            <x-home-nav class="mt-12 flex-1" />

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
                    {{-- Holds the room the sticky Connect button takes on desktop. --}}
                    <div class="hidden h-8 w-48 shrink-0 lg:block" aria-hidden="true"></div>
                </div>

                <p class="rm-fade-up-delay mt-5 w-full max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Your agent captures the work — a screenshot, a page, a PDF or an email — and sends you a review. You mark what matters and approve or ask for changes. It reads your marks and keeps going.
                </p>

                <div class="rm-fade-up-delay-2 relative mt-10 sm:mt-12">
                    <x-hero-loop-preview />
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
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Screenshots, a live page on desktop and phone, PDF slides or email HTML. Each kind gets its own checklist.</p>
                        </article>

                        <article class="rounded-2xl bg-card p-7 lg:row-start-1">
                            <x-mark-type-icon type="s" />
                            <h3 class="mt-3 text-sm font-semibold text-zinc-900">Second opinion</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">A checklist on every capture, and vision hints when a key is set. Suggestions, never decisions.</p>
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
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">Approve or ask for changes. Your agent reads what’s next and keeps going. <a href="/reviews" class="link">Recent reviews</a> lists every one.</p>
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
            </div>

            {{-- Setup: the one list of ways to connect (shared with /connect and /connectors). --}}
            <x-home-section id="setup">
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
            <x-home-section id="pricing">
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
                        'min-[30rem]:grid-cols-2 lg:grid-cols-3' => $pricingEnabled,
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
                                <article class="rounded-2xl bg-card p-7 sm:p-8 min-[30rem]:col-span-2 lg:col-span-1">
                                    <p class="text-sm font-medium text-muted-foreground">Credit pack</p>
                                    <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">
                                        ${{ (int) $pack['price_usd'] }}<span class="text-lg font-medium text-zinc-500"> once</span>
                                    </p>
                                    <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
                                        {{ (int) $pack['credits'] }} extra credits.<br>
                                        They never expire.
                                    </p>
                                    <ul class="mt-6 space-y-2 text-[14px] text-zinc-600">
                                        <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Works on Try or Plus</li>
                                        <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> Used after your monthly credits</li>
                                        <li class="flex gap-2"><span class="text-zinc-400" aria-hidden="true">—</span> No subscription</li>
                                    </ul>
                                    <p class="mt-8 max-w-xs text-[13px] leading-relaxed text-zinc-500">
                                        Ask your agent for <span class="font-mono text-[12px] text-zinc-900">create_checkout</span> with a credit pack.
                                    </p>
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
                    $credits = (int) config('billing.plans.free.credits', 20);
                    $faq = [
                        ['Do I need to sign up?', 'No. Pick your assistant under <a href="#setup" class="link">Connect</a>. Most connect by pasting one address and clicking Connect, which makes a try workspace that’s yours.'.(config('billing.pricing_enabled') ? '' : " {$credits} credits a month, free for now.")],
                        ['Where does the review open?', 'Always at a review link you can open anywhere. In Claude and VS Code it can also open right in the chat, so you mark and decide without leaving it.'],
                        ['My marks, second opinion, guests — who’s in charge?', 'You. Your marks are the brief the agent works from. Second opinion and guest notes stay suggestions until you accept them.'],
                        ['What happens when I run out of credits?', config('billing.pricing_enabled')
                            ? 'New checkups pause until your monthly credits refill, or top up right away with a credit pack (never expires) or Plus. Monthly credits don’t roll over. Your agent can check with get_billing.'
                            : "New checkups pause until your {$credits} credits refill next month. Screenshots and PDFs cost 1, email HTML 3, a live URL 5. Your agent can check the date with get_billing."],
                        ['What’s a pass, and the board?', 'The board is your checklist: each mark goes open, resolved, verified. When you ask for changes, your agent sends fresh captures as the next pass, and you check what it fixed. See <a href="/board" class="link">the board</a>.'],
                        ['Can someone else look too?', 'Yes. Share a guest link from the review: they can suggest, and you decide what becomes a mark. See <a href="/guest-links" class="link">guest links</a>.'],
                    ];
                    if (config('billing.pricing_enabled')) {
                        $faq[] = ['How do I upgrade or cancel?', 'Ask your agent: create_checkout opens a Polar checkout for Plus or a one-time credit pack, create_portal handles cards and receipts, and cancel_subscription ends Plus at the close of the period.'];
                    }
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
                            class="link"
                        >Derek</a>
                        — I love giving design feedback. Not to be a dick, but to be a Derek. Agents are getting fast at shipping UI; what’s still missing is a clear place for a human to mark what matters and send the next pass back without leaving the chat.
                    </p>
                    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                        ReviseMy started as a
                        <a
                            href="https://heyderekj.com/projects/revisemy/"
                            target="_blank"
                            rel="noreferrer"
                            class="link"
                        >side project</a>
                        in 2024 — an idea and a Figma file — then
                        <a
                            href="https://x.com/taylorotwell/status/2075667366646858222"
                            target="_blank"
                            rel="noreferrer"
                            class="link"
                        >Taylor’s Laravel Cloud weekend challenge</a>
                        was the nudge to ship it as an MCP on Laravel. Built in a weekend on the side; it works, it passes tests, and there’s plenty left to improve.
                        <a
                            href="https://github.com/sponsors/heyderekj"
                            target="_blank"
                            rel="noreferrer"
                            class="ml-1.5 inline-flex translate-y-[-1px] items-center gap-1 rounded-full bg-chip px-2.5 py-0.5 text-[12px] font-medium text-zinc-700 transition-colors hover:bg-chip-hover"
                        >
                            <svg class="size-3 text-problem" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M8 14.25c-.2 0-.4-.06-.57-.18C5.6 12.7 2 9.72 2 6.4 2 4.3 3.6 2.75 5.7 2.75c1.1 0 2.1.5 2.8 1.35A3.8 3.8 0 0 1 11.3 2.75C13.4 2.75 15 4.3 15 6.4c0 3.32-3.6 6.3-5.43 7.67A.9.9 0 0 1 8 14.25Z"/>
                            </svg>
                            Sponsor on GitHub
                        </a>
                    </p>
                    <p class="mt-6 text-[15px] leading-relaxed text-zinc-600">
                        Say hi anytime —
                        <a
                            href="mailto:derekj@hey.com"
                            class="link"
                        >derekj@hey.com</a>
                        or
                        <a
                            href="https://x.com/heyderekj"
                            target="_blank"
                            rel="noreferrer"
                            class="link"
                        >@heyderekj on X.com</a>.
                    </p>

                    <div class="mt-8">
                        <p class="mb-2 text-sm font-medium text-muted-foreground">Also by Derek</p>
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
            class="fixed inset-0 z-50 bg-black/25 backdrop-blur-[2px]"
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
            class="fixed inset-y-0 right-0 z-50 flex w-[min(100%,20rem)] flex-col bg-card shadow-xl"
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
            <div class="flex items-center justify-between gap-3 px-5 py-3">
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

            <x-home-nav drawer class="flex-1 overflow-y-auto px-5 py-8" />

            <p class="px-5 py-5 font-mono text-[11px] text-zinc-400">v{{ config('revisemy.version') }}</p>
        </aside>
    </div>

</div>
