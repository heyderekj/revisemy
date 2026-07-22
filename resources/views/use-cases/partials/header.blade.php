<header class="flex items-center justify-between gap-4">
    <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
        <x-revisemy-logo variant="wordmark" size="lg" />
    </a>
    {{-- Mobile: real button in the header. Desktop: spacer so the sticky CTA aligns with the logo. --}}
    <x-try-token-cta
        id="rm-use-case-hero-cta"
        fathom-event="Try token use case header"
        class="sm:hidden"
    />
    <div class="invisible hidden pointer-events-none sm:block" aria-hidden="true">
        <x-try-token-cta fathom-event="Try token use case header" />
    </div>
</header>
