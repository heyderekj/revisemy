<x-layouts.app
    title="Manage billing — ReviseMy"
    description="Manage ReviseMy billing."
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
                    Billing
                </h1>
                <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Plan: <span class="font-semibold text-zinc-900">{{ $status['plan_name'] }}</span>
                    · Credits: {{ $status['credits_monthly'] }} / {{ $status['credits_grant'] }} this period
                    @if ($status['credits_purchased'] > 0)
                        + {{ $status['credits_purchased'] }} purchased
                    @endif
                </p>
                @if ($subscribed && $status['cancel_at_period_end'] && $status['credits_period_ends_at'])
                    <p class="mt-2 text-sm text-zinc-500">
                        Plus is canceled and ends {{ \Illuminate\Support\Carbon::parse($status['credits_period_ends_at'])->toFormattedDateString() }}.
                    </p>
                @endif

                @if (session('status'))
                    <p class="mt-4 text-sm text-emerald-700">{{ session('status') }}</p>
                @endif
                @if (session('error'))
                    <p class="mt-4 text-sm text-rose-700">{{ session('error') }}</p>
                @endif

                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($status['portal_available'])
                        <form method="post" action="{{ URL::temporarySignedRoute('billing.portal', now()->addHours(6), ['workspace' => $workspace->public_id]) }}">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-700"
                            >
                                Receipts &amp; payment method
                            </button>
                        </form>
                    @endif
                    @foreach ($packUrls as $key => $url)
                        <a
                            href="{{ $url }}"
                            class="inline-flex border border-zinc-300 bg-canvas px-4 py-2.5 text-sm font-medium text-zinc-800 transition hover:bg-surface-hover"
                        >
                            Buy {{ config("billing.packs.{$key}.credits") }} credits — ${{ config("billing.packs.{$key}.price_usd") }}
                        </a>
                    @endforeach
                </div>

                @if ($subscribed && ! $status['cancel_at_period_end'])
                    <p class="mt-8 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                        Receipts and payment method live in Polar (merchant of record).
                        Cancel below to stop renewal — you keep Plus until the current period ends.
                    </p>
                    <form
                        method="post"
                        action="{{ URL::temporarySignedRoute('billing.cancel-subscription', now()->addHours(6), ['workspace' => $workspace->public_id]) }}"
                        class="mt-4"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex border border-zinc-300 bg-canvas px-4 py-2.5 text-sm font-medium text-zinc-800 transition hover:bg-surface-hover"
                        >
                            Cancel Plus
                        </button>
                    </form>
                @elseif (! $subscribed)
                    <p class="mt-4 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                        You’re on Try. Ask your agent to call
                        <code class="bg-zinc-100 px-1.5 py-0.5 text-sm text-zinc-800">create_checkout</code>
                        for Plus ({{ $status['pro_credits'] }} credits/mo for ${{ $status['pro_price_usd'] }}) when you’re ready to keep going.
                    </p>
                @endif

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
