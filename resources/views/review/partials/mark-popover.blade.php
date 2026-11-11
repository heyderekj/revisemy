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
            observer: null,
            frame: null,
            init() {
                this.$nextTick(() => {
                    if (! this.$refs.panel) return;
                    this.observer = new ResizeObserver(() => this.place());
                    this.observer.observe(this.$refs.panel);
                    this.placeWhenReady();
                });
            },
            destroy() {
                this.observer?.disconnect();
                cancelAnimationFrame(this.frame);
            },
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
                        this.anchor().scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest', inline: 'nearest' });
                    }
                    return;
                }
                this.frame = requestAnimationFrame(() => this.placeWhenReady(attempts + 1));
            },
            onScroll(e) {
                if (e?.target?.closest?.('[data-mark-popover]')) return;
                this.place();
            },
            close() {
                $store.rmFocus.close();
            },
        }"
        x-on:resize.window.debounce.50ms="place()"
        x-on:scroll.window.capture="onScroll($event)"
        x-on:keydown.escape.window="if (! $event.target.closest('input, textarea, [data-modal=feedback-requests]')) close()"
    >
        {{-- Phones: a tap above the sheet puts it away. --}}
        <button type="button" class="pointer-events-auto fixed inset-0 bg-black/20 md:hidden" x-on:click="close()" aria-label="Close mark"></button>

        <div
            x-ref="panel"
            x-bind:style="panelStyle"
            x-on:click.outside="if (! mobile() && ! $event.target.closest('[data-modal=feedback-requests]')) close()"
            class="pointer-events-auto fixed inset-x-0 bottom-0 flex max-h-[min(68svh,36rem)] flex-col overflow-hidden rounded-t-2xl bg-lift shadow-[0_-12px_40px_-18px_rgba(0,0,0,0.45)] ring-1 ring-black/[0.07] pb-[env(safe-area-inset-bottom)] md:inset-x-auto md:bottom-auto md:w-[min(22rem,calc(100vw-1rem))] md:max-h-[min(70svh,40rem)] md:rounded-2xl md:pb-0 md:shadow-[0_18px_50px_-24px_rgba(0,0,0,0.45)]"
            role="dialog"
            aria-label="Mark M{{ $open->number }}"
        >
            <div class="flex shrink-0 items-center justify-between gap-2 px-4 py-1.5 md:px-3.5 md:pt-3 md:pb-2">
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
                        class="flex size-11 items-center justify-center rounded-md text-muted-foreground transition hover:bg-chip hover:text-foreground md:size-7"
                        x-on:click="close()"
                        aria-label="Close mark"
                    >
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-2 md:px-3.5 md:pb-3">
                <x-mark-detail :mark="$open" :owner-view="$mode === 'owner'" :reference-action="$review->allowsComments() ? 'toggleCommentElementReference' : null" :selected-references="$activeCommentMarkId === $open->id ? $markCommentBodyReferences : []" :reference-mark-id="$open->id" compact />
                <x-mark-timeline :mark="$open" :owner-view="$mode === 'owner'" :can-react="$review->allowsComments()" class="mt-4" wire:key="popover-timeline-{{ $open->id }}-{{ $open->updated_at?->timestamp }}" />
            </div>

            <div class="shrink-0 border-t border-border px-4 pb-2 md:px-3.5 md:pt-1 md:pb-3">
                @include('review.partials.mark-actions', ['pin' => $open, 'previous' => false, 'inPopover' => true])
            </div>
        </div>
    </div>
@endif
