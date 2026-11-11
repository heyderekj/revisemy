@props(['label', 'number', 'heading'])

<details class="group mx-[var(--rm-pad)] border-b border-border">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 [&::-webkit-details-marker]:hidden">
        <h2 class="text-base font-semibold tracking-tight text-zinc-900 sm:text-lg">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.07em] text-muted-foreground">
                <span class="text-zinc-400 tabular-nums">{{ $number }}</span>
                <span class="text-border-strong" aria-hidden="true">/</span>
                {{ $label }}
            </span>
            {{ $heading }}
        </h2>
        <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-zinc-500 group-open:rotate-180" />
    </summary>

    {{-- Keep the fragment target inside the disclosure so linking to it opens the panel. --}}
    <div {{ $attributes->class('scroll-mt-24 pb-6 lg:scroll-mt-8') }}>
        {{ $slot }}
    </div>
</details>
