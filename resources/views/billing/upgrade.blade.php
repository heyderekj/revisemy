@php
    $hasTransaction = filled(request()->query('_ptxn'));
@endphp

<x-layouts.app
    title="Upgrade to Plus — ReviseMy"
    description="Upgrade to ReviseMy Plus — $9/mo, 100 credits, same full capture quality."
    robots="noindex, nofollow"
    schema="page"
    canonical="{{ url('/upgrade') }}"
>
    @include('cashier::js')

    <x-page-frame checkout :footer="false">
        <x-home-section first class="!pb-0 sm:!pb-0">
            <x-billing.page-header />

            <x-billing.pro-split :price-usd="$priceUsd" :credits="$credits">
                <x-slot:actions>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        href="/"
                        class="!border !border-zinc-200 !bg-white hover:!border-zinc-300 hover:!bg-zinc-50"
                    >
                        Nevermind, stay on the free plan
                    </flux:button>
                </x-slot:actions>

                <x-slot:paymentIntro>
                    @if ($hasTransaction)
                        <p class="text-[15px] leading-relaxed text-zinc-600">
                            Complete payment securely below.
                        </p>
                    @else
                        <p class="text-[15px] leading-relaxed text-zinc-600">
                            Waiting for a Paddle payment link, or ask your agent to open
                            <code class="bg-zinc-100 px-1 py-0.5 text-[13px] text-zinc-800">create_checkout</code>.
                        </p>
                    @endif
                </x-slot:paymentIntro>

                <div class="paddle-checkout"></div>

                <x-slot:paymentFooter>
                    @unless ($hasTransaction)
                        <p class="text-center text-[13px] leading-relaxed text-zinc-500" id="rm-upgrade-idle">
                            No checkout loaded yet. Use the signed upgrade link from your agent to pay for a workspace.
                        </p>
                    @endunless
                </x-slot:paymentFooter>
            </x-billing.pro-split>
        </x-home-section>
    </x-page-frame>

    @if ($hasTransaction)
        <script>
            window.addEventListener('load', () => {
                document.getElementById('rm-upgrade-idle')?.remove();
            });
        </script>
    @endif
</x-layouts.app>
