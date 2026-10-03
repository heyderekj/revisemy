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

    {{-- Closing: open source, the maker, and how to say hi --}}
    <x-home-section id="open-source">
        <div class="max-w-xl">
            <x-section-eyebrow number="06" label="Open source" />
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Open source, made by Derek</h2>
            <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                ReviseMy is open source under the
                <a href="https://osaasy.dev/" target="_blank" rel="noreferrer" class="link">O’Saasy License</a>.
                Read the code, run your own copy, file an issue or send a PR.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a
                    href="https://github.com/heyderekj/revisemy"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-full bg-chip px-3 py-1 text-sm font-medium text-zinc-700 transition-colors hover:bg-chip-hover"
                >
                    GitHub ↗
                </a>
                <a
                    href="https://github.com/sponsors/heyderekj"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-full bg-chip px-3 py-1 text-sm font-medium text-zinc-700 transition-colors hover:bg-chip-hover"
                >
                    <svg class="size-3.5 text-problem" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 14.25c-.2 0-.4-.06-.57-.18C5.6 12.7 2 9.72 2 6.4 2 4.3 3.6 2.75 5.7 2.75c1.1 0 2.1.5 2.8 1.35A3.8 3.8 0 0 1 11.3 2.75C13.4 2.75 15 4.3 15 6.4c0 3.32-3.6 6.3-5.43 7.67A.9.9 0 0 1 8 14.25Z"/>
                    </svg>
                    Sponsor
                </a>
            </div>
            <p class="mt-6 text-[15px] leading-relaxed text-zinc-600">
                I’m <a href="https://heyderekj.com" target="_blank" rel="noreferrer" class="link">Derek</a>
                — I love giving design feedback. Not to be a dick, but to be a Derek. ReviseMy started as a
                <a href="https://heyderekj.com/projects/revisemy/" target="_blank" rel="noreferrer" class="link">side project</a>
                in 2024, and
                <a href="https://x.com/taylorotwell/status/2075667366646858222" target="_blank" rel="noreferrer" class="link">Taylor’s Laravel Cloud weekend challenge</a>
                was the nudge to ship it.
            </p>
            <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
                Say hi anytime —
                <a href="mailto:derekj@hey.com" class="link">derekj@hey.com</a>
                or
                <a href="https://x.com/heyderekj" target="_blank" rel="noreferrer" class="link">@heyderekj on X.com</a>.
            </p>
            <p class="mt-6 text-sm text-muted-foreground">
                Also by Derek:
                <a href="https://harvous.com" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">Harvous ↗</a>
                <span aria-hidden="true">·</span>
                <a href="https://dinkyfiles.com" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">Dinky ↗</a>
                <span aria-hidden="true">·</span>
                <a href="https://binkyfiles.com" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">Binky ↗</a>
            </p>
        </div>
    </x-home-section>
</x-site-shell>
