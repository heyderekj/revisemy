<header class="flex items-center justify-between gap-4 border-b border-zinc-900/8 pb-6">
    <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
        <x-revisemy-logo class="!h-auto !w-[128px]" />
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
