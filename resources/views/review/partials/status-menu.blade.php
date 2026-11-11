{{-- The review's status, and what the owner can do with it: decide, change a
     decision on the latest pass, archive. The review page passes
     $undoable = true so a first decision keeps its few seconds to take back. --}}
@php
    $tag = $review->statusTag();
    $status = $review->effectiveStatus();
    $undoable ??= false;
    $quiet ??= false;
    $decide = fn (string $kind, string $status) => $undoable
        ? "\$dispatch('rm-decide', { kind: '{$kind}' })"
        : "\$wire.setReviewStatus('{$status}')";
@endphp
<flux:dropdown position="bottom" align="start" class="{{ $quiet ? 'min-w-0' : 'shrink-0' }} {{ $class ?? '' }}">
    <button
        type="button"
        @class([
            'inline-flex max-w-full items-center gap-0.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-zinc-300',
            'h-6 px-0.5 text-muted-foreground transition-colors hover:text-foreground' => $quiet,
            'text-done-ink' => $quiet && $status === \App\Models\Review::STATUS_APPROVED && ! $review->isArchived(),
            'text-attention-ink' => $quiet && $status === \App\Models\Review::STATUS_CHANGES_REQUESTED && ! $review->isArchived(),
            'transition hover:opacity-80' => ! $quiet,
        ])
        title="{{ $tag['label'] }}{{ $review->isArchived() ? ' · Archived' : '' }}"
        aria-label="Status: {{ $tag['label'] }}{{ $review->isArchived() ? ', archived' : '' }}"
    >
        @if ($quiet)
            @include('review.partials.status-icon')
        @else
            <x-signal-tag :tone="$tag['tone']">{{ $tag['label'] }}</x-signal-tag>
            @if ($review->isArchived())
                <x-signal-tag class="ms-1">Archived</x-signal-tag>
            @endif
        @endif
        <flux:icon.chevron-down variant="micro" class="size-3.5 shrink-0 text-zinc-400" />
    </button>
    <flux:menu class="min-w-52">
        @if ($quiet)
            <flux:menu.heading>{{ $tag['label'] }}{{ $review->isArchived() ? ' · Archived' : '' }}</flux:menu.heading>
        @endif
        @if ($status === \App\Models\Review::STATUS_PENDING)
            <flux:menu.item icon="check" x-on:click="{{ $decide('approve', 'approved') }}">Approve</flux:menu.item>
            <flux:menu.item icon="arrow-uturn-left" x-on:click="{{ $decide('changes', 'changes_requested') }}">Request changes</flux:menu.item>
        @elseif ($review->canChangeStatus())
            <flux:menu.item icon="arrow-path" wire:click="setReviewStatus('pending')">Reopen for review</flux:menu.item>
            @if ($status === \App\Models\Review::STATUS_APPROVED)
                <flux:menu.item icon="arrow-uturn-left" wire:click="setReviewStatus('changes_requested')">Request changes instead</flux:menu.item>
            @else
                <flux:menu.item icon="check" wire:click="setReviewStatus('approved')">Approve instead</flux:menu.item>
            @endif
        @elseif (! $review->isLatestPass())
            <flux:menu.heading>A later pass builds on this one</flux:menu.heading>
        @elseif ($review->isExpired())
            <flux:menu.heading>Closed: past its retention</flux:menu.heading>
        @endif
        <flux:menu.separator />
        @if ($review->isArchived())
            <flux:menu.item icon="arrow-up-tray" wire:click="restoreReview">Restore to Your reviews</flux:menu.item>
        @else
            <flux:menu.item icon="archive-box" wire:click="archiveReview">Archive</flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
