<?php

use App\Livewire\Concerns\ChangesReviewStatus;
use App\Livewire\Concerns\FindsReviewMarks;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\Review;
use App\Services\MarkLifecycleService;
use App\Support\FeedbackText;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use ChangesReviewStatus, FindsReviewMarks;

    #[Locked]
    public string $token;

    public Review $review;

    public bool $showMarkSheet = false;

    public ?int $selectedMarkId = null;

    public string $commentBody = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->loadBoard();
    }

    /** The board opens on the owner token only. */
    protected function managesReview(): bool
    {
        return true;
    }

    protected function reloadReview(): void
    {
        $this->loadBoard();
    }

    public function loadBoard(): void
    {
        // Owner token only — the board is an owner tool, not a guest view.
        $this->review = Review::query()
            ->where('token', $this->token)
            ->with(['screenshots.annotations.comments', 'screenshots.annotations.afterScreenshot', 'screenshots.annotations.events.afterScreenshot', 'parent.screenshots.annotations.comments', 'parent.screenshots.annotations.afterScreenshot', 'parent.screenshots.annotations.events.afterScreenshot'])
            ->firstOrFail();

        if ($this->selectedMarkId && ! $this->ownedAnnotation($this->selectedMarkId)) {
            $this->closeMarkSheet();
        }
    }

    /**
     * Marks from this pass and the one it built on, grouped by status.
     * Includes "Keep this" (they land verified — leave-alone reminders).
     * Sorted by pass → shot → mark number so multi-shot boards stay scannable.
     *
     * @return array<string, \Illuminate\Support\Collection<int, Annotation>>
     */
    public function marksByStatus(): array
    {
        $reviews = collect([$this->review, $this->review->parent])->filter();

        /** @var array<int, array{pass: int, shot: int}> $shotMeta */
        $shotMeta = [];

        foreach ($reviews as $review) {
            foreach ($review->screenshots->values() as $index => $shot) {
                $shotMeta[$shot->id] = [
                    'pass' => (int) $review->pass,
                    'shot' => $index + 1,
                ];
            }
        }

        $marks = $reviews
            ->flatMap(fn (Review $review) => $review->screenshots->flatMap->annotations)
            ->unique('id')
            ->sortBy([
                fn (Annotation $mark) => $shotMeta[$mark->screenshot_id]['pass'] ?? 0,
                fn (Annotation $mark) => $shotMeta[$mark->screenshot_id]['shot'] ?? 0,
                fn (Annotation $mark) => $mark->number,
            ])
            ->values();

        $grouped = [];

        foreach (array_keys(Annotation::statusLabels()) as $status) {
            $grouped[$status] = $marks->where('status', $status)->values();
        }

        return $grouped;
    }

    public function openMark(int $annotationId): void
    {
        if (! $this->ownedAnnotation($annotationId)) {
            return;
        }

        $this->selectedMarkId = $annotationId;
        $this->commentBody = '';
        $this->showMarkSheet = true;
    }

    public function closeMarkSheet(): void
    {
        $this->showMarkSheet = false;
        $this->selectedMarkId = null;
        $this->commentBody = '';
        $this->resetValidation(['commentBody']);
    }

    public function updatedShowMarkSheet(bool $open): void
    {
        if (! $open) {
            $this->closeMarkSheet();
        }
    }

    public function getSelectedMarkProperty(): ?Annotation
    {
        if (! $this->selectedMarkId) {
            return null;
        }

        return $this->ownedAnnotation($this->selectedMarkId)
            ?->loadMissing(['screenshot.review', 'afterScreenshot', 'comments', 'events.afterScreenshot']);
    }

    /** The board is the owner's, so its comments are the owner's: same rule as the review page. */
    public function addComment(MarkLifecycleService $lifecycle): void
    {
        if (! $this->review->allowsComments() || ! $this->selectedMarkId) {
            return;
        }

        $annotation = $this->ownedAnnotation($this->selectedMarkId);

        if (! $annotation) {
            return;
        }

        $this->commentBody = FeedbackText::sanitizeBody($this->commentBody);

        $this->validate(['commentBody' => FeedbackText::bodyRules()]);

        $lifecycle->addComment($annotation, AnnotationComment::AUTHOR_OWNER, $this->commentBody);

        $this->commentBody = '';
        $this->loadBoard();
    }

    public function moveMark(int $annotationId, string $status, MarkLifecycleService $lifecycle): void
    {
        if (! $this->review->allowsMarkManagement()) {
            session()->flash('board_message', 'This review is closed — marks can no longer move.');
            $this->loadBoard();

            return;
        }

        $annotation = $this->ownedAnnotation($annotationId);

        if (! $annotation) {
            return;
        }

        match ($status) {
            Annotation::STATUS_VERIFIED => $lifecycle->verify($annotation)
                ? null
                : session()->flash('board_message', 'Only resolved marks can be verified.'),
            Annotation::STATUS_OPEN => $lifecycle->reopen($annotation),
            Annotation::STATUS_RESOLVED => $lifecycle->resolveByOwner($annotation),
            default => null,
        };

        $this->loadBoard();
    }


    /**
     * Cards move live over the review's public channel; the view keeps a slow
     * polling heartbeat as a fallback when Echo is unavailable.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        // Leading dot matches the exact broadcastAs() name (no namespace prefix).
        return [
            "echo:review.{$this->token},.MarkUpdated" => 'loadBoard',
            "echo:review.{$this->token},.ReviewDecided" => 'loadBoard',
        ];
    }
};
?>

<div
    class="rm-desk flex min-h-svh flex-col"
    x-data="{
        dragging: null,
        didDrag: false,
        {{-- Touch moves marks with the sheet's buttons; HTML drag is unreliable there. --}}
        coarse: window.matchMedia('(pointer: coarse)').matches,
        active: 0,
        pages: 0,
        shown: 1,
        {{-- A column tab is lit while its column is in view. --}}
        inView(i) {
            return i >= this.active && i < this.active + this.shown;
        },
        panning: false,
        panStartX: 0,
        panStartScroll: 0,
        panMoved: false,
        step() {
            const track = this.$refs.track;
            const col = track?.querySelector('[data-col]');
            if (! track || ! col) return 0;
            const styles = getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 16;
            return col.offsetWidth + gap;
        },
        colCount() {
            return this.$refs.track?.querySelectorAll('[data-col]').length || 0;
        },
        visibleCols() {
            const track = this.$refs.track;
            const s = this.step();
            if (! track || ! s) return 1;
            // Ignore horizontal padding so we count columns that actually fit.
            const styles = getComputedStyle(track);
            const pad = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
            return Math.max(1, Math.floor((track.clientWidth - pad + 8) / s));
        },
        pageCount() {
            return Math.max(0, this.colCount() - this.visibleCols() + 1);
        },
        needsNav() {
            const track = this.$refs.track;
            if (! track) return false;
            return track.scrollWidth > track.clientWidth + 1 && this.pageCount() > 1;
        },
        maxScroll() {
            const track = this.$refs.track;
            if (! track) return 0;
            return Math.max(0, track.scrollWidth - track.clientWidth);
        },
        go(dir) {
            this.goTo(this.active + dir);
        },
        goTo(i) {
            const track = this.$refs.track;
            const s = this.step();
            if (! track || ! s) return;
            const pages = this.pageCount();
            const next = Math.max(0, Math.min(Math.max(pages - 1, 0), i));
            track.scrollTo({ left: Math.min(next * s, this.maxScroll()), behavior: 'smooth' });
            this.active = next;
            this.pages = pages;
        },
        syncActive() {
            const track = this.$refs.track;
            const s = this.step();
            if (! track || ! s) return;
            this.pages = this.pageCount();
            this.shown = this.visibleCols();
            if (this.pages <= 1) {
                this.active = 0;
                return;
            }
            this.active = Math.max(0, Math.min(this.pages - 1, Math.round(track.scrollLeft / s)));
        },
        onArrow(e) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable) return;
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            if (! this.needsNav()) return;
            e.preventDefault();
            this.go(e.key === 'ArrowRight' ? 1 : -1);
        },
        canPan(e) {
            // Don't steal HTML5 card drags or button clicks.
            return ! e.target.closest('[draggable], button, a, input, textarea, select');
        },
        onPanStart(e) {
            if (e.pointerType === 'touch' || e.button !== 0 || ! this.canPan(e) || ! this.needsNav()) return;
            const track = this.$refs.track;
            if (! track) return;
            this.panning = true;
            this.panMoved = false;
            this.panStartX = e.clientX;
            this.panStartScroll = track.scrollLeft;
            track.classList.add('cursor-grabbing', 'select-none');
            track.classList.remove('scroll-smooth');
            track.setPointerCapture?.(e.pointerId);
        },
        onPanMove(e) {
            if (! this.panning) return;
            const track = this.$refs.track;
            if (! track) return;
            const dx = e.clientX - this.panStartX;
            if (Math.abs(dx) > 3) this.panMoved = true;
            track.scrollLeft = this.panStartScroll - dx;
            this.syncActive();
        },
        onPanEnd() {
            if (! this.panning) return;
            const track = this.$refs.track;
            this.panning = false;
            track?.classList.remove('cursor-grabbing', 'select-none');
            track?.classList.add('scroll-smooth');
            if (this.panMoved) {
                // Snap to nearest page after a drag.
                this.goTo(this.active);
            }
        },
        init() {
            this._onResize = () => this.syncActive();
            this.$nextTick(() => this.syncActive());
            window.addEventListener('resize', this._onResize);
        },
        destroy() {
            window.removeEventListener('resize', this._onResize);
        }
    }"
    x-on:keydown.window="onArrow($event)"
    wire:poll.visible.20s="loadBoard"
>
    @php($grouped = $this->marksByStatus())
    @php($total = collect($grouped)->sum(fn ($c) => $c->count()))
    @php($verified = $grouped[\App\Models\Annotation::STATUS_VERIFIED]->count())
    @php($verifiedPct = $total > 0 ? (int) round(($verified / $total) * 100) : 0)

    {{-- Koati's shell: the work sits on an inset panel over the desk. --}}
    <div class="rm-shell">
    {{-- Sticky, and one row on a phone: back, what this is, and its status. --}}
    <header class="sticky top-0 z-30 shrink-0 border-b border-border bg-background">
        <div class="mx-auto flex max-w-7xl items-center gap-2 px-3 py-2 sm:gap-4 sm:px-6 sm:py-2.5">
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo size="sm" />
                </a>
                <h1 class="sr-only shrink-0 text-lg font-semibold tracking-tight text-zinc-900 sm:not-sr-only">Board</h1>
                @if (auth('web')->user()?->workspace_id === $review->workspace_id)
                    <a href="/reviews" class="hidden shrink-0 items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-zinc-900 sm:inline-flex">
                        Your reviews
                    </a>
                @endif
            </div>

            <div class="flex min-w-0 flex-1 items-center gap-x-2">
                <span class="hidden shrink-0 items-center rounded-md bg-chip px-1.5 py-0.5 text-xs font-medium tabular-nums text-zinc-600 sm:inline-flex">
                    Pass {{ $review->pass }}
                </span>
                <span class="hidden shrink-0 items-center rounded-md bg-sky-50 px-1.5 py-0.5 text-xs font-medium text-sky-700 sm:inline-flex" title="{{ $review->typeGuidance() }}">
                    {{ $review->typeLabel() }}
                </span>
                <span class="hidden text-zinc-300 sm:inline" aria-hidden="true">·</span>
                <p class="min-w-0 truncate text-sm font-medium text-zinc-900 sm:font-normal sm:text-zinc-500" title="{{ $review->title }}">{{ $review->title }}</p>
                @include('review.partials.status-menu', ['class' => 'shrink-0'])
            </div>

            @if ($total > 0)
                <div class="hidden min-w-0 items-center gap-2 sm:flex sm:w-40 md:w-48">
                    <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ $verified }}/{{ $total }}</span>
                    <div class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-chip" role="progressbar" aria-valuenow="{{ $verified }}" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-label="Marks verified">
                        <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300 ease-out" style="width: {{ $verifiedPct }}%"></div>
                    </div>
                </div>
            @endif

            <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ $review->reviewUrl() }}" class="shrink-0 !bg-chip hover:!bg-chip-hover max-sm:!h-9">
                Review
            </flux:button>
        </div>

        @if ($total > 0)
            {{-- Below xl one column at a time: these say which, and how many in each. --}}
            <div class="overflow-x-auto px-3 pb-2 [scrollbar-width:none] sm:px-6 xl:hidden [&::-webkit-scrollbar]:hidden" role="tablist" aria-label="Columns">
                <div class="flex w-max gap-1 rounded-xl bg-chip p-0.5">
                    @foreach (\App\Models\Annotation::statusLabels() as $status => $label)
                        <button
                            type="button"
                            role="tab"
                            data-col-tab
                            class="flex h-9 items-center gap-1.5 rounded-[0.6rem] px-3 text-sm font-medium whitespace-nowrap transition-colors"
                            x-bind:class="inView({{ $loop->index }}) ? 'bg-raised text-zinc-900 shadow-xs ring-1 ring-black/[0.06]' : 'text-zinc-500 hover:text-zinc-900'"
                            x-bind:aria-selected="inView({{ $loop->index }}).toString()"
                            x-on:click="goTo({{ $loop->index }})"
                        >{{ $label }} <span class="tabular-nums text-zinc-400">{{ $grouped[$status]->count() }}</span></button>
                    @endforeach
                </div>
            </div>
        @endif
    </header>

    @if ($newer = $review->newestPass())
        <a
            href="{{ $newer->boardUrl() }}"
            data-newer-pass
            class="flex items-center justify-center gap-1.5 bg-key/15 px-4 py-2 text-sm font-medium text-zinc-900 transition-colors hover:bg-key/25"
        >Pass {{ $newer->pass }} is ready <flux:icon.arrow-right variant="micro" class="size-4" /></a>
    @endif

    @if (session('board_message'))
        <div class="mx-auto w-full max-w-7xl px-4 pt-3 sm:px-6">
            <flux:callout variant="secondary">{{ session('board_message') }}</flux:callout>
        </div>
    @endif

    <div class="mx-auto w-full max-w-7xl flex-1 py-4 sm:py-5">
        @if ($total === 0)
            <div class="px-4 sm:px-6">
                <div class="hatch rounded-2xl px-6 py-10 text-center text-zinc-300">
                    <p class="text-base font-semibold text-zinc-900">No marks yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">Marks you make on the review land here.</p>
                </div>
            </div>
        @else
            <div
                x-ref="track"
                x-on:scroll.debounce.50ms="syncActive()"
                x-on:pointerdown="onPanStart($event)"
                x-on:pointermove="onPanMove($event)"
                x-on:pointerup="onPanEnd($event)"
                x-on:pointercancel="onPanEnd($event)"
                class="flex cursor-grab snap-x snap-mandatory scroll-smooth gap-4 overflow-x-auto scroll-px-4 px-4 pb-2 sm:scroll-px-6 sm:px-6 [scrollbar-width:none] xl:grid xl:cursor-default xl:grid-cols-4 xl:overflow-visible xl:snap-none xl:scroll-px-0 xl:pb-0 [&::-webkit-scrollbar]:hidden"
            >
                @foreach (\App\Models\Annotation::statusLabels() as $status => $label)
                    @php($column = \App\Models\Annotation::boardColumnMeta()[$status])
                    <div
                        data-col
                        data-droppable="{{ $column['droppable'] ? 'true' : 'false' }}"
                        class="flex min-h-[8rem] w-[min(82vw,20rem)] shrink-0 snap-start flex-col rounded-2xl p-3 ring-2 ring-transparent transition sm:w-[min(60vw,20rem)] md:w-[min(45vw,20rem)] xl:w-auto xl:max-w-none xl:shrink bg-well"
                        @if ($column['droppable'])
                            x-on:dragover.prevent="$el.classList.remove('ring-transparent'); $el.classList.add('ring-key')"
                            x-on:dragleave="$el.classList.remove('ring-key'); $el.classList.add('ring-transparent')"
                            x-on:drop.prevent="$el.classList.remove('ring-key'); $el.classList.add('ring-transparent'); if (dragging !== null) { $wire.moveMark(dragging, '{{ $status }}'); dragging = null }"
                        @else
                            x-on:dragover.prevent
                            x-on:drop.prevent="dragging = null"
                        @endif
                    >
                        <div class="mb-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-start gap-2">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg {{ $column['icon_bg'] }}" aria-hidden="true">
                                        <flux:icon name="{{ $column['icon'] }}" variant="micro" class="size-4 {{ $column['icon_class'] }}" />
                                    </span>
                                    <div class="min-w-0">
                                        <flux:heading size="sm">{{ $label }}</flux:heading>
                                        <p class="mt-0.5 text-xs text-muted-foreground">{{ $column['owner'] }}</p>
                                    </div>
                                </div>
                                <span class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full bg-chip px-2 text-sm font-semibold tabular-nums text-zinc-700">{{ $grouped[$status]->count() }}</span>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-2">
                            @forelse ($grouped[$status] as $mark)
                                <div
                                    wire:key="board-mark-{{ $mark->id }}"
                                    role="button"
                                    tabindex="0"
                                    x-bind:draggable="(! coarse).toString()"
                                    x-on:pointerdown="didDrag = false"
                                    x-on:dragstart="didDrag = true; dragging = {{ $mark->id }}; $el.classList.add('opacity-50')"
                                    x-on:dragend="dragging = null; $el.classList.remove('opacity-50')"
                                    x-on:click="if (didDrag) { didDrag = false; return } $wire.openMark({{ $mark->id }})"
                                    x-on:keydown.enter.prevent="$wire.openMark({{ $mark->id }})"
                                    x-on:keydown.space.prevent="$wire.openMark({{ $mark->id }})"
                                    class="cursor-pointer rounded-xl bg-lift p-3 shadow-md shadow-black/[0.06] ring-1 ring-black/[0.07] transition-[translate,box-shadow] hover:-translate-y-px hover:shadow-lg active:cursor-grabbing"
                                >
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold {{ $mark->markerClass() }}">M{{ $mark->number }}</span>
                                        <span class="text-xs text-zinc-500">{{ $mark->label() }}</span>
                                        <x-signal-tag :tone="$mark->statusTone()">{{ $mark->statusLabel() }}</x-signal-tag>
                                        @if ($mark->changedByAgent())
                                            <x-agent-tag />
                                        @endif
                                        @if ($mark->comments->isNotEmpty())
                                            <span class="inline-flex items-center gap-1 rounded-md bg-chip px-1.5 py-0.5 text-xs font-medium tabular-nums text-zinc-600">
                                                <flux:icon name="chat-bubble-left-right" class="size-3" />
                                                {{ $mark->comments->count() }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($mark->target)
                                        <x-element-chip :target="$mark->target" class="mb-1.5" />
                                    @endif
                                    <p class="text-sm leading-relaxed text-zinc-700">{{ $mark->body }}</p>
                                    @if ($mark->resolution_note)
                                        <div class="mt-2 flex items-start gap-1.5 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900">
                                            <x-who-pill :actor="$mark->resolvedBy() ?? 'agent'" class="mt-px" /> <span>{{ $mark->resolution_note }}</span>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="hatch rounded-xl px-3 py-6 text-center text-xs text-zinc-300"><span class="rounded-md bg-well px-1.5 py-0.5 text-muted-foreground">{{ $column['empty'] }}</span></p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

        @endif
    </div>

    @php($selectedMark = $this->selectedMark)
    <flux:modal
        wire:model="showMarkSheet"
        variant="flyout"
        position="bottom"
        class="!max-h-[min(92dvh,52rem)] !p-0 lg:!max-h-[min(94dvh,56rem)]"
    >
        @if ($selectedMark)
            @php($shot = $selectedMark->screenshot)
            @php($markReview = $shot?->review)

            <div class="grid min-h-0 lg:grid-cols-[minmax(0,1fr)_minmax(17rem,22rem)] lg:items-stretch">
                {{-- Mini review: screenshot + mark context --}}
                <div class="flex min-w-0 flex-col gap-4 p-5 sm:p-6 lg:pr-7">
                    <div class="flex flex-wrap items-start justify-between gap-3 pr-10">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-xs font-semibold {{ $selectedMark->markerClass() }}">M{{ $selectedMark->number }}</span>
                                <span class="text-xs text-zinc-500">{{ $selectedMark->label() }}</span>
                                <x-signal-tag :tone="$selectedMark->statusTone()">{{ $selectedMark->statusLabel() }}</x-signal-tag>
                                @if ($selectedMark->changedByAgent())
                                    <x-agent-tag />
                                @endif
                            </div>
                            @if ($markReview)
                                <p class="mt-2 text-xs text-zinc-400">Pass {{ $markReview->pass }} · {{ $markReview->title }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="hidden flex-wrap items-center gap-2 lg:flex">
                                @include('review.partials.board-mark-actions')
                            </div>
                            <flux:button size="sm" variant="ghost" icon="eye" href="{{ $review->reviewUrl() }}{{ $shot?->review_id === $review->id ? '?mark='.$selectedMark->id : '' }}" class="!bg-chip hover:!bg-chip-hover">
                                On the review
                            </flux:button>
                        </div>
                    </div>

                    @unless ($shot)
                        <div class="hatch rounded-xl bg-well px-4 py-10 text-center text-sm text-muted-foreground">
                            No screenshot for this mark.
                        </div>
                    @endunless

                    <x-mark-detail :mark="$selectedMark" :context="$markReview?->context" />
                </div>

                {{-- What happened: every step and comment, who took it, and the composer at the bottom --}}
                <div class="flex min-h-0 flex-col bg-well p-5 sm:p-6 lg:h-full">
                    <div class="mb-3 flex shrink-0 items-center gap-2">
                        <flux:heading size="sm">What happened</flux:heading>
                        <span class="flex h-5 min-w-5 items-center justify-center rounded-md bg-chip px-1.5 text-[11px] font-medium tabular-nums text-zinc-600">{{ $selectedMark->comments->count() }}</span>
                    </div>

                    <div class="lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pr-1">
                        <x-mark-timeline :mark="$selectedMark" wire:key="mark-timeline-{{ $selectedMark->id }}-{{ $selectedMark->updated_at?->timestamp }}" />
                    </div>

                    @if ($review->allowsComments())
                        <div class="mt-4 shrink-0 space-y-2 lg:mt-auto">
                            <flux:textarea
                                wire:model="commentBody"
                                rows="3"
                                placeholder="Reply…"
                                x-on:keydown.meta.enter.prevent="$wire.addComment()"
                                x-on:keydown.ctrl.enter.prevent="$wire.addComment()"
                            />
                            <flux:error name="commentBody" />
                            <flux:button size="sm" variant="primary" icon="chat-bubble-left-ellipsis" wire:click="addComment" class="!h-8 w-full">
                                Reply
                            </flux:button>
                        </div>
                    @else
                        <p class="mt-4 shrink-0 text-center text-xs text-muted-foreground lg:mt-auto">
                            Comments are off, so only your agent can reply here. Turn them back on from Share on the review.
                        </p>
                    @endif
                </div>
            </div>

            @if ($review->allowsMarkManagement() && $selectedMark->severity !== \App\Models\Annotation::SEVERITY_KEEP && ($selectedMark->awaitsVerification() || $selectedMark->canOwnerResolve() || $selectedMark->status !== \App\Models\Annotation::STATUS_OPEN))
                <div data-sheet-actions class="material-chrome sticky bottom-0 flex items-center gap-2 border-t border-border px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden [&>*]:flex-1 [&>*]:justify-center">
                    @include('review.partials.board-mark-actions')
                </div>
            @endif
        @endif
    </flux:modal>
</div>
</div>
