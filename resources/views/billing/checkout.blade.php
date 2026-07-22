<x-layouts.app
    title="Upgrade to Plus — ReviseMy"
    description="Upgrade to ReviseMy Plus — $9/mo, 100 credits, same full capture quality."
    robots="noindex, nofollow"
    schema="page"
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
                        href="{{ route('billing.cancel') }}"
                        class="!border !border-zinc-200 !bg-white hover:!border-zinc-300 hover:!bg-zinc-50"
                    >
                        Nevermind, stay free
                    </flux:button>
                </x-slot:actions>

                <x-slot:paymentIntro>
                    <p class="text-[15px] leading-relaxed text-zinc-600">
                        Enter your email and pay securely below.
                    </p>
                </x-slot:paymentIntro>

                <div class="paddle-checkout"></div>

                <x-slot:paymentFooter>
                    <button
                        type="button"
                        id="rm-retry-paddle"
                        class="text-sm font-medium text-zinc-600 underline underline-offset-4 transition hover:text-zinc-900"
                        hidden
                    >
                        Retry payment form
                    </button>
                </x-slot:paymentFooter>
            </x-billing.pro-split>
        </x-home-section>
    </x-page-frame>

    <script>
        const options = @json($options);

        function openCheckout() {
            if (window.Paddle && typeof Paddle.Checkout?.open === 'function') {
                Paddle.Checkout.open(options);
                return true;
            }
            return false;
        }

        function tryOpen(attemptsLeft) {
            if (openCheckout()) {
                return;
            }
            if (attemptsLeft > 0) {
                setTimeout(() => tryOpen(attemptsLeft - 1), 250);
                return;
            }
            const retry = document.getElementById('rm-retry-paddle');
            if (retry) {
                retry.hidden = false;
            }
        }

        document.getElementById('rm-retry-paddle')?.addEventListener('click', () => {
            document.getElementById('rm-retry-paddle').hidden = true;
            tryOpen(8);
        });

        window.addEventListener('load', () => setTimeout(() => tryOpen(12), 200));
    </script>
</x-layouts.app>
