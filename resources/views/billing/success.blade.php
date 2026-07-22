<x-layouts.app
    title="Plus unlocked — ReviseMy"
    description="Your ReviseMy workspace is on Plus."
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
                <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                    You’re on Plus
                </h1>
                <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Thanks{{ $email ? ' — receipt comes from Paddle to '.$email : '' }}.
                    Your workspace now has {{ (int) config('billing.plans.pro.credits', 100) }} credits for this month (full capture quality).
                </p>
                <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Return to your agent and continue — call
                    <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">create_review</code>
                    again, or
                    <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">get_billing</code>
                    to confirm credits.
                </p>
            </article>
        </x-home-section>

        <div class="relative border-t border-zinc-200 px-[var(--rm-pad)] py-12">
            <x-cross-mark left="0" top="0" />
            <x-cross-mark left="100%" top="0" />
            <x-billing.credit-costs compare tone="confirm" :show-label="false" class="max-w-md" />
        </div>
    </x-page-frame>
</x-layouts.app>
