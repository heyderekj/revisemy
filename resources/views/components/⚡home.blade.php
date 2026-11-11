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

<x-site-shell cta-href="#setup" fathom="Try token" sticky-event="Try token sidebar" :show-reviews-button="false">
    {{-- Agents the headline cycles through. Values are x-host-icon names. --}}
    @php
        $heroAgents = ['Claude' => 'claude', 'ChatGPT' => 'chatgpt', 'Copilot' => 'copilot', 'Cursor' => 'cursor', 'Grok' => 'grok'];
    @endphp

    {{-- Hero — bottom padding tightened so it reads with How it works below --}}
    <x-home-section first data-rm-desktop-hero class="@container/hero rm-fade-up !pb-8 sm:!pb-10">
        <div class="grid gap-8 @min-[44rem]/hero:grid-cols-[minmax(0,1.08fr)_minmax(0,1fr)] @min-[44rem]/hero:items-center">
            <div class="min-w-0">
                <h1 class="max-w-xl text-[clamp(2.25rem,5.5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-zinc-900 max-[359px]:text-[2rem] @min-[44rem]/hero:text-[min(5.5vw,6.4cqw,3.75rem)]">
                    Visual feedback<br aria-hidden="true">
                    with <span class="sr-only">your agent</span><span class="rm-agent-cycle" aria-hidden="true">
                        @foreach ($heroAgents as $label => $icon)
                            <span class="rm-agent-cycle-item gap-[0.18em]" style="--i: {{ $loop->index }}">
                                <x-host-icon :name="$icon" class="size-[1cap]!" />
                                {{ $label }}
                            </span>
                        @endforeach
                    </span>
                </h1>

                <p class="rm-fade-up-delay mt-5 w-full max-w-sm text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Mark up a page, screenshot, PDF, or email and approve your agent’s fixes when they’re ready.
                </p>

                {{-- Start a review here instead, and hand its link to an agent later.
                     /new takes a link, screenshots, a PDF or an email. --}}
                <div class="rm-fade-up-delay mt-6 flex w-full max-w-xl flex-col items-start gap-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('reviews.new') }}" class="btn-lit inline-flex h-11 items-center justify-center gap-2 rounded-full px-6 text-sm font-medium">
                            Start a review
                            <flux:icon.arrow-right variant="micro" class="size-4" />
                        </a>
                        <x-try-token-cta
                            href="#setup"
                            id="rm-hero-cta"
                            data-rm-hero-cta
                            variant="filled"
                            fathom-event="Try token hero"
                            class="!h-11 !px-6 lg:!hidden"
                        />
                    </div>
                    <p class="px-1 text-xs text-muted-foreground">Paste a link or add a file. No agent needed.</p>
                </div>
            </div>
            <x-hero-preview-chat class="rm-fade-up-delay @min-[44rem]/hero:justify-self-end" />
        </div>

        <div class="rm-fade-up-delay-2 relative mt-8 sm:mt-10">
            <x-hero-loop-preview />
        </div>
    </x-home-section>

    <x-home-section id="features" joined class="!pb-8">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Features</h2>
        <div class="mt-5 grid grid-cols-2 gap-px overflow-clip border-y border-border md:grid-cols-4">
            <article class="flex min-w-0 flex-col items-start gap-2.5 p-3 shadow-[1px_0_var(--color-border),0_1px_var(--color-border)] sm:p-4">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-chip text-zinc-600">
                    <flux:icon.photo variant="micro" class="size-[18px]" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold tracking-tight text-zinc-900">Capture anything visual</h3>
                    <p class="mt-1.5 text-[13px] leading-5 text-zinc-500">Capture responsive websites or add screenshots, PDFs, or email HTML.</p>
                </div>
            </article>

            <article class="flex min-w-0 flex-col items-start gap-2.5 p-3 shadow-[1px_0_var(--color-border),0_1px_var(--color-border)] sm:p-4">
                <x-mark-type-icon type="m" class="shrink-0" />
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold tracking-tight text-zinc-900">Precise marks</h3>
                    <p class="mt-1.5 text-[13px] leading-5 text-zinc-500">Mark a spot, area, or element and share its exact styles.</p>
                    <a href="/precise-marks" class="mt-2 inline-block text-[13px] leading-5 link" aria-label="Learn more about precise marks">Learn more</a>
                </div>
            </article>

            <article class="flex min-w-0 flex-col items-start gap-2.5 p-3 shadow-[1px_0_var(--color-border),0_1px_var(--color-border)] sm:p-4">
                <x-mark-type-icon type="s" class="shrink-0" />
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold tracking-tight text-zinc-900">Second opinion</h3>
                    <p class="mt-1.5 text-[13px] leading-5 text-zinc-500">Get capture checklists and optional AI suggestions.</p>
                    <a
                        href="/second-opinion"
                        class="mt-2 inline-block text-[13px] leading-5 link"
                    >Learn more</a>
                </div>
            </article>

            <article class="flex min-w-0 flex-col items-start gap-2.5 p-3 shadow-[1px_0_var(--color-border),0_1px_var(--color-border)] sm:p-4">
                <x-mark-type-icon type="g" class="shrink-0" />
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold tracking-tight text-zinc-900">Guest links</h3>
                    <p class="mt-1.5 text-[13px] leading-5 text-zinc-500">Invite guests to review or reply to a mark without an account.</p>
                    <a
                        href="/guest-links"
                        class="mt-2 inline-block text-[13px] leading-5 link"
                    >Learn more</a>
                </div>
            </article>

            <article class="flex min-w-0 flex-col items-start gap-2.5 p-3 shadow-[1px_0_var(--color-border),0_1px_var(--color-border)] sm:p-4">
                <x-use-case-icon name="queue-list" />
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold tracking-tight text-zinc-900">Board to done</h3>
                    <p class="mt-1.5 text-[13px] leading-5 text-zinc-500">Track each mark from open to verified, with before and after views.</p>
                    <a
                        href="/board"
                        class="mt-2 inline-block text-[13px] leading-5 link"
                    >Learn more</a>
                </div>
            </article>

            <article class="flex min-w-0 flex-col items-start gap-2.5 p-3 shadow-[1px_0_var(--color-border),0_1px_var(--color-border)] sm:p-4">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-chip text-zinc-600">
                    <flux:icon.arrow-path variant="micro" class="size-[18px]" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold tracking-tight text-zinc-900">Approve and loop</h3>
                    <p class="mt-1.5 text-[13px] leading-5 text-zinc-500">Approve or ask for another pass. Revisit it in <a href="/reviews" class="link">Your reviews</a>.</p>
                </div>
            </article>

            @foreach ([
                ['window', 'Live page review', 'Inspect and mark elements in a live view of the captured page.', '/live-page-review'],
                ['document-arrow-down', 'PDF markup and export', 'Mark up PDF text and download a copy with your notes.', '/pdf-markup'],
                ['chat-bubble-left-right', 'Replies and reactions', 'Reply or react in the same thread as the mark and its fixes.', null],
                ['chat-bubble-oval-left', 'Reviews in chat', 'Mark up and approve in Claude or other supported chats.', null],
                ['rectangle-stack', 'Capture UI states', 'Review menus, dialogs, and signed-in screens.', null],
                ['command-line', 'API and webhooks', 'Create reviews in CI and get notified when a reviewer decides.', null],
            ] as [$icon, $title, $description, $guide])
                <article class="flex min-w-0 flex-col items-start gap-2.5 p-3 shadow-[1px_0_var(--color-border),0_1px_var(--color-border)] sm:p-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-chip text-zinc-600">
                        <flux:icon :name="$icon" variant="micro" class="size-[18px]" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold tracking-tight text-zinc-900">{{ $title }}</h3>
                        <p class="mt-1.5 text-[13px] leading-5 text-zinc-500">{{ $description }}</p>
                        @if ($guide)
                            <a href="{{ $guide }}" class="mt-2 inline-block text-[13px] leading-5 link" aria-label="Learn more about {{ $title }}">Learn more</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </x-home-section>

    <x-home-section joined class="!pb-8" aria-labelledby="rm-built-for">
        <x-home-carousel track-id="rm-home-use-cases" label="Use cases" item-name="use case">
            <x-slot:heading>
                <h2 id="rm-built-for" class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Built for</h2>
            </x-slot:heading>

            @foreach (config('use-cases.pages', []) + config('use-cases.audiences', []) as $slug => $page)
                <a href="{{ url('/for/'.$slug) }}" class="group flex w-[clamp(9rem,25%,12rem)] shrink-0 snap-start flex-col items-start gap-4 rounded-2xl bg-card p-4 text-sm font-medium text-zinc-900 transition-colors hover:bg-chip-hover focus-visible:outline-offset-[-2px]">
                    <x-use-case-icon :name="$page['icon']" />
                    <span class="flex w-full items-center justify-between gap-2">
                        {{ $page['label'] }}
                        <flux:icon.arrow-right variant="micro" class="size-3.5 shrink-0 text-zinc-500 group-hover:text-zinc-900" />
                    </span>
                </a>
            @endforeach
        </x-home-carousel>
    </x-home-section>

    <x-home-section id="how" joined class="!pb-8">
        <x-section-eyebrow number="01" label="How it works" />
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">One feedback loop for anything visual</h2>
        <x-home-carousel track-id="rm-home-workflow" label="Review workflow" item-name="step" class="@container/workflow mt-4">
            <x-slot:heading>
                <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                    Capture, marks, fixes and approval in one place, so nothing gets lost between passes.
                </p>
            </x-slot:heading>

            @foreach ([
                ['Start a review', 'Paste a link or add a file, or ask your agent to open a review.', 'link', 'fieldnote.coffee', 'bg-raised text-zinc-700'],
                ['Mark what should change', 'Point to the exact spot and leave a note your agent can act on.', null, 'Lead with the subscription', 'bg-key/15 text-zinc-900'],
                ['Check the next pass', 'Review the fixes, then approve or ask for another pass.', 'check', 'Fix ready to verify', 'bg-done-soft text-done-ink'],
            ] as [$title, $description, $icon, $example, $tone])
                <article class="w-[calc(100%-2.5rem)] max-w-72 shrink-0 snap-start rounded-2xl bg-card p-4 sm:w-[calc((100%-5.5rem)/2)] @min-[42rem]/workflow:w-[calc((100%-1.5rem)/3)] @min-[42rem]/workflow:max-w-none">
                    <div class="rm-dot-grid mb-3 flex min-h-16 items-center justify-center rounded-xl bg-well px-2 py-3">
                        <span class="inline-flex min-w-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium {{ $tone }}">
                            @if ($icon)
                                <flux:icon :name="$icon" variant="micro" class="size-3.5 shrink-0" />
                            @else
                                <span class="rounded bg-key px-1 text-[10px] font-semibold text-accent-contrast">M1</span>
                            @endif
                            {{ $example }}
                        </span>
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-900">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $description }}</p>
                </article>
            @endforeach
        </x-home-carousel>
    </x-home-section>

    {{-- How agents use it --}}
    <x-home-disclosure id="agents" number="02" label="For agents" heading="The technical handoff">
        <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
            <code class="font-mono text-[13px] text-zinc-900">create_review</code> takes screenshots, a URL, a PDF or email HTML.
            <code class="font-mono text-[13px] text-zinc-900">get_review</code> returns your marks as work and one <code class="font-mono text-[13px] text-zinc-900">next_action</code>: wait, fix, open the next pass, or stop.
            The full tool list is in <a href="/llms.txt" class="link">llms.txt</a>.
        </p>
    </x-home-disclosure>

    {{-- Setup: the one list of ways to connect (shared with /connect and /connectors). --}}
    <x-home-disclosure id="setup" number="03" label="Connect" heading="Connect your agent" data-rm-end-cta>
        <p class="max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600">
            Pick the one you use. Most connect by pasting one address and clicking Connect — no account, no token to copy.
        </p>

        <div class="mt-8">
            <livewire:connect-hub />
        </div>
    </x-home-disclosure>

    {{-- Credits / Pricing --}}
    @php
        $pricingEnabled = (bool) config('billing.pricing_enabled', false);
        $freeCredits = (int) config('billing.plans.free.credits', 40);
        $plusCredits = (int) config('billing.plans.pro.credits', 150);
        $freeRetention = (int) config('billing.plans.free.review_retention_days', 7);
        $plusRetention = (int) config('billing.plans.pro.review_retention_days', 90);
        $plusPrice = (int) config('billing.plans.pro.price_usd', 9);
        $freeRenews = (bool) config('billing.plans.free.renews', true);
        $pack = collect(config('billing.packs', []))->first();
    @endphp
    <x-home-disclosure
        id="pricing"
        number="04"
        :label="$pricingEnabled ? 'Pricing' : 'Credits'"
        :heading="$pricingEnabled ? 'Try it free. Keep it with Plus.' : 'Free for now'"
        x-data
        x-intersect.once.threshold.25="window.fathom && fathom.trackEvent('Pricing viewed')"
    >
        @if ($pricingEnabled)
            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                No account to start. Same capture quality on every plan.
                When you like it, your agent opens checkout with
                <span class="font-mono text-[13px] text-zinc-900">create_checkout</span>
                without leaving the chat. Heavy month? Top up with a credit pack.
            </p>
        @else
            <p class="max-w-2xl text-[15px] leading-relaxed text-zinc-600">
                No account. {{ $freeCredits }} credits a month at full capture quality. Paid plans are paused while we work out pricing.
            </p>
        @endif

        <div class="relative mt-6">

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
    </x-home-disclosure>

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

        <p class="mt-6 max-w-sm text-[15px] leading-relaxed text-pretty text-zinc-600">
            Made by <a href="https://heyderekj.com" target="_blank" rel="noreferrer" class="link">Derek</a>, who loves giving design feedback.
            Say hi at <a href="mailto:derekj@hey.com" class="link">derekj@hey.com</a>
            or <a href="https://x.com/heyderekj" target="_blank" rel="noreferrer" class="link">@heyderekj</a>.
        </p>
    </x-home-section>
</x-site-shell>
