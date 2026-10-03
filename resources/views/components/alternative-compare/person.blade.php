{{-- A small avatar and name for the generic tools on the left. --}}
@props(['name', 'meta' => null])

<div class="flex items-center gap-2">
    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-[10px] font-semibold text-zinc-600">{{ mb_substr($name, 0, 1) }}</span>
    <span class="text-xs font-medium text-zinc-700">{{ $name }}</span>
    @if ($meta)
        <span class="text-[11px] text-zinc-400">{{ $meta }}</span>
    @endif
</div>
