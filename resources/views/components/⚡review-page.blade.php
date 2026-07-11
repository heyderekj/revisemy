<?php

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Review;
use App\Models\Screenshot;
use App\Services\SecondOpinionService;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $token;

    #[Locked]
    public string $mode = 'owner';

    public Review $review;

    public string $guestName = '';

    public int $activeScreenshotIndex = 0;

    public string $draftBody = '';

    public string $draftSeverity = Annotation::SEVERITY_MUST_FIX;

    public ?float $pendingX = null;

    public ?float $pendingY = null;

    public ?float $pendingW = null;

    public ?float $pendingH = null;

    public string $decisionNote = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->loadReview();
    }

    public function loadReview(): void
    {
        $review = Review::query()
            ->where(fn ($q) => $q->where('token', $this->token)->orWhere('share_token', $this->token))
            ->with(['screenshots.annotations', 'screenshots.findings'])
            ->firstOrFail();

        $this->mode = hash_equals((string) $review->token, $this->token) ? 'owner' : 'guest';
        $this->review = $review;
    }

    public function isOwner(): bool
    {
        return $this->mode === 'owner';
    }

    public function showDecisionNote(): bool
    {
        return $this->review->isOpenForFeedback() && $this->isOwner();
    }

    public function showDecisionCallout(): bool
    {
        return (bool) $this->review->decision_note && ! $this->showDecisionNote();
    }

    public function showStatusCallout(): bool
    {
        return in_array($this->review->effectiveStatus(), ['changes_requested', 'approved'], true);
    }

    public function selectScreenshot(int $index): void
    {
        $this->activeScreenshotIndex = $index;
        $this->cancelPin();
    }

    public function startPin(float $x, float $y, ?float $w = null, ?float $h = null): void
    {
        if (! $this->review->isOpenForFeedback()) {
            return;
        }

        $x = max(0, min(1, $x));
        $y = max(0, min(1, $y));
        $w = $w !== null ? max(0, min(1 - $x, $w)) : null;
        $h = $h !== null ? max(0, min(1 - $y, $h)) : null;

        $hasRegion = $w !== null && $h !== null && $w >= 0.01 && $h >= 0.01;

        $this->pendingX = $hasRegion ? $x + ($w / 2) : $x;
        $this->pendingY = $hasRegion ? $y + ($h / 2) : $y;
        $this->pendingW = $hasRegion ? $w : null;
        $this->pendingH = $hasRegion ? $h : null;
        $this->draftBody = '';
        $this->draftSeverity = Annotation::SEVERITY_MUST_FIX;
    }

    public function cancelPin(): void
    {
        $this->pendingX = null;
        $this->pendingY = null;
        $this->pendingW = null;
        $this->pendingH = null;
        $this->draftBody = '';
    }

    public function savePin(): void
    {
        if (! $this->review->isOpenForFeedback() || $this->pendingX === null || $this->pendingY === null) {
            return;
        }

        $rules = [
            'draftBody' => ['required', 'string', 'max:2000'],
            'draftSeverity' => ['required', 'in:'.implode(',', Annotation::severities())],
        ];

        if (! $this->isOwner()) {
            $rules['guestName'] = ['required', 'string', 'max:40'];
        }

        $this->validate($rules, [
            'draftBody.required' => 'Leave a note on this spot.',
            'guestName.required' => 'Add your name so the owner knows who suggested this.',
        ]);

        $screenshot = $this->review->screenshots->values()->get($this->activeScreenshotIndex);

        if (! $screenshot) {
            return;
        }

        $hasRegion = $this->pendingW !== null && $this->pendingH !== null
            && $this->pendingW >= 0.01 && $this->pendingH >= 0.01;

        $area = $hasRegion ? [
            'x' => max(0, min(1, $this->pendingX - ($this->pendingW / 2))),
            'y' => max(0, min(1, $this->pendingY - ($this->pendingH / 2))),
            'w' => $this->pendingW,
            'h' => $this->pendingH,
        ] : null;

        if ($area) {
            $area['x'] = max(0, min(1 - $area['w'], $area['x']));
            $area['y'] = max(0, min(1 - $area['h'], $area['y']));
        }

        if ($this->isOwner()) {
            $number = ((int) $screenshot->annotations()->max('number')) + 1;

            $screenshot->annotations()->create([
                'x' => $this->pendingX,
                'y' => $this->pendingY,
                'area' => $area,
                'severity' => $this->draftSeverity,
                'body' => $this->draftBody,
                'number' => $number,
            ]);
        } else {
            $screenshot->findings()->create([
                'source' => Finding::SOURCE_GUEST,
                'author' => trim($this->guestName),
                'severity' => $this->draftSeverity,
                'body' => $this->draftBody,
                'x' => $this->pendingX,
                'y' => $this->pendingY,
                'area' => $area,
                'status' => Finding::STATUS_OPEN,
            ]);
        }

        $this->cancelPin();
        $this->loadReview();
    }

    public function deletePin(int $annotationId): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $annotation = Annotation::query()
            ->whereKey($annotationId)
            ->whereHas('screenshot', fn ($q) => $q->where('review_id', $this->review->id))
            ->first();

        $annotation?->delete();
        $this->loadReview();
    }

    public function acceptFinding(int $findingId): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $finding = Finding::query()
            ->whereKey($findingId)
            ->whereHas('screenshot', fn ($q) => $q->where('review_id', $this->review->id))
            ->first();

        if (! $finding || ! $finding->isOpen()) {
            return;
        }

        $screenshot = $finding->screenshot;
        $number = ((int) $screenshot->annotations()->max('number')) + 1;
        $area = is_array($finding->area) ? $finding->area : null;
        $hasRegion = is_array($area)
            && (float) ($area['w'] ?? 0) >= 0.01
            && (float) ($area['h'] ?? 0) >= 0.01;

        $x = $finding->x !== null
            ? (float) $finding->x
            : ($hasRegion ? (float) $area['x'] + ((float) $area['w'] / 2) : 0.5);
        $y = $finding->y !== null
            ? (float) $finding->y
            : ($hasRegion ? (float) $area['y'] + ((float) $area['h'] / 2) : 0.5);

        $pin = $screenshot->annotations()->create([
            'x' => max(0, min(1, $x)),
            'y' => max(0, min(1, $y)),
            'area' => $hasRegion ? [
                'x' => (float) $area['x'],
                'y' => (float) $area['y'],
                'w' => (float) $area['w'],
                'h' => (float) $area['h'],
            ] : null,
            'severity' => $finding->pinSeverity(),
            'body' => $finding->body,
            'number' => $number,
        ]);

        $finding->update([
            'status' => Finding::STATUS_ACCEPTED,
            'related_pin' => $pin->number,
        ]);

        $this->loadReview();
    }

    public function dismissFinding(int $findingId): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $finding = Finding::query()
            ->whereKey($findingId)
            ->whereHas('screenshot', fn ($q) => $q->where('review_id', $this->review->id))
            ->first();

        if (! $finding || ! $finding->isOpen()) {
            return;
        }

        $finding->update(['status' => Finding::STATUS_DISMISSED]);
        $this->loadReview();
    }

    public function refreshSecondOpinion(SecondOpinionService $opinions): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $opinions->requestForReview($this->review, $this->activeScreenshotIndex);
        $this->loadReview();
    }

    public function regenerateShareToken(): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $this->review->regenerateShareToken();
        $this->loadReview();
    }

    public function approve(): void
    {
        $this->decide(Review::STATUS_APPROVED);
    }

    public function requestChanges(): void
    {
        $this->decide(Review::STATUS_CHANGES_REQUESTED);
    }

    protected function decide(string $status): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->validate([
            'decisionNote' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->review->update([
            'status' => $status,
            'decision_note' => $this->decisionNote ?: null,
            'decision_at' => now(),
        ]);

        $this->loadReview();
    }

    public function getActiveScreenshotProperty()
    {
        return $this->review->screenshots->values()->get($this->activeScreenshotIndex);
    }

    public function getOpinionPendingProperty(): bool
    {
        return $this->review->screenshots->contains(
            fn (Screenshot $shot) => $shot->second_opinion_status === Screenshot::OPINION_QUEUED
        );
    }
};
?>

<div
    class="flex h-svh max-h-svh flex-col overflow-hidden"
    @if ($this->opinionPending)
        wire:poll.3s="loadReview"
    @endif
>
    <header class="shrink-0 border-b border-zinc-200 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:gap-4">
            <div class="min-w-0">
                <div class="flex min-w-0 items-center gap-2 text-sm text-zinc-500">
                    <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                        <span class="font-mark text-[1.35rem] leading-none tracking-tight text-rose-500 dark:text-rose-400">ReviseMy</span>
                    </a>
                    <span class="shrink-0">/</span>
                    <span class="truncate">{{ $review->title }}</span>
                </div>
                <p class="mt-1 text-xs text-zinc-500 sm:text-sm">
                    @if ($review->effectiveStatus() === 'pending')
                        <span class="sm:hidden">Pass {{ $review->pass }} — mark, then decide</span>
                        <span class="hidden sm:inline">Pass {{ $review->pass }} — mark feedback, then approve or request changes</span>
                    @elseif ($review->effectiveStatus() === 'changes_requested')
                        <span class="sm:hidden">Changes requested — next pass needed</span>
                        <span class="hidden sm:inline">Changes requested — agent should apply your marks and open the next pass</span>
                    @elseif ($review->effectiveStatus() === 'approved')
                        Looks good — approved (pass {{ $review->pass }})
                    @else
                        This review link expired
                    @endif
                </p>
            </div>

            @if ($mode === 'guest')
                <div class="flex shrink-0 items-center">
                    <span class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-800 sm:px-3 sm:text-xs dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <span class="sm:hidden">Guest mode</span>
                        <span class="hidden sm:inline">Guest — your marks are suggestions until accepted</span>
                    </span>
                </div>
            @elseif ($review->isOpenForFeedback())
                <div class="flex w-full flex-wrap items-center gap-2 lg:w-auto lg:justify-end">
                    <div class="min-w-0 flex-1 sm:flex-none" x-data="{ copied: false, shareUrl: {{ \Illuminate\Support\Js::from($review->shareUrl()) }} }">
                        <flux:button
                            variant="ghost"
                            icon="link"
                            class="w-full !bg-zinc-100 hover:!bg-zinc-200/80 sm:w-auto"
                            x-on:click="navigator.clipboard.writeText(shareUrl); copied = true; setTimeout(() => copied = false, 2000)"
                        >
                            <span x-show="! copied">Share</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </flux:button>
                    </div>
                    <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="requestChanges" class="min-w-0 flex-1 !bg-zinc-100 hover:!bg-zinc-200/80 sm:flex-none">Changes</flux:button>
                    <flux:button variant="primary" icon="check" wire:click="approve" class="min-w-0 flex-1 !bg-rose-600 hover:!bg-rose-700 sm:flex-none">Approve</flux:button>
                </div>
            @endif
        </div>
    </header>

    <div
        class="mx-auto grid min-h-0 w-full max-w-7xl flex-1 grid-cols-1 gap-4 overflow-y-auto px-4 pt-4 sm:gap-5 sm:px-6 sm:pt-5 xl:grid-cols-[minmax(0,1fr)_20rem] xl:grid-rows-[auto_1fr] xl:gap-x-6 xl:gap-y-4 xl:overflow-hidden xl:pt-6"
        x-data
        x-init="
            if (! Alpine.store('rmFocus')) {
                Alpine.store('rmFocus', { finding: null });
            }
        "
    >
        <section class="min-w-0 space-y-3 sm:space-y-4 xl:col-start-1 xl:row-start-1 xl:min-h-0 xl:overflow-y-auto">
            @if ($review->context)
                <div class="grid gap-1.5 sm:grid-cols-[9.5rem_1fr] sm:gap-4">
                    <flux:heading size="sm" class="sm:pt-0.5">What to look at</flux:heading>
                    <p class="text-sm leading-relaxed text-pretty text-zinc-600 sm:text-base dark:text-zinc-400">
                        {{ $review->context }}
                    </p>
                </div>
            @endif

            @if ($review->screenshots->count() > 1)
                <div class="flex flex-wrap gap-2">
                    @foreach ($review->screenshots as $index => $shotOption)
                        <flux:button
                            size="sm"
                            variant="{{ $activeScreenshotIndex === $index ? 'primary' : 'ghost' }}"
                            wire:click="selectScreenshot({{ $index }})"
                            x-on:click="$store.rmFocus && ($store.rmFocus.finding = null)"
                            class="{{ $activeScreenshotIndex === $index ? '!border-zinc-800 !bg-zinc-800 !text-white hover:!bg-zinc-700' : '' }}"
                        >
                            Shot {{ $index + 1 }}
                        </flux:button>
                    @endforeach
                </div>
            @endif

            @php($shot = $this->activeScreenshot)

            @if ($shot)
                <div
                    wire:key="shot-viewer-{{ $activeScreenshotIndex }}"
                    class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    x-data="{
                        zoom: 1,
                        zoomMin: 1,
                        zoomMax: 3,
                        zoomStep: 0.25,
                        drawing: false,
                        panning: false,
                        spaceHeld: false,
                        panStartX: 0,
                        panStartY: 0,
                        panScrollLeft: 0,
                        panScrollTop: 0,
                        startX: 0,
                        startY: 0,
                        draft: null,
                        zoomIn() {
                            this.zoom = Math.min(this.zoomMax, +(this.zoom + this.zoomStep).toFixed(2));
                        },
                        zoomOut() {
                            this.zoom = Math.max(this.zoomMin, +(this.zoom - this.zoomStep).toFixed(2));
                        },
                        resetZoom() {
                            this.zoom = 1;
                            this.$refs.viewport?.scrollTo({ top: 0, left: 0, behavior: 'instant' });
                        },
                        onKeyDown(e) {
                            if (e.code !== 'Space') return;
                            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable) return;
                            this.spaceHeld = true;
                            if (this.zoom > 1) e.preventDefault();
                        },
                        onKeyUp(e) {
                            if (e.code !== 'Space') return;
                            this.spaceHeld = false;
                            this.panning = false;
                        },
                        isPanMode(e) {
                            return e.button === 1 || (this.spaceHeld && e.button === 0 && this.zoom > 1);
                        },
                        beginPan(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const vp = this.$refs.viewport;
                            if (! vp) return;
                            this.panning = true;
                            this.panStartX = e.clientX;
                            this.panStartY = e.clientY;
                            this.panScrollLeft = vp.scrollLeft;
                            this.panScrollTop = vp.scrollTop;
                        },
                        movePan(e) {
                            if (! this.panning) return;
                            const vp = this.$refs.viewport;
                            if (! vp) return;
                            vp.scrollLeft = this.panScrollLeft - (e.clientX - this.panStartX);
                            vp.scrollTop = this.panScrollTop - (e.clientY - this.panStartY);
                        },
                        endPan() {
                            this.panning = false;
                        },
                        scrollParentEl() {
                            let node = this.$refs.viewport;
                            while (node) {
                                node = node.parentElement;
                                if (! node) {
                                    return null;
                                }
                                const { overflowY } = getComputedStyle(node);
                                if (/(auto|scroll)/.test(overflowY) && node.scrollHeight > node.clientHeight + 1) {
                                    return node;
                                }
                            }
                            return null;
                        },
                        onWheel(e) {
                            if (this.zoom > 1) return;

                            const vp = this.$refs.viewport;
                            const parent = this.scrollParentEl();
                            if (! vp || ! parent) return;

                            const hasOverflow = vp.scrollHeight > vp.clientHeight + 1;

                            if (! hasOverflow) {
                                parent.scrollTop += e.deltaY;
                                e.preventDefault();
                                return;
                            }

                            const atTop = vp.scrollTop <= 0;
                            const atBottom = vp.scrollTop + vp.clientHeight >= vp.scrollHeight - 1;

                            if ((e.deltaY > 0 && atBottom) || (e.deltaY < 0 && atTop)) {
                                parent.scrollTop += e.deltaY;
                                e.preventDefault();
                            }
                        },
                        norm(e) {
                            const canvas = this.$refs.canvas;
                            if (! canvas) return null;
                            const rect = canvas.getBoundingClientRect();
                            return {
                                x: Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)),
                                y: Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height)),
                            };
                        },
                        onMouseMove(e) {
                            if (this.panning) {
                                this.movePan(e);
                                return;
                            }
                            this.moveDraw(e);
                        },
                        onMouseUp(e) {
                            if (this.panning) {
                                this.endPan();
                                return;
                            }
                            this.endDraw(e);
                        },
                        beginDraw(e) {
                            if (this.isPanMode(e)) {
                                this.beginPan(e);
                                return;
                            }
                            if (e.button !== 0) return;
                            if (e.target.closest('[data-finding], [data-pin], [data-zoom-controls]')) return;
                            const p = this.norm(e);
                            if (! p) return;
                            this.drawing = true;
                            this.startX = p.x;
                            this.startY = p.y;
                            this.draft = { x: p.x, y: p.y, w: 0, h: 0 };
                            e.preventDefault();
                        },
                        moveDraw(e) {
                            if (! this.drawing) return;
                            const p = this.norm(e);
                            if (! p) return;
                            const x = Math.min(this.startX, p.x);
                            const y = Math.min(this.startY, p.y);
                            this.draft = {
                                x,
                                y,
                                w: Math.abs(p.x - this.startX),
                                h: Math.abs(p.y - this.startY),
                            };
                        },
                        endDraw(e) {
                            if (! this.drawing) return;
                            this.drawing = false;
                            const draft = this.draft;
                            this.draft = null;
                            if (! draft) return;
                            if (draft.w >= 0.01 && draft.h >= 0.01) {
                                $wire.startPin(draft.x, draft.y, draft.w, draft.h);
                            } else {
                                $wire.startPin(this.startX, this.startY);
                            }
                        },
                        cancelDraw() {
                            this.drawing = false;
                            this.draft = null;
                            this.panning = false;
                        }
                    }"
                    x-on:keydown.window="onKeyDown($event)"
                    x-on:keyup.window="onKeyUp($event)"
                >
                    <div data-zoom-controls class="absolute left-2 top-2 z-30 flex items-center gap-0.5 rounded-lg border border-zinc-200/80 bg-white/95 p-0.5 shadow-sm backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            x-on:click="zoomOut()"
                            x-bind:disabled="zoom <= zoomMin"
                            aria-label="Zoom out"
                        >−</button>
                        <button
                            type="button"
                            class="min-w-[2.75rem] rounded-md px-1.5 py-1 font-mono text-[10px] text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                            x-on:click="resetZoom()"
                            x-text="Math.round(zoom * 100) + '%'"
                            aria-label="Reset zoom"
                        ></button>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            x-on:click="zoomIn()"
                            x-bind:disabled="zoom >= zoomMax"
                            aria-label="Zoom in"
                        >+</button>
                    </div>

                    <div
                        x-ref="viewport"
                        class="max-h-[min(52svh,420px)] sm:max-h-[min(65svh,520px)] lg:max-h-[min(70svh,560px)]"
                        x-bind:class="{
                            'overflow-auto overscroll-contain': zoom > 1,
                            'overflow-y-auto': zoom <= 1,
                            'cursor-grab': zoom > 1 && spaceHeld && !panning,
                            'cursor-grabbing': panning
                        }"
                        x-on:wheel="onWheel($event)"
                        x-on:mousedown="isPanMode($event) && beginPan($event)"
                        x-on:mousemove.window="onMouseMove($event)"
                        x-on:mouseup.window="onMouseUp($event)"
                    >
                        <div
                            x-ref="canvas"
                            class="relative min-w-full select-none"
                            x-bind:style="'width: ' + (zoom * 100) + '%'"
                            @if ($review->isOpenForFeedback())
                                x-bind:class="!spaceHeld ? 'cursor-crosshair' : ''"
                                x-on:mousedown="beginDraw($event)"
                                x-on:keydown.escape.window="cancelDraw()"
                            @endif
                        >
                            <img
                                src="{{ $shot->url() }}"
                                alt="Screenshot {{ $activeScreenshotIndex + 1 }}"
                                class="pointer-events-none block w-full"
                                draggable="false"
                            />

                            @php($openFindings = $shot->findings->filter(fn ($f) => $f->isOpen() && ! $f->isGuest())->values())
                            @php($guestFindings = $shot->findings->filter(fn ($f) => $f->isOpen() && $f->isGuest())->values())
                            @foreach ($openFindings as $findingIndex => $finding)
                                @php($area = is_array($finding->area) ? $finding->area : null)
                                @if ($area)
                                    @php($badgePosition = ($area['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($area['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <div
                                        data-finding
                                        class="absolute z-[5]"
                                        style="left: {{ $area['x'] * 100 }}%; top: {{ $area['y'] * 100 }}%; width: {{ $area['w'] * 100 }}%; height: {{ $area['h'] * 100 }}%;"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-sky-400/80 bg-sky-400/10 transition"
                                            title="{{ $finding->body }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-sky-500 bg-sky-400/20 ring-2 ring-sky-400/40' : ''"
                                        ></div>
                                        <button
                                            type="button"
                                            class="absolute {{ $badgePosition }} z-[6] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-0.5 text-[10px] font-semibold text-sky-700 shadow-sm transition"
                                            title="{{ $finding->body }}"
                                            x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-sky-600 bg-sky-50 ring-2 ring-sky-300' : ''"
                                        >
                                            S{{ $findingIndex + 1 }}
                                        </button>
                                    </div>
                                @endif
                            @endforeach

                            @foreach ($guestFindings as $guestIndex => $finding)
                                @php($area = is_array($finding->area) ? $finding->area : null)
                                @php($hasRegion = $area && (float) ($area['w'] ?? 0) >= 0.01 && (float) ($area['h'] ?? 0) >= 0.01)
                                @if ($hasRegion)
                                    @php($badgePosition = ($area['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($area['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <div
                                        data-finding
                                        class="absolute z-[7]"
                                        style="left: {{ $area['x'] * 100 }}%; top: {{ $area['y'] * 100 }}%; width: {{ $area['w'] * 100 }}%; height: {{ $area['h'] * 100 }}%;"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-amber-400/80 bg-amber-400/10 transition"
                                            title="{{ $finding->body }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-amber-500 bg-amber-400/20 ring-2 ring-amber-400/40' : ''"
                                        ></div>
                                        <button
                                            type="button"
                                            class="absolute {{ $badgePosition }} z-[8] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-white px-0.5 text-[10px] font-semibold text-amber-700 shadow-sm transition"
                                            title="{{ $finding->body }}"
                                            x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-amber-600 bg-amber-50 ring-2 ring-amber-300' : ''"
                                        >
                                            G{{ $guestIndex + 1 }}
                                        </button>
                                    </div>
                                @elseif ($finding->x !== null && $finding->y !== null)
                                    <button
                                        type="button"
                                        data-finding
                                        class="absolute z-[7] flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-white px-0.5 text-[10px] font-semibold text-amber-700 shadow-sm transition"
                                        style="left: {{ $finding->x * 100 }}%; top: {{ $finding->y * 100 }}%;"
                                        title="{{ $finding->body }}"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                        x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                        x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-amber-600 bg-amber-50 ring-2 ring-amber-300' : ''"
                                    >
                                        G{{ $guestIndex + 1 }}
                                    </button>
                                @endif
                            @endforeach

                            @foreach ($shot->annotations as $annotation)
                                @php($region = $annotation->region())
                                @if ($region)
                                    @php($markBadgePosition = ($region['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($region['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <div
                                        data-pin
                                        class="absolute z-[8]"
                                        style="left: {{ $region['x'] * 100 }}%; top: {{ $region['y'] * 100 }}%; width: {{ $region['w'] * 100 }}%; height: {{ $region['h'] * 100 }}%;"
                                    >
                                        <div class="pointer-events-none absolute inset-0 rounded-md border-2 border-rose-500/80 bg-rose-500/10"></div>
                                        <div class="pointer-events-none absolute {{ $markBadgePosition }} z-[9] flex h-6 min-w-6 items-center justify-center rounded-full px-0.5 text-[10px] font-semibold text-white shadow-sm ring-2 ring-white {{ $annotation->markerClass() }}">
                                            M{{ $annotation->number }}
                                        </div>
                                    </div>
                                @else
                                    <button
                                        type="button"
                                        data-pin
                                        class="absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white shadow-lg ring-2 ring-white {{ $annotation->markerClass() }}"
                                        style="left: {{ $annotation->x * 100 }}%; top: {{ $annotation->y * 100 }}%;"
                                        title="{{ $annotation->body }}"
                                    >
                                        M{{ $annotation->number }}
                                    </button>
                                @endif
                            @endforeach

                            <template x-if="draft && draft.w > 0 && draft.h > 0">
                                <div
                                    class="pointer-events-none absolute z-[15] rounded-md border-2 border-dashed border-rose-500 bg-rose-500/15"
                                    x-bind:style="'left:' + (draft.x * 100) + '%;top:' + (draft.y * 100) + '%;width:' + (draft.w * 100) + '%;height:' + (draft.h * 100) + '%'"
                                ></div>
                            </template>

                            @if ($pendingX !== null && $pendingY !== null)
                                @if ($pendingW !== null && $pendingH !== null && $pendingW >= 0.01 && $pendingH >= 0.01)
                                    <div
                                        class="pointer-events-none absolute z-[18] rounded-md border-2 border-rose-500 bg-rose-500/15"
                                        style="left: {{ ($pendingX - $pendingW / 2) * 100 }}%; top: {{ ($pendingY - $pendingH / 2) * 100 }}%; width: {{ $pendingW * 100 }}%; height: {{ $pendingH * 100 }}%;"
                                    ></div>
                                @endif
                                <div
                                    class="absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-rose-500 text-xs font-semibold text-white shadow-lg ring-2 ring-white"
                                    style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                                >
                                    +
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($review->isOpenForFeedback())
                    <div class="rounded-xl border border-zinc-200/80 bg-zinc-50/90 px-3 py-2.5 sm:px-4">
                        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-[11px] text-zinc-600 sm:gap-x-4 sm:text-xs">
                            <span class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border-2 border-rose-500/80 bg-rose-500/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 text-[8px] font-semibold text-white ring-2 ring-white">M</span>
                                </span>
                                Your marks
                            </span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 sm:block" aria-hidden="true"></span>
                            <span class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-sky-400/80 bg-sky-400/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-sky-500 bg-white text-[8px] font-semibold text-sky-700 ring-2 ring-white">S</span>
                                </span>
                                Second opinion
                            </span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 sm:block" aria-hidden="true"></span>
                            <span class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-amber-400/80 bg-amber-400/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-amber-500 bg-white text-[8px] font-semibold text-amber-700 ring-2 ring-white">G</span>
                                </span>
                                Guest
                            </span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 md:block" aria-hidden="true"></span>
                            <span class="w-full text-center text-zinc-500 sm:w-auto">{{ $mode === 'guest' ? 'Drag to suggest · click for point' : 'Drag to mark · click for point' }}</span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 md:block" aria-hidden="true"></span>
                            <span class="hidden text-zinc-500 sm:inline">+/− zoom · Space+drag to pan</span>
                        </div>
                    </div>
                @endif
            @else
                <flux:callout variant="warning">No screenshots on this review yet.</flux:callout>
            @endif

            @if ($pendingX !== null)
                <div class="rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="sm" class="mb-3">
                        @if ($pendingW !== null && $pendingH !== null && $pendingW >= 0.01 && $pendingH >= 0.01)
                            {{ $mode === 'guest' ? 'Suggest a change on this region' : 'Leave a note on this region' }}
                        @else
                            {{ $mode === 'guest' ? 'Suggest a change on this spot' : 'Leave a note on this spot' }}
                        @endif
                    </flux:heading>
                    <div class="space-y-3">
                        @if ($mode === 'guest')
                            <flux:input
                                wire:model="guestName"
                                placeholder="Your name"
                                maxlength="40"
                                x-data
                                x-init="if (! $wire.guestName) { $wire.guestName = localStorage.getItem('revisemy_guest_name') || '' }"
                                x-on:change="localStorage.setItem('revisemy_guest_name', $event.target.value)"
                            />
                        @endif
                        <flux:textarea wire:model="draftBody" rows="3" placeholder="Be specific — what feels off, and what would be better?" />
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Models\Annotation::severityLabels() as $value => $label)
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-sm has-[:checked]:border-zinc-400 has-[:checked]:bg-white has-[:checked]:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                                    <input type="radio" wire:model="draftSeverity" value="{{ $value }}" class="{{ \App\Models\Annotation::accentClass($value) }}">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            @if ($mode === 'guest')
                                <flux:button variant="primary" icon="chat-bubble-left-ellipsis" wire:click="savePin" class="w-full !bg-amber-600 hover:!bg-amber-700 sm:w-auto">Suggest</flux:button>
                            @else
                                <flux:button variant="primary" icon="pencil-square" wire:click="savePin" class="w-full !bg-rose-600 sm:w-auto">Save mark</flux:button>
                            @endif
                            <flux:button variant="ghost" icon="x-mark" wire:click="cancelPin" class="w-full sm:w-auto">Cancel</flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <aside class="flex min-w-0 flex-col border-t border-zinc-200 pt-4 dark:border-zinc-800 xl:col-start-2 xl:row-span-2 xl:row-start-1 xl:min-h-0 xl:border-t-0 xl:overflow-y-auto xl:pt-0">
            <div class="space-y-4 pb-4 xl:pb-6">
            <div class="rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="sm" class="mb-3">{{ $mode === 'owner' ? 'My marks' : 'Owner marks' }}</flux:heading>

                @php($pins = $shot?->annotations ?? collect())

                @if ($pins->isEmpty())
                    <p class="text-sm text-zinc-500">
                        {{ $mode === 'owner' ? 'No marks yet. Drag to outline a region, or click for a point.' : 'The owner has not marked anything yet.' }}
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($pins as $pin)
                            <li class="rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
                                        <span class="text-xs uppercase tracking-wide text-zinc-500">{{ $pin->label() }}</span>
                                    </div>
                                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                                        <button type="button" class="text-xs text-zinc-400 hover:text-rose-600" wire:click="deletePin({{ $pin->id }})">Remove</button>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $pin->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-2xl border border-sky-200/80 bg-sky-50/50 p-3 shadow-sm sm:p-4 dark:border-sky-900 dark:bg-sky-950/30">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <flux:heading size="sm">Second opinion</flux:heading>
                        <p class="mt-1 text-xs leading-snug text-zinc-500">Hints until you accept — then they become your marks</p>
                    </div>
                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-path"
                            wire:click="refreshSecondOpinion"
                            wire:loading.attr="disabled"
                            class="shrink-0 [&_[data-flux-loading-indicator]]:rounded-md [&_[data-flux-loading-indicator]]:bg-sky-100/95 dark:[&_[data-flux-loading-indicator]]:bg-sky-950/90"
                        >
                            Refresh
                        </flux:button>
                    @endif
                </div>

                @php($findings = ($shot?->findings ?? collect())->filter(fn ($f) => $f->isOpen() && ! $f->isGuest())->values())
                @php($status = $shot?->second_opinion_status ?? 'idle')

                @if ($status === 'queued')
                    <p class="text-sm text-sky-700 dark:text-sky-300">Running on Laravel Cloud…</p>
                @elseif ($status === 'failed')
                    <p class="text-sm text-rose-600">Second opinion failed{{ $shot?->second_opinion_error ? ': '.$shot->second_opinion_error : '.' }}</p>
                @elseif ($findings->isEmpty())
                    <p class="text-sm text-zinc-500">No open suggestions. Accept ones you want as marks, dismiss the rest, or refresh for a new pass.</p>
                @else
                    <div class="mb-2 flex items-center justify-between gap-2" x-show="$store.rmFocus?.finding" x-cloak>
                        <p class="text-xs text-sky-700">Focused on one hint</p>
                        <button
                            type="button"
                            class="text-xs font-medium text-sky-700 hover:text-sky-900"
                            x-on:click="$store.rmFocus.finding = null"
                        >Show all</button>
                    </div>
                    <ul class="space-y-3">
                        @foreach ($findings as $findingIndex => $finding)
                            <li
                                class="cursor-pointer rounded-xl border bg-white/80 p-3 transition dark:bg-zinc-900/60"
                                x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                x-on:click="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                x-bind:class="$store.rmFocus?.finding === {{ $finding->id }}
                                    ? 'border-sky-400 ring-2 ring-sky-300/60 dark:border-sky-500'
                                    : 'border-sky-100 dark:border-sky-900'"
                            >
                                <div class="mb-1 flex items-start gap-2">
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-sky-500 text-[10px] font-semibold text-sky-700">S{{ $findingIndex + 1 }}</span>
                                        <span class="text-xs uppercase tracking-wide text-zinc-500">{{ $finding->severity }}</span>
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-medium text-sky-800 dark:bg-sky-900 dark:text-sky-200">{{ $finding->sourceLabel() }}</span>
                                    </div>
                                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                                        <div class="flex shrink-0 items-center gap-1" x-on:click.stop>
                                            <button
                                                type="button"
                                                wire:click="acceptFinding({{ $finding->id }})"
                                                title="Accept as mark"
                                                aria-label="Accept as mark"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-sky-600 text-white transition hover:bg-sky-500"
                                            >
                                                <flux:icon.check variant="micro" class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="dismissFinding({{ $finding->id }})"
                                                title="Dismiss"
                                                aria-label="Dismiss"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                            >
                                                <flux:icon.x-mark variant="micro" class="size-3.5" />
                                            </button>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $finding->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-2xl border border-amber-200/80 bg-amber-50/50 p-3 shadow-sm sm:p-4 dark:border-amber-900 dark:bg-amber-950/30">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <flux:heading size="sm">Guest feedback</flux:heading>
                        <p class="mt-1 text-xs leading-snug text-zinc-500">
                            {{ $mode === 'owner' ? 'Teammate suggestions — accept to make them your marks' : 'Suggestions from you and other guests' }}
                        </p>
                    </div>
                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="regenerateShareToken"
                            wire:confirm="Regenerate the guest link? Anyone with the old link loses access."
                            class="shrink-0"
                        >
                            New link
                        </flux:button>
                    @endif
                </div>

                @php($guestSuggestions = ($shot?->findings ?? collect())->filter(fn ($f) => $f->isOpen() && $f->isGuest())->values())

                @if ($guestSuggestions->isEmpty())
                    <p class="text-sm text-zinc-500">
                        {{ $mode === 'owner' ? 'No guest suggestions yet. Copy the guest link (top right) and send it to a teammate.' : 'No suggestions yet. Drag or click on the screenshot to add one.' }}
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($guestSuggestions as $guestIndex => $finding)
                            <li
                                class="cursor-pointer rounded-xl border bg-white/80 p-3 transition dark:bg-zinc-900/60"
                                x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                x-on:click="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                x-bind:class="$store.rmFocus?.finding === {{ $finding->id }}
                                    ? 'border-amber-400 ring-2 ring-amber-300/60 dark:border-amber-500'
                                    : 'border-amber-100 dark:border-amber-900'"
                            >
                                <div class="mb-1 flex items-start gap-2">
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-amber-500 text-[10px] font-semibold text-amber-700">G{{ $guestIndex + 1 }}</span>
                                        <span class="text-xs uppercase tracking-wide text-zinc-500">{{ \App\Models\Annotation::severityLabels()[$finding->severity] ?? $finding->severity }}</span>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">{{ $finding->sourceLabel() }}</span>
                                    </div>
                                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                                        <div class="flex shrink-0 items-center gap-1" x-on:click.stop>
                                            <button
                                                type="button"
                                                wire:click="acceptFinding({{ $finding->id }})"
                                                title="Accept as mark"
                                                aria-label="Accept as mark"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-amber-600 text-white transition hover:bg-amber-500"
                                            >
                                                <flux:icon.check variant="micro" class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="dismissFinding({{ $finding->id }})"
                                                title="Dismiss"
                                                aria-label="Dismiss"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                            >
                                                <flux:icon.x-mark variant="micro" class="size-3.5" />
                                            </button>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $finding->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            </div>
        </aside>

        @if ($this->showDecisionNote() || $this->showDecisionCallout() || $this->showStatusCallout())
            <div class="min-w-0 space-y-3 border-t border-zinc-200 pt-4 pb-6 dark:border-zinc-800 xl:col-start-1 xl:row-start-2 xl:border-t-0 xl:pt-0">
                @if ($this->showDecisionNote())
                    <div class="rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="sm" class="mb-3">Overall note (optional)</flux:heading>
                        <flux:textarea wire:model="decisionNote" rows="2" placeholder="Anything else before you approve or request changes?" />
                    </div>
                @elseif ($this->showDecisionCallout())
                    <flux:callout>
                        <strong class="font-medium">Note to the agent:</strong> {{ $review->decision_note }}
                    </flux:callout>
                @endif

                @if ($review->effectiveStatus() === 'changes_requested')
                    <flux:callout>
                        <strong class="font-medium">What’s next:</strong>
                        The agent should apply your marks, then open a new checkup pass with fresh screenshots (linked to this review). You’ll get another link to approve.
                    </flux:callout>
                @elseif ($review->effectiveStatus() === 'approved')
                    <flux:callout>
                        <strong class="font-medium">Loop complete for this pass.</strong>
                        Ask the agent for another checkup anytime if the UI changes again.
                    </flux:callout>
                @endif
            </div>
        @endif
    </div>
</div>
