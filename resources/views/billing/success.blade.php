<x-simple-page :shell="false" title="Payment received — ReviseMy" eyebrow="Billing" :heading="$kind === 'already_plus' ? 'You’re already on Plus' : 'Payment received'" robots="noindex, nofollow" :footer="false">
    @if ($kind === 'already_plus')
        <p>Nothing to pay. Need more credits this month? Ask your agent for a credit pack — {{ config('billing.packs.credits_50.credits') }} credits for ${{ config('billing.packs.credits_50.price_usd') }}, and they never expire.</p>
    @else
        <p>Thanks — your receipt comes from Polar, our merchant of record. Credits land on your workspace within a few seconds.</p>
    @endif
    <p class="mt-4 text-sm text-muted-foreground">
        Your agent can carry on now. It can check your credits, and open billing for you to manage or cancel any time.
    </p>
    <x-billing.credit-costs compare class="mt-10 max-w-md" />
    @if (! empty($purchase))
        <x-fathom-event :name="$purchase['name']" :value="$purchase['value']" :once="$purchase['once']" />
    @endif
</x-simple-page>
