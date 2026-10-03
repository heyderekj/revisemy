<x-simple-page title="Manage billing — ReviseMy" eyebrow="Billing" heading="Billing" robots="noindex, nofollow">
    <p>
        Plan: <span class="font-semibold text-zinc-900">{{ $status['plan_name'] }}</span>
        · Credits: {{ $status['credits_monthly'] }} / {{ $status['credits_grant'] }} this period
        @if ($status['credits_purchased'] > 0)
            + {{ $status['credits_purchased'] }} purchased
        @endif
    </p>
    @if ($subscribed && $status['cancel_at_period_end'] && $status['credits_period_ends_at'])
        <p class="mt-2 text-sm text-muted-foreground">Plus is canceled and ends {{ \Illuminate\Support\Carbon::parse($status['credits_period_ends_at'])->toFormattedDateString() }}.</p>
    @endif

    @if (session('status'))
        <p class="mt-4 text-sm text-done-ink">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 text-sm text-problem-ink" role="alert">{{ session('error') }}</p>
    @endif

    <div class="mt-8 flex flex-wrap gap-3">
        @if ($status['portal_available'])
            <form method="post" action="{{ URL::temporarySignedRoute('billing.portal', now()->addHours(6), ['workspace' => $workspace->public_id]) }}">
                @csrf
                <button type="submit" class="btn-lit inline-flex h-10 items-center rounded-full px-5 text-sm font-medium">Receipts &amp; payment method</button>
            </form>
        @endif
        @foreach ($packUrls as $key => $url)
            <a href="{{ $url }}" class="btn-quiet inline-flex h-10 items-center rounded-full px-5 text-sm font-medium">
                Buy {{ config("billing.packs.{$key}.credits") }} credits — ${{ config("billing.packs.{$key}.price_usd") }}
            </a>
        @endforeach
    </div>

    @if ($subscribed && ! $status['cancel_at_period_end'])
        <p class="mt-8">Receipts and payment method live in Polar, our merchant of record. Cancel to stop renewal; you keep Plus until the current period ends.</p>
        <form method="post" action="{{ URL::temporarySignedRoute('billing.cancel-subscription', now()->addHours(6), ['workspace' => $workspace->public_id]) }}" class="mt-4">
            @csrf
            <button type="submit" class="btn-quiet inline-flex h-10 items-center rounded-full px-5 text-sm font-medium">Cancel Plus</button>
        </form>
    @elseif (! $subscribed)
        <p class="mt-8">You’re on Try. Ask your agent for create_checkout to get Plus ({{ $status['pro_credits'] }} credits a month for ${{ $status['pro_price_usd'] }}) when you’re ready to keep going.</p>
    @endif

    <a href="/" class="link mt-10 inline-block text-sm">Back to ReviseMy</a>
</x-simple-page>
