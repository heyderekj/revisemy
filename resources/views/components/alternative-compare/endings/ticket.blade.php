{{-- A bug report with browser details; the agent closes it over MCP. --}}
<div class="rounded-xl bg-raised p-3 shadow-xs ring-1 ring-black/[0.04]">
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1 rounded-md bg-chip px-1.5 py-0.5 font-mono text-[11px] text-zinc-600"><flux:icon.bug-ant variant="micro" class="size-3" /> BUG-142</span>
        <span class="min-w-0 truncate text-sm font-medium text-zinc-900">Home page hero headline</span>
    </div>
    <x-alternative-compare.shot muted class="mt-3">
        <div class="absolute rounded border-2 border-zinc-500/70" style="left: 7%; top: 17%; width: 50%; height: 23%;"></div>
    </x-alternative-compare.shot>
    <p class="mt-3 text-sm leading-relaxed text-zinc-700">{{ $compare['note'] }}</p>
    <dl class="mt-3 grid grid-cols-[5.5rem_1fr] gap-x-3 gap-y-1 text-xs">
        <dt class="text-zinc-400">Browser</dt><dd class="text-zinc-600">Chrome 129 · macOS</dd>
        <dt class="text-zinc-400">Console</dt><dd class="text-zinc-600">0 errors</dd>
        <dt class="text-zinc-400">Status</dt>
        <dd class="flex flex-wrap items-center gap-1.5">
            <span class="rounded bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-400 line-through">Open</span>
            <flux:icon.arrow-right variant="micro" class="size-3 text-zinc-300" />
            <span class="rounded bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-700">Resolved</span>
            <x-alternative-compare.agent-chip />
        </dd>
    </dl>
</div>
