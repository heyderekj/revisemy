{{-- A comment pinned on the frame. With `agent`, the tool's agent replies
     and resolves it; without, the thread just ends at Resolved. --}}
@php($agent = $compare['agent'] ?? false)

<x-alternative-compare.shot muted>
    <span class="absolute flex size-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full rounded-bl-none bg-zinc-700 text-[10px] font-semibold text-white shadow ring-2 ring-white" style="left: 32%; top: 24%;">Y</span>
</x-alternative-compare.shot>

<div class="rounded-xl bg-raised p-3 shadow-xs ring-1 ring-black/[0.04]">
    <x-alternative-compare.person name="You" meta="just now" />
    <p class="mt-1.5 text-sm leading-relaxed text-zinc-700">{{ $compare['note'] }}</p>
    <div class="mt-3 border-t border-border pt-3">
        @if ($agent)
            <div class="flex items-center gap-2"><x-alternative-compare.agent-chip /><span class="text-[11px] text-zinc-400">2m</span></div>
            <p class="mt-1.5 text-sm leading-relaxed text-zinc-700">Updated the hero headline. Marking this resolved.</p>
        @else
            <x-alternative-compare.person name="Sam" meta="1m" />
            <p class="mt-1.5 text-sm leading-relaxed text-zinc-700">{{ $compare['reply'] ?? 'Agreed. I’ll pass it on.' }}</p>
        @endif
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
        <span class="flex size-4 items-center justify-center rounded bg-zinc-700 text-white"><flux:icon.check variant="micro" class="size-3" /></span>
        Resolved{{ $agent ? ' by the agent' : '' }}
        @if (! empty($compare['priority']))
            <span class="ml-auto rounded bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-600">{{ $compare['priority'] }}</span>
        @endif
    </div>
</div>
