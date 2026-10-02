<x-layouts.app
    title="Checkout isn’t available — ReviseMy"
    description="Checkout couldn’t be opened — nothing was charged."
    robots="noindex, nofollow"
    schema="page"
>
    <x-page-frame>
        <x-home-section first>
            <header>
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo variant="wordmark" size="lg" />
                </a>
            </header>

            <article class="mt-10 sm:mt-12">
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Billing</p>
                <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                    Checkout isn’t available right now
                </h1>
                <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Nothing was charged. Try the link again in a minute, or ask your agent for a fresh
                    <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">create_checkout</code>
                    link. If it keeps happening, the person who runs this ReviseMy server needs to look at its billing setup.
                </p>
                <a
                    href="/"
                    class="mt-10 inline-flex text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                >
                    Back to homepage
                </a>
            </article>
        </x-home-section>
    </x-page-frame>
</x-layouts.app>
