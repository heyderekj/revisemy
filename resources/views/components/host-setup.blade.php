@props([])

{{-- Expects parent Alpine scope with `mode` and `setMode(mode)`. --}}
<div {{ $attributes->class('space-y-4') }}>
    <div class="border-b border-zinc-200/90">
        <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
            <p class="pb-2 text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">
                Setup
            </p>
            <div class="flex gap-1" role="tablist" aria-label="Setup method">
                <button
                    type="button"
                    role="tab"
                    class="inline-flex items-center gap-1.5 border-b-2 px-1 pb-2 text-sm font-medium transition"
                    :class="mode === 'agent'
                        ? 'border-zinc-900 text-zinc-900'
                        : 'border-transparent text-zinc-400 hover:text-zinc-700'"
                    :aria-selected="mode === 'agent'"
                    x-on:click="setMode('agent')"
                >
                    <flux:icon.sparkles variant="micro" class="size-3.5 shrink-0" />
                    Ask agent
                </button>
                <button
                    type="button"
                    role="tab"
                    class="inline-flex items-center gap-1.5 border-b-2 px-1 pb-2 text-sm font-medium transition"
                    :class="mode === 'manual'
                        ? 'border-zinc-900 text-zinc-900'
                        : 'border-transparent text-zinc-400 hover:text-zinc-700'"
                    :aria-selected="mode === 'manual'"
                    x-on:click="setMode('manual')"
                >
                    <flux:icon.wrench-screwdriver variant="micro" class="size-3.5 shrink-0" />
                    Do it myself
                </button>
            </div>
        </div>
    </div>

    <div x-show="mode === 'agent'" x-cloak class="space-y-4" role="tabpanel">
        {{ $agent }}
    </div>

    <div x-show="mode === 'manual'" x-cloak class="space-y-4" role="tabpanel">
        {{ $manual }}
    </div>
</div>
