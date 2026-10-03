{{-- One second-opinion hint, as review/partials/hint draws it for the owner. --}}
@props(['hint', 'number'])

<li class="rounded-xl bg-raised p-3 shadow-xs ring-1 ring-black/[0.04]">
    <div class="mb-1 flex items-start gap-2">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <span class="flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-sky-500 px-1 text-xs font-semibold text-sky-700">S{{ $number }}</span>
            <span class="text-xs text-muted-foreground">{{ \App\Models\Annotation::allSeverityLabels()[$hint['severity']] ?? ucfirst($hint['severity']) }}</span>
        </div>
        <div class="flex shrink-0 items-center gap-1">
            <span class="inline-flex h-7 items-center gap-1 rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700"><flux:icon.check variant="micro" class="size-3.5" /> Accept</span>
            <span class="inline-flex size-7 items-center justify-center rounded-full text-zinc-400"><flux:icon.x-mark variant="micro" class="size-3.5" /></span>
        </div>
    </div>
    <p class="text-sm leading-relaxed text-zinc-700">{{ $hint['text'] }}</p>
</li>
