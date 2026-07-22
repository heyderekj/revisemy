<?php

use App\Models\Annotation;
use App\Models\Review;
use App\Services\MarkLifecycleService;
use App\Support\FeedbackText;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $token;

    public Review $review;

    public bool $showMarkSheet = false;

    public ?int $selectedMarkId = null;

    public string $commentBody = '';

    public string $commentAuthor = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->loadBoard();
    }

    public function loadBoard(): void
    {
        // Owner token only — the board is an owner tool, not a guest view.
        $this->review = Review::query()
            ->where('token', $this->token)
            ->with(['screenshots.annotations.comments', 'screenshots.annotations.afterScreenshot', 'parent.screenshots.annotations.comments', 'parent.screenshots.annotations.afterScreenshot'])
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
        $this->resetValidation(['commentBody', 'commentAuthor']);
    }

    public function updatedShowMarkSheet(bool $open): void
    {
        if (! $open) {
            $this->selectedMarkId = null;
            $this->commentBody = '';
            $this->resetValidation(['commentBody', 'commentAuthor']);
        }
    }

    public function getSelectedMarkProperty(): ?Annotation
    {
        if (! $this->selectedMarkId) {
            return null;
        }

        return $this->ownedAnnotation($this->selectedMarkId)
            ?->loadMissing(['screenshot.review', 'afterScreenshot', 'comments']);
    }

    /**
     * Board comments are anonymous-friendly: blank name posts as Owner,
     * otherwise the typed name is stored (same spirit as guest links).
     */
    public function addComment(): void
    {
        if (! $this->review->allowsComments() || ! $this->selectedMarkId) {
            return;
        }

        $annotation = $this->ownedAnnotation($this->selectedMarkId);

        if (! $annotation) {
            return;
        }

        $this->commentBody = FeedbackText::sanitizeBody($this->commentBody);
        $this->commentAuthor = FeedbackText::sanitizeName($this->commentAuthor);

        $this->validate([
            'commentBody' => FeedbackText::bodyRules(),
            'commentAuthor' => FeedbackText::nameRules(required: false),
        ], FeedbackText::nameMessages('commentAuthor'));

        $author = $this->commentAuthor;

        $annotation->comments()->create([
            'author' => $author !== '' ? $author : 'Owner',
            'from_owner' => true,
            'body' => $this->commentBody,
        ]);

        $this->commentBody = '';
        $this->loadBoard();
    }

    public function moveMark(int $annotationId, string $status, MarkLifecycleService $lifecycle): void
    {
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

    protected function ownedAnnotation(int $annotationId): ?Annotation
    {
        $reviewIds = array_filter([$this->review->id, $this->review->parent_id]);

        return Annotation::query()
            ->whereKey($annotationId)
            ->whereHas('screenshot', fn ($q) => $q->whereIn('review_id', $reviewIds))
            ->first();
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
    class="flex min-h-svh flex-col"
    x-data="{
        dragging: null,
        didDrag: false,
        active: 0,
        pages: 0,
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

    <header class="shrink-0 border-b border-zinc-200/80 bg-zinc-50/90 backdrop-blur">
        <div class="mx-auto grid max-w-7xl grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 px-4 py-2.5 sm:flex sm:gap-4 sm:px-6">
            <div class="col-start-1 row-start-1 flex shrink-0 items-center gap-2 sm:gap-3">
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo size="sm" />
                </a>
                <h1 class="shrink-0 text-lg font-semibold tracking-tight text-zinc-900">Board</h1>
            </div>

            <div class="col-span-3 row-start-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 sm:col-span-1 sm:row-start-1 sm:flex-1 sm:flex-nowrap">
                <span class="inline-flex shrink-0 items-center rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600">
                    Pass {{ $review->pass }}
                </span>
                <span class="inline-flex shrink-0 items-center rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-sky-700" title="{{ $review->typeGuidance() }}">
                    {{ $review->typeLabel() }}
                </span>
                <span class="hidden text-zinc-300 sm:inline" aria-hidden="true">·</span>
                <p class="min-w-0 truncate text-sm text-zinc-500" title="{{ $review->title }}">{{ $review->title }}</p>
            </div>

            @if ($total > 0)
                <div class="hidden min-w-0 items-center gap-2 sm:flex sm:w-40 md:w-48">
                    <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ $verified }}/{{ $total }}</span>
                    <div class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-zinc-200/80" role="progressbar" aria-valuenow="{{ $verified }}" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-label="Marks verified">
                        <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300 ease-out" style="width: {{ $verifiedPct }}%"></div>
                    </div>
                </div>
            @endif

            <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ $review->reviewUrl() }}" class="col-start-3 row-start-1 shrink-0 justify-self-end !bg-zinc-100 hover:!bg-zinc-200/80">
                Review
            </flux:button>
        </div>
    </header>

    @if (session('board_message'))
        <div class="mx-auto w-full max-w-7xl px-4 pt-3 sm:px-6">
            <flux:callout variant="secondary">{{ session('board_message') }}</flux:callout>
        </div>
    @endif

    <div class="mx-auto w-full max-w-7xl flex-1 py-4 sm:py-5">
        @if ($total === 0)
            <div class="px-4 sm:px-6">
                <flux:callout>No marks yet. Add must-fix, nice-to-have, question, or keep marks on the review.</flux:callout>
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
                        class="flex min-h-[8rem] w-[min(82vw,20rem)] shrink-0 snap-start flex-col rounded-2xl border p-3 transition sm:w-[min(60vw,20rem)] md:w-[min(45vw,20rem)] xl:w-auto xl:max-w-none xl:shrink {{ $column['droppable'] ? 'border-zinc-200 bg-white/70' : 'border-dashed border-zinc-200/90 bg-zinc-50/80' }}"
                        @if ($column['droppable'])
                            x-on:dragover.prevent="$el.classList.remove('border-zinc-200'); $el.classList.add('border-rose-400')"
                            x-on:dragleave="$el.classList.remove('border-rose-400'); $el.classList.add('border-zinc-200')"
                            x-on:drop.prevent="$el.classList.remove('border-rose-400'); $el.classList.add('border-zinc-200'); if (dragging !== null) { $wire.moveMark(dragging, '{{ $status }}'); dragging = null }"
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
                                        <p class="mt-0.5 text-[11px] font-medium uppercase tracking-wide {{ $column['droppable'] ? 'text-zinc-400' : 'text-zinc-400/80' }}">{{ $column['owner'] }}</p>
                                    </div>
                                </div>
                                <span class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 px-2 text-sm font-semibold tabular-nums text-zinc-700">{{ $grouped[$status]->count() }}</span>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-2">
                            @forelse ($grouped[$status] as $mark)
                                <div
                                    wire:key="board-mark-{{ $mark->id }}"
                                    role="button"
                                    tabindex="0"
                                    draggable="true"
                                    x-on:dragstart="didDrag = true; dragging = {{ $mark->id }}; $el.classList.add('opacity-50')"
                                    x-on:dragend="dragging = null; $el.classList.remove('opacity-50')"
                                    x-on:click="if (didDrag) { didDrag = false; return } $wire.openMark({{ $mark->id }})"
                                    x-on:keydown.enter.prevent="$wire.openMark({{ $mark->id }})"
                                    x-on:keydown.space.prevent="$wire.openMark({{ $mark->id }})"
                                    class="cursor-pointer rounded-xl border border-zinc-200 bg-white p-3 shadow-sm transition hover:border-zinc-300 hover:shadow-md active:cursor-grabbing"
                                >
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold {{ $mark->markerClass() }}">M{{ $mark->number }}</span>
                                        <span class="text-xs text-zinc-500">{{ $mark->label() }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $mark->statusBadgeClass() }}">{{ $mark->statusLabel() }}</span>
                                        @if ($mark->comments->isNotEmpty())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600">
                                                <flux:icon name="chat-bubble-left-right" class="size-3" />
                                                {{ $mark->comments->count() }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm leading-relaxed text-zinc-700">{{ $mark->body }}</p>
                                    @if ($mark->resolution_note)
                                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900">
                                            <span class="font-medium">Agent:</span> {{ $mark->resolution_note }}
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-zinc-200 px-3 py-6 text-center text-xs text-zinc-400">{{ $column['empty'] }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            <div
                class="mt-4 flex items-center justify-center gap-3 px-4 sm:px-6 xl:hidden"
                x-show="pages > 1"
                x-cloak
            >
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-900 disabled:pointer-events-none disabled:opacity-35"
                    x-on:click="go(-1)"
                    x-bind:disabled="active <= 0"
                    aria-label="Previous columns"
                >
                    <flux:icon.chevron-left variant="micro" class="size-4" />
                </button>

                <div class="flex items-center gap-2">
                    <template x-for="i in pages" :key="i">
                        <button
                            type="button"
                            x-on:click="goTo(i - 1)"
                            class="h-2 rounded-full transition-all"
                            x-bind:class="active === i - 1 ? 'w-5 bg-rose-500' : 'w-2 bg-zinc-300 hover:bg-zinc-400'"
                            x-bind:aria-label="'Go to board page ' + i"
                        ></button>
                    </template>
                </div>

                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-900 disabled:pointer-events-none disabled:opacity-35"
                    x-on:click="go(1)"
                    x-bind:disabled="active >= pages - 1"
                    aria-label="Next columns"
                >
                    <flux:icon.chevron-right variant="micro" class="size-4" />
                </button>
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
                <div class="flex min-w-0 flex-col gap-4 border-zinc-100 p-5 sm:p-6 lg:border-r lg:pr-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-[11px] font-semibold {{ $selectedMark->markerClass() }}">M{{ $selectedMark->number }}</span>
                                <span class="text-xs text-zinc-500">{{ $selectedMark->label() }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $selectedMark->statusBadgeClass() }}">{{ $selectedMark->statusLabel() }}</span>
                            </div>
                            @if ($markReview)
                                <p class="mt-2 text-xs text-zinc-400">Pass {{ $markReview->pass }} · {{ $markReview->title }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button size="sm" variant="ghost" icon="eye" href="{{ $review->reviewUrl() }}" class="!bg-zinc-100 hover:!bg-zinc-200/80">
                                View on review
                            </flux:button>
                        </div>
                    </div>

                    @if ($shot)
                        <x-mark-focus-preview :mark="$selectedMark" />
                    @else
                        <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-10 text-center text-sm text-zinc-400">
                            No screenshot for this mark.
                        </div>
                    @endif

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
                        <dl class="space-y-4 text-sm">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Feedback</dt>
                                <dd class="mt-1 leading-relaxed text-pretty text-zinc-800 sm:text-[15px]">{{ $selectedMark->body }}</dd>
                            </div>
                            @if ($markReview?->context)
                                <div class="border-t border-zinc-100 pt-4">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">What to look at</dt>
                                    <dd class="mt-1 whitespace-pre-wrap text-pretty text-zinc-700">{{ $markReview->context }}</dd>
                                </div>
                            @endif
                            @if ($selectedMark->resolution_note)
                                <div class="border-t border-zinc-100 pt-4">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Agent note</dt>
                                    <dd class="mt-1 rounded-lg bg-emerald-50/80 px-3 py-2 text-emerald-950">{{ $selectedMark->resolution_note }}</dd>
                                </div>
                            @endif
                            @if ($selectedMark->afterScreenshot)
                                <div class="border-t border-zinc-100 pt-4">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Before / after</dt>
                                    <dd class="mt-1"><x-mark-before-after :mark="$selectedMark" /></dd>
                                </div>
                            @endif
                            @if ($selectedMark->resolved_at || $selectedMark->verified_at)
                                <div class="grid gap-3 border-t border-zinc-100 pt-4 sm:grid-cols-2">
                                    @if ($selectedMark->resolved_at)
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Resolved</dt>
                                            <dd class="mt-0.5 text-zinc-700">
                                                <time datetime="{{ $selectedMark->resolved_at->toIso8601String() }}" title="{{ $selectedMark->resolved_at->timezone(config('app.timezone'))->toDayDateTimeString() }}">
                                                    {{ $selectedMark->resolved_at->diffForHumans() }}
                                                </time>
                                            </dd>
                                        </div>
                                    @endif
                                    @if ($selectedMark->verified_at)
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Verified</dt>
                                            <dd class="mt-0.5 text-zinc-700">
                                                <time datetime="{{ $selectedMark->verified_at->toIso8601String() }}" title="{{ $selectedMark->verified_at->timezone(config('app.timezone'))->toDayDateTimeString() }}">
                                                    {{ $selectedMark->verified_at->diffForHumans() }}
                                                </time>
                                            </dd>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Comments column: stretch to left column height on desktop; composer sticks to bottom --}}
                <div class="flex min-h-0 flex-col border-t border-zinc-100 bg-zinc-50/60 p-5 sm:p-6 lg:h-full lg:border-t-0">
                    <div class="mb-3 flex shrink-0 items-center gap-2">
                        <flux:heading size="sm">Comments</flux:heading>
                        <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-zinc-100 px-1.5 text-[11px] font-medium tabular-nums text-zinc-600">{{ $selectedMark->comments->count() }}</span>
                    </div>

                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                        @forelse ($selectedMark->comments as $comment)
                            <div wire:key="mark-comment-{{ $comment->id }}" class="rounded-xl border border-zinc-200/80 bg-white px-3 py-2.5 shadow-sm">
                                <div class="mb-1 flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                                    <p class="text-xs font-medium text-zinc-800">
                                        {{ $comment->author }}
                                        @if ($comment->from_owner)
                                            <span class="ml-1 rounded-full bg-zinc-200/80 px-1.5 py-px text-[10px] font-medium text-zinc-600">Owner</span>
                                        @endif
                                    </p>
                                    <time
                                        class="text-[11px] text-zinc-400"
                                        datetime="{{ $comment->created_at->toIso8601String() }}"
                                        title="{{ $comment->created_at->timezone(config('app.timezone'))->toDayDateTimeString() }}"
                                    >{{ $comment->created_at->diffForHumans() }}</time>
                                </div>
                                <p class="text-sm leading-relaxed text-pretty text-zinc-700">{{ $comment->body }}</p>
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-zinc-200 bg-white/70 px-3 py-8 text-center text-xs text-zinc-400">
                                @if ($review->allowsComments())
                                    No comments yet. Share the guest link so teammates can weigh in.
                                @else
                                    Commenting is turned off for this review.
                                @endif
                            </p>
                        @endforelse
                    </div>

                    @if ($review->allowsComments())
                        <div class="mt-4 shrink-0 space-y-2 border-t border-zinc-200/80 pt-4 lg:mt-auto">
                            <flux:input
                                wire:model="commentAuthor"
                                placeholder="Your name (optional)"
                                maxlength="40"
                                size="sm"
                                x-data
                                x-init="if (! $wire.commentAuthor) { $wire.commentAuthor = localStorage.getItem('revisemy_guest_name') || '' }"
                                x-on:change="if ($event.target.value) localStorage.setItem('revisemy_guest_name', $event.target.value)"
                            />
                            <flux:textarea
                                wire:model="commentBody"
                                rows="3"
                                placeholder="Add context, a question, or a note…"
                            />
                            <flux:error name="commentBody" />
                            <flux:button size="sm" variant="primary" icon="chat-bubble-left-ellipsis" wire:click="addComment" class="!h-8 w-full">
                                Comment
                            </flux:button>
                        </div>
                    @else
                        <p class="mt-4 shrink-0 border-t border-zinc-200/80 pt-4 text-center text-xs text-zinc-400 lg:mt-auto">
                            Commenting is disabled. Turn it back on from Share on the review.
                        </p>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
