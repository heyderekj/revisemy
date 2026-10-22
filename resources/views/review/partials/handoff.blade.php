{{-- A review started on the site at /new: how its link reaches an agent.
     Web-only, like guest link management (docs/CONNECTORS.md). Once an
     agent has opened the link, one line says so. --}}
@if ($mode === 'owner' && $review->origin === \App\Models\Review::ORIGIN_WEB)
    @if ($review->isAwaitingAgent())
        @php($handoffPrompt = 'Open my ReviseMy review '.$review->reviewUrl().' and work through my marks.')
        <section class="rounded-2xl bg-card px-3 py-3 sm:px-4 sm:py-4" data-panel="handoff">
            <flux:heading size="sm">Hand this to your agent</flux:heading>
            <p class="mt-1.5 text-xs leading-relaxed text-muted-foreground">Mark what matters and ask for changes, then paste this to your agent. The first agent to open the link keeps the review.</p>
            <div class="mt-3 rounded-xl bg-well p-3" x-data>
                <p x-ref="prompt" class="break-words text-[13px] leading-relaxed text-zinc-700">{{ $handoffPrompt }}</p>
                <button
                    type="button"
                    class="mt-2 text-xs font-medium text-zinc-900 underline-offset-2 hover:underline"
                    x-on:click="navigator.clipboard.writeText($refs.prompt.textContent); $el.textContent = 'Copied'; setTimeout(() => $el.textContent = 'Copy for your agent', 1600)"
                >Copy for your agent</button>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">No agent connected? <a href="/connectors" class="link">Connect the one you use</a>.</p>
        </section>
    @else
        <p class="flex items-center gap-2 rounded-2xl bg-card px-3 py-3 text-xs text-muted-foreground sm:px-4" data-panel="handoff">
            <flux:icon.check-circle variant="micro" class="size-4 shrink-0 text-done-ink" />
            Your agent has this review.
        </p>
    @endif
@endif
