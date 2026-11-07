{{-- Move a mark without dragging: the same steps the board's columns take.
 In the sheet's header on wide screens, in its sticky footer on phones. --}}
@if ($review->allowsMarkManagement() && $selectedMark->severity !== \App\Models\Annotation::SEVERITY_KEEP)
    @if ($selectedMark->awaitsVerification())
        <button type="button" wire:click="moveMark({{ $selectedMark->id }}, 'verified')" class="inline-flex h-11 items-center lg:h-8 gap-1 rounded-full bg-done-soft px-4 text-sm font-medium lg:px-3 lg:text-xs text-done-ink transition-colors hover:bg-emerald-200">
            <flux:icon.check variant="micro" class="size-3.5" /> Verify
        </button>
    @elseif ($selectedMark->canOwnerResolve())
        <button type="button" wire:click="moveMark({{ $selectedMark->id }}, 'resolved')" class="inline-flex h-11 items-center lg:h-8 rounded-full bg-chip px-4 text-sm font-medium lg:px-3 lg:text-xs text-zinc-700 transition-colors hover:bg-chip-hover">Mark resolved</button>
    @endif
    @if ($selectedMark->status !== \App\Models\Annotation::STATUS_OPEN)
        <button type="button" wire:click="moveMark({{ $selectedMark->id }}, 'open')" class="inline-flex h-11 items-center lg:h-8 rounded-full bg-chip px-4 text-sm font-medium lg:px-3 lg:text-xs text-zinc-700 transition-colors hover:bg-chip-hover">Reopen</button>
    @endif
@endif
