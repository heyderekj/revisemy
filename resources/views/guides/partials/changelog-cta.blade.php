<section id="rm-use-case-footer-cta" class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Open source releases</h2>
    <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
        ReviseMy is open source. Star the repo, file an issue, or get a free try token and run a checkup with your agent.
    </p>
    <div class="mt-6 flex flex-wrap gap-3">
        <a
            href="{{ config('seo.github') }}"
            target="_blank"
            rel="noreferrer"
            class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-500"
        >
            GitHub ↗
        </a>
        <x-try-token-cta fathom-event="Try token changelog footer" />
        <a
            href="/#how"
            class="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-800 shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50"
        >
            See how it works
        </a>
    </div>
</section>
