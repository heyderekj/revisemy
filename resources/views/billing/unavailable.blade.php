<x-simple-page :shell="false" title="Checkout isn’t available — ReviseMy" eyebrow="Billing" heading="Checkout isn’t available right now" robots="noindex, nofollow">
    <p>
        Nothing was charged. Try the link again in a minute, or ask your agent for a fresh create_checkout link.
        If it keeps happening, the person who runs this ReviseMy server needs to look at its billing setup.
    </p>
    <a href="/" class="link mt-8 inline-block text-sm">Back to ReviseMy</a>
    {{-- A buyer who hit a broken checkout: worth seeing on the dashboard, not only in the log. --}}
    <x-fathom-event name="Checkout unavailable" />
</x-simple-page>
