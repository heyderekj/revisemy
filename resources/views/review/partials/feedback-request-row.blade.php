@php
    $available = $feedback->isOpen() && ! $review->isExpired();
    $status = match (true) {
        $feedback->revoked_at !== null => 'Revoked',
        $feedback->responded_at !== null => 'Replied',
        ! $available => 'Expired',
        default => 'Awaiting reply',
    };
@endphp
<details wire:key="feedback-request-{{ $feedback->id }}-{{ $createdFeedbackId === $feedback->id ? 'new' : 'existing' }}"
    @if ($createdFeedbackId === $feedback->id) open @endif
    class="group rounded-xl border border-border bg-card">
    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-3 py-2.5 text-sm hover:bg-chip focus-visible:outline-2 focus-visible:outline-key [&::-webkit-details-marker]:hidden">
        <div class="min-w-0 flex-1">
            <span class="block truncate font-medium text-foreground">{{ $feedback->reviewerName() }}</span>
            <span class="block text-xs text-muted-foreground">{{ $feedback->annotation ? 'Mark M'.$feedback->annotation->number : 'Whole review' }}</span>
        </div>
        <span @class(['shrink-0 rounded-full px-2 py-0.5 text-xs font-medium', 'bg-done-soft text-done-ink' => $status === 'Replied', 'bg-chip text-muted-foreground' => $status !== 'Replied'])>{{ $status }}</span>
        <flux:icon.chevron-down variant="micro" class="shrink-0 text-muted-foreground group-open:rotate-180" />
    </summary>
    <div class="space-y-3 border-t border-border p-3">
        @unless ($feedback->group_id)
            <p class="whitespace-pre-wrap break-words text-sm text-zinc-800">{{ $feedback->question }}</p>
        @endunless
        @if ($feedback->responded_at)
            <div class="rounded-lg bg-well p-2.5 text-sm">
                @if ($feedback->respondent !== $feedback->reviewerName())
                    <p class="font-medium">{{ $feedback->respondent }}</p>
                @endif
                <p class="mt-0.5 whitespace-pre-wrap break-words text-zinc-800">{{ $feedback->response }}</p>
            </div>
        @endif
        @if ($available)
            <div class="space-y-2" x-data="{ status: '' }">
                <input x-ref="personalLink" aria-label="Personal feedback link for {{ $feedback->reviewerName() }}" value="{{ $feedback->url() }}"
                    readonly x-on:focus="$el.select()" class="w-full rounded-lg border border-border bg-well px-2.5 py-1.5 text-base sm:text-sm">
                <div class="flex flex-wrap gap-1.5">
                    <flux:button size="sm" icon="link" x-on:click="
                        try {
                            await navigator.clipboard.writeText($refs.personalLink.value);
                            status = 'Link copied';
                        } catch {
                            $refs.personalLink.focus();
                            $refs.personalLink.select();
                            status = 'Copy the selected link.';
                        }
                    ">Copy link</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="revokeFeedbackRequest({{ $feedback->id }})" wire:confirm="Revoke this feedback link?">Revoke</flux:button>
                </div>
                <p class="text-xs text-muted-foreground">Anyone with this link can open it until {{ ($review->expires_at?->min($feedback->expires_at) ?? $feedback->expires_at)->timezone(config('app.timezone'))->format('M j') }}.</p>
                <p role="status" x-show="status" x-text="status" class="text-xs text-muted-foreground"></p>
            </div>
        @endif
    </div>
</details>
