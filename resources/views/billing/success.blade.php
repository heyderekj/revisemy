<x-layouts.app
    title="Payment received — ReviseMy"
    description="Your ReviseMy purchase went through."
    robots="noindex, nofollow"
    schema="page"
>
    <x-page-frame :footer="false">
        <x-home-section first>
            <header>
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo variant="wordmark" size="lg" />
                </a>
            </header>

            <article class="mt-10 sm:mt-12">
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Billing</p>
                @if ($kind === 'already_plus')
                    <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                        You’re already on Plus
                    </h1>
                    <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                        Nothing to pay. Need more credits this month? Ask your agent for a credit pack —
                        <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">create_checkout</code>
                        with <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">product: "credits_50"</code>.
                    </p>
                @else
                    <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                        Payment received
                    </h1>
                    <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                        Thanks — your receipt comes from Polar, our merchant of record.
                        Credits land on your workspace within a few seconds.
                    </p>
                @endif
                <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Return to your agent and continue — call
                    <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">get_billing</code>
                    to confirm credits, then
                    <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">create_review</code>
                    again. Manage or cancel any time with
                    <code class="bg-zinc-100 px-1 py-0.5 text-[13px] text-zinc-800">create_portal</code>.
                </p>
            </article>
        </x-home-section>

        <div class="relative border-t border-zinc-200 px-[var(--rm-pad)] py-12">
            <x-cross-mark left="0" top="0" />
            <x-cross-mark left="100%" top="0" />
            <x-billing.credit-costs compare class="max-w-md" />
        </div>
    </x-page-frame>
</x-layouts.app>
