<x-layouts.app
    title="Pricing — ReviseMy"
    description="ReviseMy pricing: Try free with {{ $tryCredits }} credits a month, Plus at ${{ $priceUsd }}/mo for {{ $credits }} credits, or a one-time credit pack."
    schema="page"
    canonical="{{ url('/upgrade') }}"
>
    <x-page-frame :footer="false">
        <x-home-section first>
            <x-billing.page-header />

            <article class="mt-10 sm:mt-12">
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Pricing</p>
                <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                    Pay for checkups, not seats
                </h1>
                <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Every plan gets the same full capture quality. Checkout happens from your agent:
                    ask it to call
                    <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">create_checkout</code>
                    and open the link it shares. Payments are handled by Polar, our merchant of record.
                </p>
            </article>
        </x-home-section>

        <div class="relative border-t border-zinc-200">
            <x-cross-mark left="0" top="0" />
            <x-cross-mark left="100%" top="0" />
            <div class="grid grid-cols-1 gap-px bg-[var(--color-border)] min-[30rem]:grid-cols-2 lg:grid-cols-3">
                <section class="bg-[var(--color-canvas)] p-7 sm:p-8">
                    <h2 class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Try</h2>
                    <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">$0</p>
                    <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
                        {{ $tryCredits }} credits each month — no rollover. No account needed.
                    </p>
                </section>

                <section class="bg-[var(--color-canvas)] p-7 sm:p-8">
                    <h2 class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Plus</h2>
                    <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">
                        ${{ $priceUsd }}<span class="text-lg font-medium text-zinc-500">/mo</span>
                    </p>
                    <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
                        {{ $credits }} credits each billing month, longer review retention. Cancel anytime.
                    </p>
                    <p class="mt-4 text-[13px] text-zinc-500">
                        Agent: <code class="bg-zinc-100 px-1 py-0.5 text-[12px] text-zinc-800">create_checkout</code>
                    </p>
                </section>

                @foreach ($packs as $pack)
                    <section class="bg-[var(--color-canvas)] p-7 sm:p-8 min-[30rem]:col-span-2 lg:col-span-1">
                        <h2 class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Credit pack</h2>
                        <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">
                            ${{ $pack['price_usd'] }}<span class="text-lg font-medium text-zinc-500"> once</span>
                        </p>
                        <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
                            {{ $pack['credits'] }} credits that never expire. Works on Try or Plus, spent after monthly credits.
                        </p>
                        <p class="mt-4 text-[13px] text-zinc-500">
                            Agent: <code class="bg-zinc-100 px-1 py-0.5 text-[12px] text-zinc-800">create_checkout product:"{{ $pack['product'] }}"</code>
                        </p>
                    </section>
                @endforeach
            </div>
        </div>

        <div class="relative border-t border-zinc-200 px-[var(--rm-pad)] py-12">
            <x-cross-mark left="0" top="0" />
            <x-cross-mark left="100%" top="0" />
            <x-billing.credit-costs compare class="max-w-md" />
        </div>
    </x-page-frame>
</x-layouts.app>
