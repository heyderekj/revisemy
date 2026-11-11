@php
    $guestUntil = $review->guestAccessUntil();
@endphp

<flux:modal.trigger name="share-review"
    x-init="if (new URLSearchParams(location.search).get('share') === '1') $nextTick(() => $dispatch('modal-show', { name: 'share-review' }))">
    <flux:button size="sm" variant="ghost" icon="link" class="!rounded-full !bg-chip hover:!bg-chip-hover" aria-label="Share">
        <span class="hidden sm:inline">Share</span>
    </flux:button>
</flux:modal.trigger>

<flux:modal name="share-review" class="w-full max-w-md">
    <div
        class="space-y-4"
        x-data="{
            status: '',
            async copyLink() {
                const input = this.$refs.guestLink;
                try {
                    await navigator.clipboard.writeText(input.value);
                    this.status = 'Guest link copied';
                } catch {
                    input.focus();
                    input.select();
                    this.status = 'Copy the selected link to share it.';
                }
            },
        }"
        x-on:share-url-updated.window="status = 'New guest link ready'"
    >
        <flux:heading size="lg">Share review</flux:heading>
        <button type="button" wire:click="startFeedbackRequest"
            class="flex w-full items-center gap-3 rounded-xl border border-border bg-well px-3 py-2.5 text-left hover:bg-chip">
            <flux:icon.envelope variant="mini" class="shrink-0 text-muted-foreground" />
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-medium text-zinc-900">Request feedback</span>
                <span class="block text-xs text-muted-foreground">Ask a question with a personal link.</span>
            </span>
            <flux:icon.chevron-right variant="micro" class="shrink-0 text-muted-foreground" />
        </button>
        <div>
            <label for="guest-link" class="text-sm font-medium">Guest link</label>
            <div class="mt-1.5 flex gap-2">
                <input
                    id="guest-link"
                    x-ref="guestLink"
                    class="min-w-0 flex-1 rounded-lg border border-border bg-well px-3 text-base text-zinc-700 sm:text-sm"
                    type="url"
                    value="{{ $review->shareUrl() }}"
                    readonly
                    x-on:focus="$el.select()"
                >
                <flux:button variant="primary" x-on:click="copyLink()" :disabled="! $review->allowsGuestAccess()">Copy link</flux:button>
            </div>
            <p class="mt-1.5 text-xs text-muted-foreground">
                @if ($review->isExpired())
                    This review has expired.
                @elseif ($review->isShareLinkExpired())
                    This link has expired. Change the expiry or generate a new link.
                @elseif ($guestUntil)
                    Anyone with the link can open the review until {{ $guestUntil->timezone(config('app.timezone'))->toFormattedDateString() }}.
                @else
                    Anyone with the link can open the review.
                @endif
            </p>
            <span role="status" class="text-xs text-muted-foreground" x-show="status" x-text="status"></span>
        </div>

        @unless ($review->isExpired())
            <div class="space-y-2 border-t border-border pt-3">
                <label for="guest-expiry" class="text-sm font-medium">Link expiry</label>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="setShareExpiry('7d')">7 days</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="setShareExpiry('14d')">14 days</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="setShareExpiry('never')">Until review expires</flux:button>
                    <input id="guest-expiry" type="date" aria-label="Custom expiry date" class="rounded-lg border border-border bg-well px-2 py-1 text-base sm:text-sm"
                        min="{{ now()->format('Y-m-d') }}"
                        value="{{ $review->share_expires_at?->format('Y-m-d') }}"
                        x-on:change="if ($el.value && $el.validity.valid) $wire.setShareExpiryDate($el.value)"
                    >
                </div>
                <flux:error name="shareExpiryDate" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
                <flux:button size="sm" variant="ghost" wire:click="toggleComments">
                    {{ $review->allowsComments() ? 'Turn off comments and reactions' : 'Turn on comments and reactions' }}
                </flux:button>
                <flux:button size="sm" variant="ghost" wire:click="regenerateShareToken" wire:confirm="Generate a new guest link? The current link will stop working.">New link</flux:button>
            </div>
        @endunless
    </div>
</flux:modal>
