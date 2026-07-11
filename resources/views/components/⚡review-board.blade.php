<?php

use App\Models\Annotation;
use App\Models\Review;
use App\Services\MarkLifecycleService;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $token;

    public Review $review;

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
            ->with(['screenshots.annotations', 'parent.screenshots.annotations'])
            ->firstOrFail();
    }

    /**
     * Actionable marks from this pass and the one it built on, grouped by status.
     * Keeps are excluded — they are "leave alone" reminders, not board tasks.
     *
     * @return array<string, \Illuminate\Support\Collection<int, Annotation>>
     */
    public function marksByStatus(): array
    {
        $reviews = collect([$this->review, $this->review->parent])->filter();

        $marks = $reviews
            ->flatMap(fn (Review $review) => $review->screenshots->flatMap->annotations)
            ->reject(fn (Annotation $mark) => $mark->severity === Annotation::SEVERITY_KEEP)
            ->unique('id')
            ->sortBy('number')
            ->values();

        $grouped = [];

        foreach (array_keys(Annotation::statusLabels()) as $status) {
            $grouped[$status] = $marks->where('status', $status)->values();
        }

        return $grouped;
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
            default => session()->flash('board_message', 'Marks reach “'.(Annotation::statusLabels()[$status] ?? $status).'” when the agent works them (resolve_marks).'),
        };

        $this->loadBoard();
    }

    public function verifyMark(int $annotationId, MarkLifecycleService $lifecycle): void
    {
        $annotation = $this->ownedAnnotation($annotationId);

        if ($annotation) {
            $lifecycle->verify($annotation);
        }

        $this->loadBoard();
    }

    public function reopenMark(int $annotationId, MarkLifecycleService $lifecycle): void
    {
        $annotation = $this->ownedAnnotation($annotationId);

        if ($annotation) {
            $lifecycle->reopen($annotation);
        }

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
    class="flex min-h-svh flex-col bg-zinc-50"
    x-data="{
        dragging: null,
        active: 0,
        step() {
            const track = this.$refs.track;
            const col = track?.querySelector('[data-col]');
            if (! track || ! col) return 0;
            const gap = parseFloat(getComputedStyle(track).columnGap) || 16;
            return col.offsetWidth + gap;
        },
        colCount() {
            return this.$refs.track?.querySelectorAll('[data-col]').length || 0;
        },
        go(dir) {
            const track = this.$refs.track;
            const s = this.step();
            if (! track || ! s) return;
            const next = Math.max(0, Math.min(this.colCount() - 1, this.active + dir));
            track.scrollLeft = next * s;
            this.active = next;
        },
        goTo(i) {
            const track = this.$refs.track;
            const s = this.step();
            if (! track || ! s) return;
            track.scrollLeft = i * s;
            this.active = i;
        },
        syncActive() {
            const s = this.step();
            if (! s) return;
            this.active = Math.round(this.$refs.track.scrollLeft / s);
        },
        onArrow(e) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable) return;
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            const track = this.$refs.track;
            if (! track || track.scrollWidth <= track.clientWidth + 1) return;
            e.preventDefault();
            this.go(e.key === 'ArrowRight' ? 1 : -1);
        }
    }"
    x-on:keydown.window="onArrow($event)"
    wire:poll.20s="loadBoard"
>
    @php($grouped = $this->marksByStatus())
    @php($total = collect($grouped)->sum(fn ($c) => $c->count()))
    @php($verified = $grouped[\App\Models\Annotation::STATUS_VERIFIED]->count())

    <header class="shrink-0 border-b border-zinc-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex min-w-0 items-center gap-2 text-sm text-zinc-500">
                    <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                        <span class="font-mark text-[1.35rem] leading-none tracking-tight text-rose-500">ReviseMy</span>
                    </a>
                    <span class="shrink-0">/</span>
                    <span class="truncate">{{ $review->title }}</span>
                    <span class="shrink-0">/</span>
                    <span class="shrink-0 text-zinc-400">Board</span>
                </div>
                <p class="mt-1 text-xs text-zinc-500 sm:text-sm">
                    Pass {{ $review->pass }} — {{ $verified }}/{{ $total }} marks verified. Watch cards move as the agent resolves them.
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ $review->reviewUrl() }}" class="!bg-zinc-100 hover:!bg-zinc-200/80">
                    Back to review
                </flux:button>
            </div>
        </div>
    </header>

    @if (session('board_message'))
        <div class="mx-auto w-full max-w-7xl px-4 pt-3 sm:px-6">
            <flux:callout variant="secondary">{{ session('board_message') }}</flux:callout>
        </div>
    @endif

    <div class="mx-auto w-full max-w-7xl flex-1 px-4 py-4 sm:px-6 sm:py-6">
        @if ($total === 0)
            <flux:callout>No actionable marks yet. Add must-fix, tweak, question, or nit marks on the review, then request changes.</flux:callout>
        @else
            <div
                x-ref="track"
                x-on:scroll.debounce.50ms="syncActive()"
                class="flex snap-x snap-mandatory scroll-smooth gap-4 overflow-x-auto pb-2 [scrollbar-width:none] xl:grid xl:grid-cols-4 xl:overflow-visible xl:snap-none xl:pb-0 [&::-webkit-scrollbar]:hidden"
            >
                @foreach (\App\Models\Annotation::statusLabels() as $status => $label)
                    <div
                        data-col
                        class="flex min-h-[8rem] w-[82vw] max-w-[20rem] shrink-0 snap-start flex-col rounded-2xl border border-zinc-200 bg-white/70 p-3 transition sm:w-[60vw] md:w-[45vw] xl:w-auto xl:max-w-none xl:shrink"
                        x-on:dragover.prevent="$el.classList.add('ring-2','ring-rose-300')"
                        x-on:dragleave="$el.classList.remove('ring-2','ring-rose-300')"
                        x-on:drop.prevent="$el.classList.remove('ring-2','ring-rose-300'); if (dragging !== null) { $wire.moveMark(dragging, '{{ $status }}'); dragging = null }"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <flux:heading size="sm">{{ $label }}</flux:heading>
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-zinc-100 px-1.5 text-[11px] font-medium text-zinc-600">{{ $grouped[$status]->count() }}</span>
                        </div>

                        <div class="flex flex-1 flex-col gap-2">
                            @forelse ($grouped[$status] as $mark)
                                <div
                                    wire:key="board-mark-{{ $mark->id }}"
                                    draggable="true"
                                    x-on:dragstart="dragging = {{ $mark->id }}; $el.classList.add('opacity-50')"
                                    x-on:dragend="dragging = null; $el.classList.remove('opacity-50')"
                                    class="cursor-grab rounded-xl border border-zinc-200 bg-white p-3 shadow-sm active:cursor-grabbing"
                                >
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white {{ $mark->markerClass() }}">M{{ $mark->number }}</span>
                                        <span class="text-xs uppercase tracking-wide text-zinc-500">{{ $mark->label() }}</span>
                                    </div>
                                    <p class="text-sm leading-relaxed text-zinc-700">{{ $mark->body }}</p>
                                    @if ($mark->resolution_note)
                                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900">
                                            <span class="font-medium">Agent:</span> {{ $mark->resolution_note }}
                                        </div>
                                    @endif
                                    <div class="mt-2 flex items-center gap-2">
                                        @if ($mark->awaitsVerification())
                                            <button type="button" class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white transition hover:bg-emerald-500" wire:click="verifyMark({{ $mark->id }})">Verify</button>
                                        @endif
                                        @if ($mark->status !== \App\Models\Annotation::STATUS_OPEN)
                                            <button type="button" class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-200" wire:click="reopenMark({{ $mark->id }})">Reopen</button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-zinc-200 px-3 py-6 text-center text-xs text-zinc-400">Drop a mark here</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-center gap-2 xl:hidden">
                @foreach (\App\Models\Annotation::statusLabels() as $status => $label)
                    <button
                        type="button"
                        x-on:click="goTo({{ $loop->index }})"
                        class="h-2 rounded-full transition-all"
                        x-bind:class="active === {{ $loop->index }} ? 'w-5 bg-rose-500' : 'w-2 bg-zinc-300 hover:bg-zinc-400'"
                        aria-label="Go to {{ $label }} column"
                    ></button>
                @endforeach
            </div>
        @endif
    </div>
</div>
