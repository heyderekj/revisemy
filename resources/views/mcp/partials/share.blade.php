<section id="inline-share" class="mt-3 space-y-2 rounded-xl bg-well p-3" aria-label="Share review" x-show="shareOpen" x-cloak
    @keydown.escape.stop="shareOpen = false">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <label for="inline-guest-link" class="text-sm font-medium text-zinc-900">Guest link</label>
        <button type="button" class="text-xs font-medium text-zinc-600 underline decoration-zinc-400 underline-offset-2 hover:text-zinc-900"
            @click="openFullReview(true)">Manage sharing on the web</button>
    </div>
    <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 hover:bg-chip-hover"
        @click="openFullReview(false, true)">Request feedback</button>
    <p class="text-xs text-muted-foreground" role="status" x-show="!shareReady && busy">Checking guest access…</p>
    <p class="text-xs text-problem-ink" x-show="!shareReady && !busy">Couldn’t check guest access. Reopen Share to try again.</p>
    <p class="text-xs text-muted-foreground" x-show="shareReady && !guestLinkAvailable">This guest link has expired. Manage it on the web.</p>
    <div class="flex flex-wrap items-center gap-2" x-show="guestLinkAvailable">
        <input id="inline-guest-link" x-ref="guestLink" :value="guestLinkAvailable ? payload.guest_share_url : ''" readonly type="url"
            @focus="$el.select()" class="h-8 min-w-28 flex-1 rounded-lg border border-input bg-background px-2.5 text-sm text-zinc-700">
        <button type="button" class="btn-lit h-8 rounded-full px-3 text-xs font-medium" @click="copyGuestLink()">Copy link</button>
    </div>
    <p class="text-xs text-muted-foreground" x-show="guestLinkAvailable && payload.guest_access?.until"
        x-text="'Available until ' + guestLinkExpiry()"></p>
    <p class="text-xs text-muted-foreground" role="status" x-show="shareStatus" x-text="shareStatus"></p>
</section>
