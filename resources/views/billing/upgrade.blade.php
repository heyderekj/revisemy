<x-layouts.marketing
    title="Pricing — ReviseMy"
    description="ReviseMy pricing: Try free with {{ $tryCredits }} credits a month, Plus at ${{ $priceUsd }}/mo for {{ $credits }} credits, or a one-time credit pack."
    fathom="Connect pricing"
    :wide="true"
>
    <x-marketing-hero eyebrow="Pricing" headline="Pay for checkups, not seats" subheadline="Every plan gets the same full capture quality. Checkout happens from your agent: ask it for create_checkout and open the link it shares. Payments are handled by Polar, our merchant of record." />

    <x-home-section>
        <div class="grid grid-cols-1 gap-3 min-[30rem]:grid-cols-2 lg:grid-cols-3">
            <section class="rounded-2xl bg-card p-7 sm:p-8">
                <h2 class="text-sm font-medium text-muted-foreground">Try</h2>
                <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">$0</p>
                <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">{{ $tryCredits }} credits each month, no rollover. No account needed.</p>
            </section>

            <section class="rounded-2xl bg-card p-7 sm:p-8">
                <h2 class="text-sm font-medium text-muted-foreground">Plus</h2>
                <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">${{ $priceUsd }}<span class="text-lg font-medium text-zinc-500">/mo</span></p>
                <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">{{ $credits }} credits each billing month and longer review retention. Cancel any time.</p>
                <p class="mt-4 text-sm text-muted-foreground">Ask your agent for <span class="font-mono text-[13px] text-zinc-900">create_checkout</span></p>
            </section>

            @foreach ($packs as $pack)
                <section class="rounded-2xl bg-card p-7 sm:p-8 min-[30rem]:col-span-2 lg:col-span-1">
                    <h2 class="text-sm font-medium text-muted-foreground">Credit pack</h2>
                    <p class="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-semibold tracking-tight text-zinc-900">${{ $pack['price_usd'] }}<span class="text-lg font-medium text-zinc-500"> once</span></p>
                    <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">{{ $pack['credits'] }} credits that never expire. Works on Try or Plus, spent after monthly credits.</p>
                    <p class="mt-4 text-sm text-muted-foreground">Ask your agent for <span class="font-mono text-[13px] text-zinc-900">create_checkout product:"{{ $pack['product'] }}"</span></p>
                </section>
            @endforeach
        </div>
    </x-home-section>

    <x-home-section>
        <x-billing.credit-costs compare class="max-w-md" />
    </x-home-section>
</x-layouts.marketing>
