@php
    $burn = config('billing.costs', []);
@endphp

<x-layouts.app
    title="Upgrade to Pro — ReviseMy"
    description="Upgrade to ReviseMy Pro — $9/mo, 100 credits, same full capture quality."
    robots="noindex, nofollow"
    schema="page"
>
    @include('cashier::js')

    <x-page-frame checkout>
        <x-home-section first class="!pb-0 sm:!pb-0">
            <header>
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo variant="wordmark" size="lg" />
                </a>
            </header>
        </x-home-section>

        <x-home-section joined class="!pb-10 sm:!pb-12">
            <div class="grid gap-10 lg:grid-cols-2 lg:gap-0 lg:divide-x lg:divide-zinc-200">
                <div class="lg:pr-10">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Billing</p>
                    <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                        Upgrade to Pro
                    </h1>
                    <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                        <span class="font-semibold text-zinc-900">${{ $priceUsd }}/mo</span>
                        · {{ $credits }} credits each month · same full capture quality as Free.
                    </p>

                    <dl class="mt-8 space-y-3 border-t border-zinc-200 pt-6 text-[14px]">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-zinc-600">Images / PDF</dt>
                            <dd class="font-medium tabular-nums text-zinc-900">{{ (int) ($burn['images'] ?? 1) }} credit</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-zinc-600">Email HTML</dt>
                            <dd class="font-medium tabular-nums text-zinc-900">{{ (int) ($burn['html'] ?? 3) }} credits</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-zinc-600">URL capture</dt>
                            <dd class="font-medium tabular-nums text-zinc-900">{{ (int) ($burn['capture_url'] ?? 5) }} credits</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 border-t border-zinc-200 pt-3">
                            <dt class="text-zinc-600">Review retention</dt>
                            <dd class="font-medium tabular-nums text-zinc-900">{{ (int) config('billing.plans.pro.review_retention_days', 90) }} days</dd>
                        </div>
                    </dl>

                    <p class="mt-8 text-[13px] leading-relaxed text-zinc-500">
                        Paddle is the merchant of record. After you pay, return to your agent and continue the checkup.
                    </p>

                    <a
                        href="{{ route('billing.cancel') }}"
                        class="mt-6 inline-flex text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                    >
                        Cancel
                    </a>
                </div>

                <div class="lg:pl-10">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Payment</p>
                    <p class="mt-2 text-[15px] leading-relaxed text-zinc-600">
                        Enter your email and pay securely below.
                    </p>

                    <div class="mt-6 min-h-[516px] border border-zinc-200 bg-surface/80 px-3 py-4 sm:px-4">
                        <div class="paddle-checkout"></div>
                    </div>

                    <button
                        type="button"
                        id="rm-retry-paddle"
                        class="mt-4 text-sm font-medium text-zinc-600 underline underline-offset-4 transition hover:text-zinc-900"
                        hidden
                    >
                        Retry payment form
                    </button>
                </div>
            </div>
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
