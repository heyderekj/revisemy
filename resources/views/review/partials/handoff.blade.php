{{-- The handoff stays tucked away until the owner needs it. Asking for
     changes also opens the same actions in a dialog. --}}
@if ($mode === 'owner' && $review->origin === \App\Models\Review::ORIGIN_WEB)
    @if ($review->isAwaitingAgent())
        @php($markCount = $review->screenshots->flatMap->annotations->filter(fn ($mark) => in_array($mark->source ?: \App\Models\Annotation::SOURCE_HUMAN, [\App\Models\Annotation::SOURCE_HUMAN, \App\Models\Annotation::SOURCE_GUEST], true))->count())

        <details class="group rounded-xl bg-well px-3" data-panel="handoff" data-handoff="waiting">
            <summary class="flex cursor-pointer list-none items-center gap-2 py-3 text-sm font-medium text-zinc-900 [&::-webkit-details-marker]:hidden">
                <flux:icon.paper-airplane variant="micro" class="size-4 text-muted-foreground" />
                <span class="flex-1">Hand this to your agent</span>
                <flux:icon.chevron-down variant="micro" class="size-4 text-muted-foreground group-open:rotate-180" />
            </summary>
            <p class="text-xs text-muted-foreground">The first agent to open this link gets the review and its marks.</p>
            <x-handoff-actions :review="$review" class="mt-3 pb-3" />
        </details>

        {{-- Right after Ask for changes: send it while it's on your mind. --}}
        <template x-teleport="body">
            <div
                x-data="{ open: false, asked: false }"
                x-on:handoff-ready.window="asked = !! $event.detail?.asked; open = true"
                x-on:keydown.escape.window="open = false"
                x-show="open"
                x-cloak
                class="fixed inset-0 z-[80] flex items-end justify-center p-3 sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="handoff-dialog-title"
            >
                <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" x-on:click="open = false" aria-hidden="true"></div>
                <div
                    x-show="open"
                    x-trap.noscroll="open"
                    x-transition:enter="transition duration-300 ease-[cubic-bezier(0.23,1,0.32,1)]"
                    x-transition:enter-start="translate-y-3 opacity-0 sm:translate-y-0 sm:scale-[0.97]"
                    x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                    class="relative w-full max-w-md rounded-2xl bg-card p-5 shadow-xl ring-1 ring-black/5"
                >
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 id="handoff-dialog-title" class="text-base font-semibold text-zinc-900">
                                <span x-text="asked ? 'Changes asked for. Now send it to your agent' : 'Send it to your agent'">Send it to your agent</span>
                            </h2>
                            <p class="mt-1 text-sm text-pretty text-zinc-500">
                                @if ($markCount === 0)
                                    Mark anything you want changed first. Your agent reads your marks as work and sends you the next pass to check.
                                @else
                                    It reads your {{ $markCount === 1 ? 'mark' : $markCount.' marks' }} as work and sends you the next pass to check.
                                @endif
                            </p>
                        </div>
                        <button type="button" x-on:click="open = false" class="-mr-1 -mt-1 inline-flex size-8 shrink-0 items-center justify-center rounded-full text-zinc-500 transition-colors hover:bg-chip" aria-label="Close">
                            <flux:icon.x-mark variant="micro" class="size-4" />
                        </button>
                    </div>
                    <x-handoff-actions :review="$review" class="mt-4" />
                </div>
            </div>
        </template>
    @else
        <div class="flex items-start gap-2.5 rounded-2xl bg-card px-3 py-3 sm:px-4" data-panel="handoff" data-handoff="taken">
            <flux:icon.check-circle variant="micro" class="mt-0.5 size-4 shrink-0 text-done-ink" />
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-900">{{ $review->handed_off_to ?: 'Your agent' }} has this review</p>
                <p class="text-xs text-muted-foreground">
                    It opened the link {{ $review->handed_off_at?->diffForHumans() }}. Its replies show on your marks.
                </p>
            </div>
        </div>
    @endif
@endif
