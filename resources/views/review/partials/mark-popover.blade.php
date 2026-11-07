{{-- The open mark ($openMarkId): beside it on the shot at md and up, a sheet
     from the bottom on phones. Click a mark, its chip or its card to open it;
     Escape, ×, a click elsewhere or the same mark again puts it away. The
     board opens the same body (x-mark-detail) in its sheet, and the inline
     review in mcp/partials/mark-detail. --}}
@php($open = $pendingX === null ? $this->openedMark() : null)
@if ($open)
    <div
        wire:key="mark-popover-{{ $open->id }}"
        class="pointer-events-none fixed inset-0 z-50"
        data-mark-popover="{{ $open->id }}"
        x-data="{
            left: null,
            top: null,
            origin: 'left center',
            placed: false,
            mobile() {
                return window.matchMedia('(max-width: 767px)').matches;
            },
            anchor() {
                return document.querySelector('[data-mark-id=&quot;{{ $open->id }}&quot;]');
            },
            get panelStyle() {
                if (this.mobile()) return '';
                if (! this.placed) return 'visibility: hidden;';
                return 'left:' + this.left + 'px; top:' + this.top + 'px; transform-origin: ' + this.origin + ';';
            },
            place() {
                const panel = this.$refs.panel;
                if (! panel) return false;
                if (this.mobile()) {
                    this.placed = true;
                    return true;
                }
                const anchor = this.anchor();
                // Not on the shot in view: sit at the right edge, mid-height.
                if (! anchor) {
                    this.left = window.innerWidth - (panel.offsetWidth || 352) - 16;
                    this.top = Math.max(8, (window.innerHeight - (panel.offsetHeight || 420)) / 2);
                    this.placed = true;
                    return true;
                }
                const at = window.rmPlaceBeside(anchor.getBoundingClientRect(), panel.offsetWidth || 352, panel.offsetHeight || 420);
                if (! at) return false;
                Object.assign(this, at);
                this.placed = true;
                return true;
            },
            placeWhenReady(attempts = 0) {
                if (this.place() || attempts >= 20) {
                    if (this.mobile()) {
                        this.$nextTick(() => window.rmRevealAbove(this.anchor(), this.$refs.panel?.offsetHeight || 0));
                    } else if (this.anchor()) {
                        this.anchor().scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                    }
                    return;
                }
                requestAnimationFrame(() => this.placeWhenReady(attempts + 1));
            },
            onScroll(e) {
                if (e?.target?.closest?.('[data-mark-popover]')) return;
                this.place();
            },
            close() {
                $store.rmFocus.close();
            },
        }"
        x-init="
            placeWhenReady();
            let unhook = window.Livewire?.hook?.('morph.updated', () => $nextTick(() => place()));
            return () => { if (typeof unhook === 'function') unhook(); };
        "
        x-on:resize.window.debounce.50ms="place()"
        x-on:scroll.window.capture="onScroll($event)"
        x-on:keydown.escape.window="if (! $event.target.closest('input, textarea')) close()"
    >
        {{-- Phones: a tap above the sheet puts it away. --}}
        <button type="button" class="pointer-events-auto fixed inset-0 bg-black/20 md:hidden" x-on:click="close()" aria-label="Close mark"></button>

        <div
            x-ref="panel"
            x-bind:style="panelStyle"
            x-on:click.outside="if (! mobile()) close()"
            class="pointer-events-auto fixed inset-x-0 bottom-0 flex max-h-[min(80svh,40rem)] flex-col overflow-hidden rounded-t-2xl bg-lift shadow-[0_-12px_40px_-18px_rgba(0,0,0,0.45)] ring-1 ring-black/[0.07] pb-[env(safe-area-inset-bottom)] md:inset-x-auto md:bottom-auto md:w-[min(22rem,calc(100vw-1rem))] md:max-h-[min(70svh,40rem)] md:rounded-2xl md:pb-0 md:shadow-[0_18px_50px_-24px_rgba(0,0,0,0.45)]"
            role="dialog"
            aria-label="Mark M{{ $open->number }}"
        >
            <div class="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-zinc-200 md:hidden" aria-hidden="true"></div>
            <div class="flex shrink-0 items-start justify-between gap-2 px-4 pt-3 pb-2 md:px-3.5">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold {{ $open->markerClass() }}">M{{ $open->number }}</span>
                    <span class="text-xs text-muted-foreground">{{ $open->label() }}</span>
                    <x-signal-tag :tone="$open->statusTone()">{{ $open->statusLabel() }}</x-signal-tag>
                    @if ($open->changedByAgent())
                        <x-agent-tag />
                    @endif
                    @if ($open->source && $open->source !== \App\Models\Annotation::SOURCE_HUMAN)
                        <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-600">{{ $open->sourceLabel() }}</span>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                        <button type="button" class="h-9 rounded-md px-2 text-xs text-zinc-400 transition-colors hover:text-zinc-900 md:h-7" wire:click="deletePin({{ $open->id }})">Remove</button>
                    @endif
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-md text-zinc-400 transition hover:bg-chip hover:text-zinc-700 md:size-7"
                        x-on:click="close()"
                        aria-label="Close mark"
                    >
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-3 md:px-3.5">
                <x-mark-detail :mark="$open" :owner-view="$mode === 'owner'" compact />
                <x-mark-timeline :mark="$open" :owner-view="$mode === 'owner'" class="mt-4" wire:key="popover-timeline-{{ $open->id }}-{{ $open->updated_at?->timestamp }}" />
            </div>

            <div class="shrink-0 border-t border-zinc-100 px-4 pt-1 pb-3 md:px-3.5">
                @include('review.partials.mark-actions', ['pin' => $open, 'previous' => false, 'inPopover' => true])
            </div>
        </div>
    </div>
@endif
