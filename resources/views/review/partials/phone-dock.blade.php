{{-- Phones: one bar under the shot instead of a drawer over it. The marks
     as chips (tap one to open it), the whole list one tap away, and the
     decision under the thumb. In the page's flow, so it never covers the shot. --}}
@php($dockMarks = $this->activeMarks->count())
@php($dockHints = ($mode === 'owner' ? $this->openSecondOpinion->count() : 0) + $this->openGuestSuggestions->count())
@php($dockDecides = $this->isOwner() && $review->isOpenForFeedback())
<div
    data-phone-dock
    class="material-chrome shrink-0 border-t border-border px-3 pt-1.5 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:hidden"
    x-data="{ note: false }"
>
    <div class="flex items-center gap-2">
            @php($stripMarks = $this->activeMarks)
            @php($stripSecondOpinion = $this->openSecondOpinion->filter(fn ($f) => $f->hasRegion())->values())
            @php($stripGuest = $this->openGuestSuggestions->filter(
                fn ($f) => $f->hasRegion() || ($f->x !== null && $f->y !== null)
            )->values())

            @if ($shot && ($stripMarks->isNotEmpty() || $stripSecondOpinion->isNotEmpty() || $stripGuest->isNotEmpty()))
                <div class="min-w-0 flex-1 overflow-x-auto overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <div class="flex w-max items-center gap-x-3 gap-y-2 py-1.5 pr-2 pl-1.5 text-[11px] text-zinc-600">
                        @if ($stripMarks->isNotEmpty())
                            <div class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border-2 border-key/80 bg-key/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-key text-[8px] font-semibold text-accent-contrast ring-2 ring-white">M</span>
                                </span>
                                <span class="sr-only">Your marks</span>
                                <div class="flex items-center gap-1">
                                    @foreach ($stripMarks as $pin)
                                        <button
                                            type="button"
                                            class="flex h-8 min-w-8 shrink-0 items-center justify-center rounded-md bg-accent px-1 text-xs font-semibold text-ink ring-1 ring-zinc-200/80 transition hover:ring-zinc-300"
                                            x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} ? 'ring-2 ring-key' : ''"
                                            x-on:click.stop="$store.rmFocus.open({{ $pin->id }}, 'canvas')"
                                            aria-label="Open mark M{{ $pin->number }}"
                                        >
                                            M{{ $pin->number }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($stripMarks->isNotEmpty() && ($stripSecondOpinion->isNotEmpty() || $stripGuest->isNotEmpty()))
                            <span class="h-3 w-px shrink-0 bg-zinc-300" aria-hidden="true"></span>
                        @endif

                        @if ($stripSecondOpinion->isNotEmpty())
                            <div class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-sky-400/80 bg-sky-400/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-sky-500 bg-white text-[8px] font-semibold text-sky-700 ring-2 ring-white">S</span>
                                </span>
                                <span class="sr-only">Second opinion</span>
                                <div class="flex items-center gap-1">
                                    @foreach ($stripSecondOpinion as $finding)
                                        <button
                                            type="button"
                                            class="flex h-8 min-w-8 shrink-0 items-center justify-center rounded-md border border-dashed border-sky-500 bg-white px-1 text-xs font-semibold text-sky-700 transition hover:bg-sky-50"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-sky-600 bg-sky-50 ring-2 ring-sky-300' : ''"
                                            x-on:click="
                                                $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                                $store.rmFocus.mark = null;
                                                @if ($hintFilter !== 'all' && $hintFilter !== $finding->severity)
                                                    $wire.setHintFilter('all').then(() => document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                                                @else
                                                    document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                                @endif
                                            "
                                            aria-label="Jump to second opinion S{{ $suggestionNumbers['s'][$finding->id] ?? '' }}"
                                        >
                                            S{{ $suggestionNumbers['s'][$finding->id] ?? '' }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($stripSecondOpinion->isNotEmpty() && $stripGuest->isNotEmpty())
                            <span class="h-3 w-px shrink-0 bg-zinc-300" aria-hidden="true"></span>
                        @endif

                        @if ($stripGuest->isNotEmpty())
                            <div class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-zinc-400/80 bg-zinc-400/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-zinc-500 bg-white text-[8px] font-semibold text-zinc-700 ring-2 ring-white">G</span>
                                </span>
                                <span class="sr-only">Guest</span>
                                <div class="flex items-center gap-1">
                                    @foreach ($stripGuest as $finding)
                                        <button
                                            type="button"
                                            class="flex h-8 min-w-8 shrink-0 items-center justify-center rounded-md border border-dashed border-zinc-500 bg-white px-1 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-zinc-600 bg-zinc-50 ring-2 ring-zinc-300' : ''"
                                            x-on:click="
                                                $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                                $store.rmFocus.mark = null;
                                                @if (! in_array($hintFilter, ['all', 'guest'], true))
                                                    $wire.setHintFilter('all').then(() => document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                                                @else
                                                    document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                                @endif
                                            "
                                            aria-label="Jump to guest suggestion G{{ $suggestionNumbers['g'][$finding->id] ?? '' }}"
                                        >
                                            G{{ $suggestionNumbers['g'][$finding->id] ?? '' }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        @if (! ($shot && ($stripMarks->isNotEmpty() || $stripSecondOpinion->isNotEmpty() || $stripGuest->isNotEmpty())))
            <p class="min-w-0 flex-1 truncate py-2 text-xs text-muted-foreground">
                @if ($review->isOpenForFeedback() && $shot)
                    {{ $mode === 'guest' ? 'Nothing suggested yet.' : 'No marks yet.' }}
                @endif
            </p>
        @endif
        <button
            type="button"
            data-dock-list
            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full px-3 text-sm font-medium transition-colors"
            x-bind:class="$store.rmPanel?.open ? 'bg-foreground text-background' : 'bg-chip text-zinc-900 hover:bg-chip-hover'"
            x-on:click="$store.rmPanel.toggle()"
            x-bind:aria-expanded="(!! $store.rmPanel?.open).toString()"
            aria-controls="rm-drawer"
        >
            <flux:icon.queue-list variant="micro" class="size-4" />
            <span>All</span>
            <span class="tabular-nums text-zinc-500" x-bind:class="$store.rmPanel?.open && '!text-background/70'">{{ $dockMarks + $dockHints }}</span>
        </button>
    </div>

    @if ($dockDecides)
        {{-- The decision. The note stays folded until it's wanted. --}}
        <div x-show="note" x-cloak class="mt-2">
            <flux:textarea wire:model="decisionNote" rows="2" placeholder="Note for your agent (optional)" class="!text-base" />
        </div>
        <div class="mt-2 flex items-center gap-2">
            <button type="button" class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-chip text-zinc-600 transition-colors hover:bg-chip-hover" x-on:click="note = ! note" x-bind:aria-expanded="note.toString()" aria-label="Add a note for your agent">
                <flux:icon.chat-bubble-bottom-center-text variant="micro" class="size-4" />
            </button>
            @if ($review->isAwaitingAgent())
                <button type="button" class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-chip text-zinc-600 transition-colors hover:bg-chip-hover" x-on:click="$dispatch('handoff-ready')" aria-label="Send to your agent">
                    <flux:icon.paper-airplane variant="micro" class="size-4" />
                </button>
            @endif
            <flux:button variant="ghost" icon="arrow-uturn-left" x-on:click="$dispatch('rm-decide', { kind: 'changes' })" class="!h-11 min-w-0 flex-1 !bg-chip hover:!bg-chip-hover">Changes</flux:button>
            <flux:button variant="primary" icon="check" x-on:click="$dispatch('rm-decide', { kind: 'approve' })" class="!h-11 min-w-0 flex-1">Approve</flux:button>
        </div>
    @endif
</div>
