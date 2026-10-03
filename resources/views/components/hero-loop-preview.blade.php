{{-- The home hero: a short exchange with your agent on the stage, then the
     review page it opened, drawn by <x-review-mock> from a fictional sample.
     The agent's mark uses the headline's rm-agent-cycle classes, so it
     changes in step with "with ChatGPT / Claude / …" above. --}}
@props([
    // Name => x-host-icon name, in the headline's order. Only the icons show.
    'agents' => ['Claude' => 'claude'],
])

<section class="rm-hero-loop relative" aria-label="Preview of ReviseMy: your agent sends a review, you mark it">
    <x-review-mock sample="website" animate>
        <div class="mx-auto mb-6 flex max-w-2xl flex-col gap-4 sm:mb-8" aria-hidden="true">
            {{-- You --}}
            <div class="rm-hero-loop-bubble max-w-[85%] self-end rounded-2xl rounded-br-md bg-zinc-900 px-3.5 py-2 text-sm leading-relaxed text-zinc-50 sm:max-w-md">
                Check the new home page before we ship.
            </div>

            {{-- Your agent --}}
            <div class="rm-hero-loop-bubble rm-hero-loop-bubble-delay flex max-w-[95%] gap-3 self-start sm:max-w-lg">
                <span class="rm-agent-cycle mt-0.5 size-7 shrink-0 place-items-center rounded-full bg-raised text-zinc-800 shadow-xs ring-1 ring-black/[0.06]">
                    @foreach (array_values($agents) as $i => $icon)
                        <span class="rm-agent-cycle-item" style="--i: {{ $i }}"><x-host-icon :name="$icon" size="md" /></span>
                    @endforeach
                </span>
                <div class="min-w-0 space-y-2">
                    <p class="inline-flex items-center gap-1.5 rounded-md bg-raised/80 px-2 py-1 font-mono text-[11px] text-zinc-500 ring-1 ring-black/[0.05]">
                        <flux:icon.check variant="micro" class="size-3.5 text-done" />
                        revisemy · create_review
                    </p>
                    <p class="text-sm leading-relaxed text-zinc-800">Captured fieldnote.coffee on desktop and mobile and opened a review. Mark what matters, and I’ll pick it up from there.</p>
                    <div class="flex items-center gap-3 rounded-xl bg-raised p-2.5 pr-3 shadow-xs ring-1 ring-black/[0.06]">
                        <x-revisemy-logo size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-900">Fieldnote Coffee home page</p>
                            <p class="text-xs text-zinc-500">Review · Pass 1<span class="hidden sm:inline"> · waiting on you</span></p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-key px-2.5 py-1 text-xs font-medium text-accent-foreground">
                            Open <flux:icon.arrow-up-right variant="micro" class="size-3.5" />
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </x-review-mock>
</section>
