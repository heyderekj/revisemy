{{-- Where a pane's story finishes: dashed for a hand-off, solid for done. --}}
@props(['done' => false])

<div @class([
    'mt-auto flex items-center gap-2 rounded-xl px-3 py-2.5 text-xs font-medium',
    'bg-done-soft text-done-ink' => $done,
    'border border-dashed border-zinc-300 text-zinc-500' => ! $done,
])>
    @if ($done)
        <flux:icon.shield-check variant="micro" class="size-4 shrink-0" />
    @else
        <flux:icon.arrow-uturn-right variant="micro" class="size-4 shrink-0 text-zinc-400" />
    @endif
    <span class="min-w-0">{{ $slot }}</span>
</div>
