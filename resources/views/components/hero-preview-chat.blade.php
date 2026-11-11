{{-- The exchange that opens the fictional Fieldnote review. --}}
<div {{ $attributes->class('flex w-full min-w-0 max-w-md flex-col gap-3') }} aria-hidden="true">
    {{-- You --}}
    <div class="max-w-[85%] self-end rounded-2xl rounded-br-md bg-zinc-900 px-3.5 py-2 text-sm leading-relaxed text-zinc-50 sm:max-w-md">
        Check the new home page before we ship.
    </div>

    {{-- Your agent --}}
    <div class="flex w-full min-w-0 gap-3">
        <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-raised text-zinc-800 shadow-xs ring-1 ring-black/[0.06]">
            <x-host-icon name="claude" size="md" />
        </span>
        <div class="min-w-0 flex-1 space-y-2">
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
                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-zinc-800/5 px-2.5 py-1 text-xs font-medium text-zinc-800 dark:bg-white/10 dark:text-white">
                    Open <flux:icon.arrow-up-right variant="micro" class="size-3.5" />
                </span>
            </div>
        </div>
    </div>
</div>
