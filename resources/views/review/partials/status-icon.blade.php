@php
    $statusIcon = $review->isArchived() ? 'archive-box' : match ($review->effectiveStatus()) {
        \App\Models\Review::STATUS_APPROVED => 'check-circle',
        \App\Models\Review::STATUS_CHANGES_REQUESTED => 'arrow-uturn-left',
        \App\Models\Review::STATUS_EXPIRED => 'lock-closed',
        default => 'chat-bubble-left-ellipsis',
    };
@endphp
<span wire:key="review-status-icon-{{ $statusIcon }}" class="rm-status-icon inline-flex" aria-hidden="true">
    <flux:icon :name="$statusIcon" variant="micro" class="size-4 shrink-0" />
</span>
