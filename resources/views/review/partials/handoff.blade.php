{{-- A review started on the site at /new: getting it to an agent. Three
     steps that tick themselves off as the owner works, then the ways to send
     it (x-handoff-actions). Asking for changes opens the same actions in a
     dialog, since that's the moment to send it. Once an agent has opened the
     link, one line names it. Web-only, like guest link management
     (docs/CONNECTORS.md). --}}
@if ($mode === 'owner' && $review->origin === \App\Models\Review::ORIGIN_WEB)
    @if ($review->isAwaitingAgent())
        @php($markCount = $review->screenshots->flatMap->annotations->filter(fn ($mark) => in_array($mark->source ?: \App\Models\Annotation::SOURCE_HUMAN, [\App\Models\Annotation::SOURCE_HUMAN, \App\Models\Annotation::SOURCE_GUEST], true))->count())
        @php($decided = $review->effectiveStatus())
        @php($steps = [
            [
                'label' => 'Mark what matters',
                'detail' => $markCount > 0 ? $markCount.' '.\Illuminate\Support\Str::plural('mark', $markCount) : 'Drag on the page to mark a spot',
                'done' => $markCount > 0,
            ],
            [
                'label' => $decided === \App\Models\Review::STATUS_APPROVED ? 'Approved' : 'Ask for changes',
                'detail' => in_array($decided, [\App\Models\Review::STATUS_CHANGES_REQUESTED, \App\Models\Review::STATUS_APPROVED], true) ? null : 'Or approve it as it is',
                'done' => in_array($decided, [\App\Models\Review::STATUS_CHANGES_REQUESTED, \App\Models\Review::STATUS_APPROVED], true),
            ],
            [
                'label' => 'Send it to your agent',
                'detail' => 'The first agent to open the link keeps the review',
                'done' => false,
            ],
        ])
        @php($current = collect($steps)->search(fn ($step) => ! $step['done']))

        <section class="rounded-2xl bg-card px-3 py-3 sm:px-4 sm:py-4" data-panel="handoff" data-handoff="waiting">
            <flux:heading size="sm">Hand this to your agent</flux:heading>

            <ol class="mt-3 space-y-2">
                @foreach ($steps as $i => $step)
                    <li class="flex gap-2.5">
                        <span @class([
                            'mt-px flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums',
                            'bg-done-soft text-done-ink' => $step['done'],
                            'bg-zinc-900 text-white night:bg-white night:text-zinc-900' => ! $step['done'] && $i === $current,
                            'bg-chip text-zinc-500' => ! $step['done'] && $i !== $current,
                        ]) aria-hidden="true">
                            @if ($step['done'])
                                <flux:icon.check variant="micro" class="size-3.5" />
                            @else
                                {{ $i + 1 }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p @class(['text-sm', 'text-zinc-500' => $step['done'], 'font-medium text-zinc-900' => ! $step['done']])>
                                {{ $step['label'] }}<span class="sr-only">{{ $step['done'] ? ', done' : '' }}</span>
                            </p>
                            @if ($step['detail'])
                                <p class="text-xs text-muted-foreground">{{ $step['detail'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

            <x-handoff-actions :review="$review" class="mt-3.5" />
        </section>

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
