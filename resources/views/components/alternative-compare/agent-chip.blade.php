{{-- Marks a step the other tool's agent integration took. --}}
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-600']) }}>
    <flux:icon.cpu-chip variant="micro" class="size-3 text-zinc-400" />{{ $slot->isEmpty() ? 'Agent via MCP' : $slot }}
</span>
