{{-- Stylized dual preview: AI chat (inline MCP review) + review page.
     Decorative only — no photos, no live data. --}}
<section
    class="rm-hero-loop rm-bleed relative border-y border-zinc-200 bg-[var(--color-border)]"
    aria-label="Preview of ReviseMy in AI chat and on the review page"
>
    <x-cross-mark left="0" top="0" />
    <x-cross-mark left="100%" top="0" />
    <x-cross-mark left="50%" top="0" visibility="hidden sm:block" />
    <x-cross-mark left="0" top="100%" />
    <x-cross-mark left="100%" top="100%" />
    <x-cross-mark left="50%" top="100%" visibility="hidden sm:block" />

    <div class="grid grid-cols-1 gap-px sm:grid-cols-2">
        {{-- AI chat --}}
        <div class="rm-hero-loop-panel bg-[var(--color-canvas)] p-4 sm:p-5" aria-hidden="true">
            <div class="mb-3 flex items-center justify-between gap-2">
                <span class="text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400">Agent chat</span>
                <span class="text-[11px] text-zinc-400">MCP · inline</span>
            </div>

            <div class="space-y-3">
                <div class="rm-hero-loop-bubble max-w-[92%] bg-zinc-100 px-3 py-2 text-[13px] leading-relaxed text-zinc-700">
                    Run a design checkup on the hero.
                </div>

                <div class="rm-hero-loop-bubble rm-hero-loop-bubble-delay ml-auto max-w-[94%] space-y-3 bg-zinc-900 px-3 py-2.5 text-[13px] leading-relaxed text-zinc-100">
                    <p>
                        Created a review — mark feedback inline, then approve or request changes.
                    </p>

                    {{-- Inline review card — capture input only; marks live on the review side --}}
                    <div class="overflow-hidden bg-[var(--color-canvas)] text-zinc-900 ring-1 ring-zinc-700/40">
                        <div class="relative aspect-[16/10] bg-zinc-100">
                            <x-hero-wireframe dashed class="inset-3" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Review page — animates in after agent chat --}}
        <div class="rm-hero-loop-panel bg-[var(--color-canvas)] p-4 sm:p-5" aria-hidden="true">
            <div class="rm-hero-loop-review mb-3 flex items-center justify-between gap-2">
                <span class="text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400">Review page</span>
                <span class="text-[11px] text-zinc-400">Browser · marks</span>
            </div>

            <div class="space-y-3">
                <div class="rm-hero-loop-review rm-hero-loop-review-delay overflow-hidden bg-white ring-1 ring-zinc-200">
                    <div class="flex items-center justify-between gap-2 border-b border-zinc-200/80 bg-zinc-50/90 px-2.5 py-2">
                        <div class="flex min-w-0 items-center gap-2">
                            <img src="/images/app-icon.png" alt="" width="20" height="20" class="size-5 shrink-0" decoding="async">
                            <span class="text-[13px] font-semibold tracking-tight text-zinc-900">Review</span>
                            <span class="bg-white px-1.5 py-0.5 text-[9px] font-medium tabular-nums text-zinc-600 ring-1 ring-zinc-200">Pass 1</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <span class="bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-600 ring-1 ring-zinc-200/80">Share</span>
                            <span class="bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-600 ring-1 ring-zinc-200/80">Changes</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-[minmax(0,1fr)_9.5rem] gap-0 border-b border-zinc-200">
                        <div class="relative aspect-[4/3] bg-zinc-100 sm:aspect-[16/11]">
                            <x-hero-wireframe class="inset-3" />
                            <span class="rm-hero-loop-mark absolute left-[30%] top-[56%] z-[2] flex h-5 min-w-5 -translate-x-1/2 -translate-y-1/2 items-center justify-center bg-rose-500 px-0.5 text-[9px] font-semibold text-accent-contrast ring-2 ring-white">M1</span>
                            <span class="rm-hero-loop-mark rm-hero-loop-mark-delay absolute left-[72%] top-[48%] z-[2] flex h-5 min-w-5 -translate-x-1/2 -translate-y-1/2 items-center justify-center border border-dashed border-sky-500 bg-white px-0.5 text-[9px] font-semibold text-sky-700 ring-2 ring-white">S1</span>
                            <div class="pointer-events-none absolute left-[72%] top-[62%] h-8 w-14 -translate-x-1/2 -translate-y-1/2 border border-dashed border-sky-400/70 bg-sky-400/10"></div>
                        </div>

                        <div class="flex flex-col border-l border-zinc-200 bg-zinc-50/50 p-2">
                            <p class="mb-1.5 text-[9px] font-medium uppercase tracking-[0.12em] text-zinc-400">My marks</p>
                            <div class="flex items-start gap-1.5 bg-white p-1.5 ring-1 ring-zinc-200">
                                <span class="mt-0.5 flex h-4 min-w-4 shrink-0 items-center justify-center bg-rose-500 text-[8px] font-semibold text-accent-contrast">M1</span>
                                <div class="min-w-0">
                                    <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-600">Must fix</p>
                                    <p class="mt-0.5 text-[10px] leading-snug text-zinc-600">Tighten hero hierarchy</p>
                                </div>
                            </div>
                            <p class="mt-2 text-[9px] font-medium uppercase tracking-[0.12em] text-zinc-400">Second opinion</p>
                            <div class="mt-1 flex items-start gap-1.5 border border-dashed border-sky-200 bg-sky-50/50 p-1.5">
                                <span class="mt-0.5 flex h-4 min-w-4 shrink-0 items-center justify-center border border-dashed border-sky-500 bg-white text-[8px] font-semibold text-sky-700">S1</span>
                                <p class="text-[10px] leading-snug text-zinc-500">Hint — not a decision</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-zinc-50/90 px-2.5 py-2 text-[10px] text-zinc-500">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 bg-rose-500"></span>
                            Your marks
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 border border-dashed border-sky-500 bg-white"></span>
                            Second opinion
                        </span>
                        <span class="ml-auto text-zinc-400">Drag to mark</span>
                    </div>
                </div>

                <div class="ml-auto max-w-[94%] space-y-2">
                    <div class="rm-hero-loop-review rm-hero-loop-review-delay-2 bg-zinc-100 px-3 py-2 text-[13px] leading-relaxed text-zinc-700">
                        Drag to mark a region, or click for a point.
                    </div>
                    <div class="rm-hero-loop-review rm-hero-loop-review-delay-3 bg-zinc-100 px-3 py-2 text-[13px] leading-relaxed text-zinc-700">
                        Share a guest link for another set of eyes.
                    </div>
                    <div class="rm-hero-loop-review rm-hero-loop-review-delay-4 bg-zinc-100 px-3 py-2 text-[13px] leading-relaxed text-zinc-700">
                        Verify a fix when the next pass looks right.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
