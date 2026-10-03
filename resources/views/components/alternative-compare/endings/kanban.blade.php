{{-- Every comment is a card; the agent moves this one to Resolved. --}}
<x-alternative-compare.shot muted>
    <span class="absolute flex h-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-700 px-1.5 text-[10px] font-semibold text-white shadow ring-2 ring-white" style="left: 32%; top: 24%;">#42</span>
</x-alternative-compare.shot>

<div class="grid grid-cols-4 gap-1.5 rounded-xl bg-chip/60 p-1.5">
    @foreach (['Open', 'In progress', 'In review', 'Resolved'] as $column)
        <div class="flex min-h-36 min-w-0 flex-col gap-1.5 rounded-lg bg-raised/70 p-1.5">
            <p class="truncate px-0.5 text-[11px] font-medium text-zinc-500">{{ $column }}</p>
            @if ($column === 'Resolved')
                <div class="rounded-md bg-raised p-2 shadow-xs ring-1 ring-black/[0.06]">
                    <p class="font-mono text-[11px] text-zinc-400">#42</p>
                    <p class="mt-0.5 line-clamp-5 text-xs leading-snug text-zinc-700">{{ $compare['note'] }}</p>
                </div>
            @else
                <div class="h-16 rounded-md border border-dashed border-zinc-200"></div>
            @endif
        </div>
    @endforeach
</div>
<p class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500"><x-alternative-compare.agent-chip /> moved #42 to Resolved</p>
