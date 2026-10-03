<x-simple-page title="Payment received — ReviseMy" eyebrow="Billing" :heading="$kind === 'already_plus' ? 'You’re already on Plus' : 'Payment received'" robots="noindex, nofollow" :footer="false">
    @if ($kind === 'already_plus')
        <p>Nothing to pay. Need more credits this month? Ask your agent for a credit pack: create_checkout with product "credits_50".</p>
    @else
        <p>Thanks — your receipt comes from Polar, our merchant of record. Credits land on your workspace within a few seconds.</p>
    @endif
    <p class="mt-4 text-sm text-muted-foreground">
        Your agent can carry on: get_billing confirms the credits, and create_portal manages or cancels any time.
    </p>
    <x-billing.credit-costs compare class="mt-10 max-w-md" />
    @if (! empty($purchase))
        <x-fathom-event :name="$purchase['name']" :value="$purchase['value']" :once="$purchase['once']" />
    @endif
</x-simple-page>
