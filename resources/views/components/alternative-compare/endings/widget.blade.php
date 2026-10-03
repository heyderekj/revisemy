{{-- A comment from the site widget, an AI brief, and a status the agent
     can move to Done. --}}
<x-alternative-compare.shot muted>
    <span class="absolute flex size-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white shadow ring-2 ring-white" style="left: 32%; top: 24%;">1</span>
    <span class="absolute right-0 top-[30%] origin-bottom-right -rotate-90 rounded-t-md bg-zinc-700 px-2 py-1 text-[10px] font-medium text-white">Feedback</span>
</x-alternative-compare.shot>

<div class="rounded-xl bg-raised p-3 shadow-xs ring-1 ring-black/[0.04]">
    <x-alternative-compare.person name="Client" meta="on fieldnote.coffee" />
    <p class="mt-1.5 text-sm leading-relaxed text-zinc-700">{{ $compare['note'] }}</p>
    <p class="mt-2 rounded-md bg-chip/70 px-2 py-1 font-mono text-[11px] text-zinc-500">h1.hero-title · Chrome · 1440×900</p>
    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
        @foreach (['To do', 'In progress', 'Review', 'Done'] as $i => $status)
            @if ($i > 0)<flux:icon.arrow-right variant="micro" class="size-3 text-zinc-300" />@endif
            <span @class(['rounded bg-chip px-1.5 py-0.5 text-[11px] font-medium', 'text-zinc-800' => $status === 'Done', 'text-zinc-400' => $status !== 'Done'])>{{ $status }}</span>
        @endforeach
        <x-alternative-compare.agent-chip />
    </div>
</div>
