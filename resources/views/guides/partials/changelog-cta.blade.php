<x-home-section id="rm-use-case-footer-cta">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Open source releases</h2>
    <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
        ReviseMy is open source. Star the repo, file an issue, or get a free try token and run a checkup with your agent.
    </p>
    <div class="mt-6 flex flex-wrap gap-3">
        <a
            href="{{ config('seo.github') }}"
            target="_blank"
            rel="noreferrer"
            class="inline-flex items-center justify-center rounded-full bg-accent px-4 py-2.5 text-sm font-medium text-accent-contrast shadow-sm transition hover:bg-accent-hover"
        >
            GitHub ↗
        </a>
        <x-try-token-cta fathom-event="Try token changelog footer" />
        <flux:button variant="ghost" size="sm" href="/#how" class="!border !border-zinc-200 !bg-white hover:!border-zinc-300 hover:!bg-zinc-50">
            See how it works
        </flux:button>
    </div>
</x-home-section>
