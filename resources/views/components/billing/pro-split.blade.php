{{-- Full-bleed Billing | Payment panel (how-it-works style rules + crosshairs).
     Default slot = payment embed (full column width). Named slots:
     `actions` (billing links), `paymentIntro` (padded copy above embed),
     `paymentFooter` (padded content under embed, e.g. retry). --}}
@props([
    'priceUsd',
    'credits',
])

@php
    $freeCredits = (int) config('billing.plans.free.credits', 30);
    $freeRetention = (int) config('billing.plans.free.review_retention_days', 7);
    $plusRetention = (int) config('billing.plans.pro.review_retention_days', 90);
@endphp

<div {{ $attributes->class('rm-bleed relative mt-8 border-y border-zinc-200 sm:mt-10') }}>
    {{-- Outer border-y intersections --}}
    <x-cross-mark left="0" top="0" />
    <x-cross-mark left="100%" top="0" />
    <x-cross-mark left="50%" top="0" visibility="hidden lg:block" />
    <x-cross-mark left="0" top="100%" />
    <x-cross-mark left="100%" top="100%" />
    <x-cross-mark left="50%" top="100%" visibility="hidden lg:block" />

    <div class="grid grid-cols-1 lg:grid-cols-2 lg:gap-px lg:bg-[var(--color-border)]">
        <article class="relative overflow-hidden bg-[var(--color-canvas)] py-8 sm:py-10 lg:sticky lg:top-8 lg:self-start lg:py-12">
            <div class="rm-plus-grid" aria-hidden="true"></div>
            <div class="rm-pad relative z-10">
                <x-section-eyebrow label="Upgrade" />
                <h1 class="text-[clamp(1.75rem,4vw,2.5rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                    The {{ config('billing.plans.pro.name', 'Plus') }} Plan
                </h1>
                <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    <span class="font-semibold text-zinc-900">${{ $priceUsd }}/mo</span>
                    for {{ $credits }} credits each month
                    <span class="text-zinc-400">(Free is {{ $freeCredits }})</span>.
                    Same full capture quality — you just get more room for reviews.
                </p>
                <p class="mt-3 text-[14px] leading-relaxed text-pretty text-zinc-500">
                    Credits reset monthly (no rollover). Reviews stick around
                    {{ $plusRetention }} days instead of {{ $freeRetention }}.
                    Credit costs per review source are listed below.
                </p>

                <x-billing.credit-costs :show-label="false" class="mt-8" />

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <flux:button
                        variant="primary"
                        size="sm"
                        href="#rm-payment"
                        class="lg:hidden"
                    >
                        Pay Here
                    </flux:button>
                    @isset($actions)
                        {{ $actions }}
                    @endisset
                </div>
            </div>
        </article>

        {{-- Mobile stack: real mid rule so crosshairs sit on the hairline --}}
        <div
            class="relative col-span-full h-px bg-[var(--color-border)] lg:hidden"
            aria-hidden="true"
        >
            <x-cross-mark left="0" top="50%" />
            <x-cross-mark left="100%" top="50%" />
        </div>

        <article id="rm-payment" class="scroll-mt-8 bg-[var(--color-canvas)] pt-8 sm:pt-10 lg:pt-12 pb-8 sm:pb-10 lg:pb-12">
            <div class="rm-pad">
                <x-section-eyebrow label="Payment" />
                @isset($paymentIntro)
                    {{ $paymentIntro }}
                @endisset
                <p class="mt-3 max-w-[300px] text-[13px] leading-relaxed text-zinc-500">
                    Paddle is the merchant of record. After you pay, return to your agent and continue the checkup.
                </p>
                <p class="mt-4 border border-amber-200/80 bg-amber-50/70 px-3 py-2.5 text-[13px] leading-relaxed text-amber-950/85">
                    <span class="font-medium text-amber-950">Tax at checkout.</span>
                    ${{ $priceUsd }}/mo is before tax — Paddle adds sales tax or VAT based on your location.
                </p>
            </div>

            <div class="rm-pad mt-6">
                <div class="min-h-[516px] bg-white p-4 ring-1 ring-zinc-200 sm:p-6">
                    {{ $slot }}
                </div>
            </div>

            @isset($paymentFooter)
                <div class="rm-pad mt-4">
                    {{ $paymentFooter }}
                </div>
            @endisset
        </article>
    </div>
</div>
