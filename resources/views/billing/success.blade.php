<x-simple-page title="Plus unlocked — ReviseMy" eyebrow="Billing" heading="You’re on Plus" robots="noindex, nofollow" :footer="false">
    <p>
        Thanks{{ $email ? ' — Paddle sends the receipt to '.$email : '' }}.
        Your workspace has {{ (int) config('billing.plans.pro.credits', 100) }} credits this month at full capture quality.
        Your agent can carry on, and get_billing shows the balance.
    </p>
    @if ($manageUrl)
        <p class="mt-4 text-sm text-muted-foreground">
            Cancel or change your card any time: <a href="{{ $manageUrl }}" class="link">Manage billing</a>, or ask your agent for create_portal.
        </p>
    @endif
    <x-billing.credit-costs compare tone="confirm" class="mt-10 max-w-md" />
</x-simple-page>
