@php
    $burn = config('billing.costs', []);
    $hasTransaction = filled(request()->query('_ptxn'));
@endphp

<x-layouts.app
    title="Upgrade to Pro — ReviseMy"
    description="Upgrade to ReviseMy Pro — $9/mo, 100 credits, same full capture quality."
    robots="noindex, nofollow"
    schema="page"
    canonical="{{ url('/upgrade') }}"
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
                        Paddle is the merchant of record. Workspace upgrades usually start from your agent’s
                        <code class="bg-zinc-100 px-1 py-0.5 text-[12px] text-zinc-800">create_checkout</code>
                        link — this page also opens payment links from Paddle emails.
                    </p>

                    <a
                        href="/"
                        class="mt-6 inline-flex text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                    >
                        Back to homepage
                    </a>
                </div>

                <div class="lg:pl-10">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Payment</p>
                    @if ($hasTransaction)
                        <p class="mt-2 text-[15px] leading-relaxed text-zinc-600">
                            Complete payment securely below.
                        </p>
                    @else
                        <p class="mt-2 text-[15px] leading-relaxed text-zinc-600">
                            Waiting for a Paddle payment link, or ask your agent to open
                            <code class="bg-zinc-100 px-1 py-0.5 text-[13px] text-zinc-800">create_checkout</code>.
                        </p>
                    @endif

                    <div class="mt-6 min-h-[516px] border border-zinc-200 bg-surface/80 px-3 py-4 sm:px-4">
                        <div class="paddle-checkout"></div>
                        @unless ($hasTransaction)
                            <p class="px-2 py-8 text-center text-[13px] leading-relaxed text-zinc-500" id="rm-upgrade-idle">
                                No checkout loaded yet. Use the signed upgrade link from your agent to pay for a workspace.
                            </p>
                        @endunless
                    </div>
                </div>
            </div>
        </x-home-section>
    </x-page-frame>

    @if ($hasTransaction)
        <script>
            // Paddle.js auto-opens inline checkout when ?_ptxn= is present.
            // Hide the idle hint if the frame mounts.
            window.addEventListener('load', () => {
                document.getElementById('rm-upgrade-idle')?.remove();
            });
        </script>
    @endif
</x-layouts.app>
