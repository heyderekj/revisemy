{{-- Comments on a version, ticked off, then the next version by hand. --}}
<x-alternative-compare.shot muted>
    <span class="absolute flex size-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white shadow ring-2 ring-white" style="left: 32%; top: 24%;">1</span>
    <span class="absolute right-[3%] top-[6%] rounded bg-zinc-700 px-1.5 py-0.5 text-[10px] font-medium text-white">v3</span>
</x-alternative-compare.shot>

<div class="rounded-xl bg-raised p-3 shadow-xs ring-1 ring-black/[0.04]">
    <x-alternative-compare.person name="You" meta="on v3" />
    <p class="mt-1.5 text-sm leading-relaxed text-zinc-700">{{ $compare['note'] }}</p>
    <div class="mt-3 flex items-center gap-2 text-xs text-zinc-500">
        <span class="flex size-4 items-center justify-center rounded bg-zinc-700 text-white"><flux:icon.check variant="micro" class="size-3" /></span>
        Ticked off by the designer
    </div>
</div>

<div class="flex items-center gap-2 rounded-xl bg-raised p-2.5 shadow-xs ring-1 ring-black/[0.04]">
    <flux:icon.pencil-square variant="micro" class="size-4 shrink-0 text-zinc-400" />
    <p class="min-w-0 flex-1 text-xs text-zinc-600">Designer uploads <span class="font-medium text-zinc-800">v4</span></p>
    <span class="rounded bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-600">Approve v4</span>
</div>
