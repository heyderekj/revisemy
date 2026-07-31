        <section class="min-h-0 min-w-0 shrink-0 max-h-[52svh] space-y-3 overflow-y-auto overscroll-contain pt-4 sm:space-y-4 sm:pt-5 md:max-h-none md:overflow-hidden md:pt-6">
            @if ($review->context || ($this->isOwner() && $review->isOpenForFeedback()))
                <div class="grid gap-1.5 py-3 sm:grid-cols-[9.5rem_1fr] sm:gap-4 sm:py-4">
                    <flux:heading size="sm" class="sm:pt-0.5">What to look at</flux:heading>

                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            @if ($editingContext && $this->isOwner() && $review->isOpenForFeedback())
                                <textarea
                                    wire:key="context-editor"
                                    wire:model="contextDraft"
                                    rows="3"
                                    maxlength="5000"
                                    placeholder="What should they look at on this pass?"
                                    class="w-full resize-y border-0 bg-transparent p-0 text-sm leading-relaxed text-pretty text-zinc-600 outline-none ring-0 placeholder:text-zinc-400 sm:text-base"
                                    x-data
                                    x-init="$el.focus()"
                                    x-on:keydown.meta.enter.prevent="$wire.saveContext()"
                                    x-on:keydown.ctrl.enter.prevent="$wire.saveContext()"
                                    x-on:keydown.escape.prevent="$wire.cancelEditContext()"
                                    x-on:blur="$wire.blurSaveContext()"
                                ></textarea>
                            @elseif ($this->isOwner() && $review->isOpenForFeedback())
                                <button
                                    type="button"
                                    wire:click="startEditContext"
                                    class="w-full text-left transition hover:text-zinc-800"
                                    title="Click to edit"
                                >
                                    @if ($review->context)
                                        <p class="text-sm leading-relaxed text-pretty text-zinc-600 sm:text-base">
                                            {{ $review->context }}
                                        </p>
                                    @else
                                        <p class="text-sm text-zinc-400 sm:text-base">
                                            Add what to look at on this pass…
                                        </p>
                                    @endif
                                </button>
                            @elseif ($review->context)
                                <p class="text-sm leading-relaxed text-pretty text-zinc-600 sm:text-base">
                                    {{ $review->context }}
                                </p>
                            @endif
                        </div>

                        @if ($this->isOwner() && $review->isOpenForFeedback())
                            <div class="flex w-14 shrink-0 items-center justify-end gap-0.5 pt-0.5">
                                @if ($editingContext)
                                    <button
                                        type="button"
                                        wire:click="saveContext"
                                        x-on:mousedown.prevent
                                        class="inline-flex size-6 items-center justify-center rounded-md text-rose-600 transition hover:bg-rose-50"
                                        aria-label="Save"
                                        title="Save · ⌘Enter"
                                    >
                                        <flux:icon.check variant="micro" class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="cancelEditContext"
                                        x-on:mousedown.prevent
                                        class="inline-flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                                        aria-label="Cancel"
                                        title="Cancel · Esc"
                                    >
                                        <flux:icon.x-mark variant="micro" class="size-3.5" />
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div @class(['sm:flex sm:items-start sm:gap-3' => $review->screenshots->count() > 1])>

            @if ($review->screenshots->count() > 1)
                <div
                    class="mb-2 flex gap-2 overflow-x-auto p-1 sm:mb-0 sm:max-h-[min(70svh,560px)] sm:w-24 sm:shrink-0 sm:flex-col sm:overflow-y-auto sm:overflow-x-visible"
                    role="tablist"
                    aria-label="Screenshots"
                    aria-orientation="vertical"
                >
                    @foreach ($review->screenshots as $index => $shotOption)
                        <button
                            type="button"
                            role="tab"
                            wire:key="rail-{{ $shotOption->id }}"
                            wire:click="selectScreenshot({{ $index }})"
                            x-on:click="$store.rmFocus && ($store.rmFocus.finding = null, $store.rmFocus.mark = null)"
                            aria-label="{{ $shotOption->railLabel($index) }}"
                            aria-selected="{{ $activeScreenshotIndex === $index ? 'true' : 'false' }}"
                            title="{{ $shotOption->railLabel($index) }}"
                            @class([
                                'group relative w-16 shrink-0 overflow-hidden rounded-xl transition duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 active:scale-[0.97] sm:w-full',
                                'shadow-sm ring-2 ring-rose-500 ring-offset-2' => $activeScreenshotIndex === $index,
                                'opacity-80 ring-1 ring-zinc-200 hover:opacity-100 hover:ring-zinc-300' => $activeScreenshotIndex !== $index,
                            ])
                        >
                            <img
                                src="{{ $shotOption->thumbUrl() }}"
                                alt=""
                                loading="lazy"
                                draggable="false"
                                class="pointer-events-none block aspect-[4/5] w-full bg-zinc-100 object-cover object-top"
                            />
                            <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-zinc-900/70 to-transparent px-1.5 pb-1 pt-4 text-left text-[10px] font-medium text-white">
                                {{ $shotOption->railLabel($index) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="min-w-0 flex-1">

            @if ($shot)
                <div
                    wire:key="shot-viewer-{{ $activeScreenshotIndex }}"
                    class="min-w-0"
                    x-data="{
                        zoom: 1,
                        zoomMin: 1,
                        zoomMax: 3,
                        zoomStep: 0.25,
                        naturalWidth: {{ (int) ($shot->width ?: 0) }},
                        naturalHeight: {{ (int) ($shot->height ?: 0) }},
                        baseWidth: 0,
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
                        resizeObserver: null,
                        init() {
                            this.$nextTick(() => {
                                this.measureLayout();
                                const img = this.$refs.shotImg;
                                if (img?.complete && img.naturalWidth) {
                                    this.naturalWidth = img.naturalWidth;
                                    this.naturalHeight = img.naturalHeight;
                                    this.measureLayout();
                                }
                                this.resizeObserver = new ResizeObserver(() => this.measureLayout());
                                if (this.$refs.viewport) {
                                    this.resizeObserver.observe(this.$refs.viewport);
                                }
                            });
                        },
                        destroy() {
                            this.resizeObserver?.disconnect();
                        },
                        onImageLoad() {
                            const img = this.$refs.shotImg;
                            if (! img) return;
                            this.naturalWidth = img.naturalWidth;
                            this.naturalHeight = img.naturalHeight;
                            this.measureLayout();
                        },
                        measureLayout() {
                            const vp = this.$refs.viewport;
                            if (! vp) return;
                            const containerWidth = vp.clientWidth;
                            const natural = this.naturalWidth || containerWidth;
                            this.baseWidth = natural > 0 ? Math.min(containerWidth, natural) : containerWidth;
                        },
                        canvasStyle() {
                            // Width only — height must come from the image so mark %
                            // coords share the same box as the pixels (no aspect-ratio
                            // on the wrapper; that can diverge from the decoded image).
                            if (! this.baseWidth) {
                                return 'max-width: 100%; width: 100%';
                            }

                            const w = Math.max(1, Math.round(this.baseWidth * this.zoom));

                            return 'width: ' + w + 'px';
                        },
                        viewportCanScrollX() {
                            const vp = this.$refs.viewport;
                            return !!(vp && vp.scrollWidth > vp.clientWidth + 1);
                        },
                        viewportCanScrollY() {
                            const vp = this.$refs.viewport;
                            return !!(vp && vp.scrollHeight > vp.clientHeight + 1);
                        },
                        viewportCanScroll() {
                            return this.viewportCanScrollX() || this.viewportCanScrollY();
                        },
                        canPan() {
                            return this.viewportCanScroll();
                        },
                        wheelAxisCanScroll(e) {
                            const absX = Math.abs(e.deltaX);
                            const absY = Math.abs(e.deltaY);
                            if (absX > absY) {
                                return this.viewportCanScrollX();
                            }
                            if (absY > 0) {
                                return this.viewportCanScrollY();
                            }

                            return this.viewportCanScroll();
                        },
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
                            if (this.canPan()) e.preventDefault();
                        },
                        onKeyUp(e) {
                            if (e.code !== 'Space') return;
                            this.spaceHeld = false;
                            this.panning = false;
                        },
                        isPanMode(e) {
                            return e.button === 1 || (this.spaceHeld && e.button === 0 && this.canPan());
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
                            // Prefer scrolling the capture when that axis can scroll;
                            // otherwise let (or forward) the wheel to the page/column.
                            if (this.wheelAxisCanScroll(e)) {
                                e.stopPropagation();
                                return;
                            }

                            const parent = this.scrollParentEl();
                            if (! parent) return;

                            parent.scrollTop += e.deltaY;
                            e.preventDefault();
                        },
                        norm(e) {
                            // Normalize against the image box, not the wrapper — a flex-
                            // stretched canvas can be shorter than the bitmap and inflate Y.
                            const target = this.$refs.shotImg || this.$refs.canvas;
                            if (! target) return null;
                            const rect = target.getBoundingClientRect();
                            if (rect.width < 1 || rect.height < 1) return null;
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
                    x-init="init(); return () => destroy()"
                    x-on:keydown.window="onKeyDown($event)"
                    x-on:keyup.window="onKeyUp($event)"
                >
                    <div @class([
                        'relative overflow-hidden border border-zinc-200 bg-zinc-100',
                        'rounded-t-2xl' => $review->isOpenForFeedback(),
                        'rounded-2xl' => ! $review->isOpenForFeedback(),
                    ])>
                        <div data-zoom-controls class="absolute bottom-2 left-2 z-30 flex items-center gap-0.5 rounded-lg border border-zinc-200/80 bg-white/95 p-0.5 shadow-sm backdrop-blur">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-md text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
                                x-on:click="zoomOut()"
                                x-bind:disabled="zoom <= zoomMin"
                                aria-label="Zoom out"
                            >−</button>
                            <button
                                type="button"
                                class="min-w-[2.75rem] rounded-md px-1.5 py-1 font-mono text-[10px] text-zinc-500 transition hover:bg-zinc-100"
                                x-on:click="resetZoom()"
                                x-text="Math.round(zoom * 100) + '%'"
                                aria-label="Reset zoom"
                            ></button>
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-md text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
                                x-on:click="zoomIn()"
                                x-bind:disabled="zoom >= zoomMax"
                                aria-label="Zoom in"
                            >+</button>
                        </div>

                    <div
                        x-ref="viewport"
                        class="relative max-h-[min(52svh,420px)] overflow-auto overscroll-contain sm:max-h-[min(65svh,520px)] lg:max-h-[min(70svh,560px)]"
                        x-bind:class="{
                            'cursor-grab': canPan() && spaceHeld && !panning,
                            'cursor-grabbing': panning
                        }"
                        x-on:wheel="onWheel($event)"
                        x-on:mousedown="isPanMode($event) && beginPan($event)"
                        x-on:mousemove.window="onMouseMove($event)"
                        x-on:mouseup.window="onMouseUp($event)"
                    >
                        <div
                            x-ref="canvas"
                            class="relative mx-auto max-w-full select-none"
                            x-bind:style="canvasStyle()"
                            @if ($review->isOpenForFeedback())
                                x-bind:class="(zoom > 1 ? '!max-w-none ' : '') + (! spaceHeld ? 'cursor-crosshair' : '')"
                                x-on:mousedown="beginDraw($event)"
                                x-on:keydown.escape.window="cancelDraw()"
                            @else
                                x-bind:class="zoom > 1 ? '!max-w-none' : ''"
                            @endif
                        >
                            <img
                                x-ref="shotImg"
                                src="{{ $shot->url() }}"
                                alt="Screenshot {{ $activeScreenshotIndex + 1 }}"
                                @if ($shot->width) width="{{ $shot->width }}" @endif
                                @if ($shot->height) height="{{ $shot->height }}" @endif
                                class="pointer-events-none block h-auto w-full max-w-full"
                                draggable="false"
                                x-on:load="onImageLoad()"
                            />

                            @php($openFindings = $this->openSecondOpinion)
                            @php($guestFindings = $this->openGuestSuggestions)
                            @php($textOnlyGuest = $guestFindings->filter(fn ($f) => ! $f->hasRegion() && ($f->x === null || $f->y === null))->values())
                            @foreach ($openFindings as $findingIndex => $finding)
                                @php($area = $finding->region())
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
                                            S{{ $suggestionNumbers['s'][$finding->id] ?? ($findingIndex + 1) }}
                                        </button>
                                    </div>
                                @endif
                            @endforeach

                            @foreach ($guestFindings as $guestIndex => $finding)
                                @php($area = $finding->region())
                                @if ($area)
                                    @php($badgePosition = ($area['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($area['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <div
                                        data-finding
                                        class="absolute z-[7]"
                                        style="left: {{ $area['x'] * 100 }}%; top: {{ $area['y'] * 100 }}%; width: {{ $area['w'] * 100 }}%; height: {{ $area['h'] * 100 }}%;"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-zinc-400/80 bg-zinc-400/10 transition"
                                            title="{{ $finding->body }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-zinc-500 bg-zinc-400/20 ring-2 ring-zinc-400/40' : ''"
                                        ></div>
                                        <button
                                            type="button"
                                            class="absolute {{ $badgePosition }} z-[8] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-zinc-500 bg-white px-0.5 text-[10px] font-semibold text-zinc-700 shadow-sm transition"
                                            title="{{ $finding->body }}"
                                            x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-zinc-600 bg-zinc-50 ring-2 ring-zinc-300' : ''"
                                        >
                                            G{{ $suggestionNumbers['g'][$finding->id] ?? ($guestIndex + 1) }}
                                        </button>
                                    </div>
                                @elseif ($finding->x !== null && $finding->y !== null)
                                    <button
                                        type="button"
                                        data-finding
                                        class="absolute z-[7] flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-zinc-500 bg-white px-0.5 text-[10px] font-semibold text-zinc-700 shadow-sm transition"
                                        style="left: {{ $finding->x * 100 }}%; top: {{ $finding->y * 100 }}%;"
                                        title="{{ $finding->body }}"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                        x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                        x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-zinc-600 bg-zinc-50 ring-2 ring-zinc-300' : ''"
                                    >
                                        G{{ $suggestionNumbers['g'][$finding->id] ?? ($guestIndex + 1) }}
                                    </button>
                                @endif
                            @endforeach

                            @if ($textOnlyGuest->isNotEmpty())
                                <div
                                    class="pointer-events-none absolute right-2 top-2 z-[12] flex max-w-[min(100%,12rem)] flex-col items-end gap-1"
                                    aria-label="Guest text hints on capture"
                                >
                                    @foreach ($textOnlyGuest as $finding)
                                        <button
                                            type="button"
                                            data-finding
                                            class="pointer-events-auto flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-zinc-500 bg-white px-0.5 text-[10px] font-semibold text-zinc-700 shadow-sm transition"
                                            title="{{ $finding->body }}"
                                            x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                            x-on:click.stop="
                                                $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                                $store.rmFocus.mark = null;
                                                document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                            "
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-zinc-600 bg-zinc-50 ring-2 ring-zinc-300' : ''"
                                        >
                                            G{{ $suggestionNumbers['g'][$finding->id] ?? ($loop->iteration) }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @foreach ($shot->annotations as $annotation)
                                @php($region = $annotation->region())
                                @php($markState = $annotation->status === \App\Models\Annotation::STATUS_VERIFIED ? 'opacity-40' : ($annotation->status === \App\Models\Annotation::STATUS_RESOLVED ? 'opacity-70' : ''))
                                @if ($region)
                                    @php($markBadgePosition = ($region['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($region['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <button
                                        type="button"
                                        data-pin
                                        class="absolute z-[8] block cursor-pointer border-0 bg-transparent p-0 text-left {{ $markState }}"
                                        style="left: {{ $region['x'] * 100 }}%; top: {{ $region['y'] * 100 }}%; width: {{ $region['w'] * 100 }}%; height: {{ $region['h'] * 100 }}%;"
                                        title="{{ $annotation->body }}"
                                        x-on:click.stop="
                                            $store.rmFocus.mark = $store.rmFocus.mark === {{ $annotation->id }} ? null : {{ $annotation->id }};
                                            $store.rmFocus.finding = null;
                                            document.getElementById('fb-mark-{{ $annotation->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                        "
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border-2 border-rose-500/80 bg-rose-500/10 transition"
                                            x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'ring-2 ring-rose-400 ring-offset-1' : ''"
                                        ></div>
                                        <span
                                            class="pointer-events-none absolute {{ $markBadgePosition }} z-[9] flex h-6 min-w-6 items-center justify-center rounded-full px-0.5 text-[10px] font-semibold shadow-sm ring-2 ring-white transition {{ $annotation->markerClass() }}"
                                            x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'scale-110' : ''"
                                        >
                                            M{{ $annotation->number }}
                                        </span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        data-pin
                                        class="absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-[10px] font-semibold shadow-lg ring-2 ring-white transition {{ $annotation->markerClass() }} {{ $markState }}"
                                        style="left: {{ $annotation->x * 100 }}%; top: {{ $annotation->y * 100 }}%;"
                                        title="{{ $annotation->body }}"
                                        x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'scale-110 ring-4 ring-rose-300' : ''"
                                    >
                                        M{{ $annotation->number }}
                                    </button>
                                @endif
                            @endforeach

                            <template x-if="draft && draft.w > 0 && draft.h > 0">
                                <div
                                    class="pointer-events-none absolute z-[15] rounded-md border-2 border-dashed {{ $mode === 'guest' ? 'border-zinc-400 bg-zinc-400/15' : 'border-rose-500 bg-rose-500/15' }}"
                                    x-bind:style="'left:' + (draft.x * 100) + '%;top:' + (draft.y * 100) + '%;width:' + (draft.w * 100) + '%;height:' + (draft.h * 100) + '%'"
                                ></div>
                            </template>

                            @if ($pendingX !== null && $pendingY !== null)
                                @if ($pendingW !== null && $pendingH !== null && $pendingW >= 0.01 && $pendingH >= 0.01)
                                    <div
                                        data-pending-mark
                                        class="pointer-events-none absolute z-[18] rounded-md border-2 border-dashed {{ $mode === 'guest' ? 'border-zinc-400 bg-zinc-400/15' : 'border-rose-500 bg-rose-500/15' }}"
                                        style="left: {{ ($pendingX - $pendingW / 2) * 100 }}%; top: {{ ($pendingY - $pendingH / 2) * 100 }}%; width: {{ $pendingW * 100 }}%; height: {{ $pendingH * 100 }}%;"
                                    ></div>
                                @else
                                    <div
                                        data-pending-mark
                                        class="pointer-events-none absolute z-[18] h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full {{ $mode === 'guest' ? 'bg-zinc-500' : 'bg-rose-500' }}"
                                        style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                                    ></div>
                                @endif
                                <div
                                    class="absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full text-xs font-semibold shadow-lg ring-2 ring-white {{ $mode === 'guest' ? 'border-2 border-dashed border-zinc-400 bg-white text-zinc-700' : 'bg-rose-500 text-accent-contrast' }}"
                                    style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                                >
                                    {{ $mode === 'guest' ? 'G' : '+' }}
                                </div>
                            @endif
                        </div>
                    </div>
                    </div>

                @if ($review->isOpenForFeedback())
                    <div class="rounded-b-2xl border-x border-b border-zinc-200/80 bg-zinc-50/90 px-3 py-2.5 sm:px-4 sm:py-3">
                        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[11px] text-zinc-600 sm:gap-x-6 sm:text-xs">
                            <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1.5 sm:gap-x-4">
                                <span class="inline-flex items-center gap-2">
                                    <span class="relative h-4 w-7 shrink-0 rounded border-2 border-rose-500/80 bg-rose-500/10" aria-hidden="true">
                                        <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 text-[8px] font-semibold text-accent-contrast ring-2 ring-white">M</span>
                                    </span>
                                    Your marks
                                </span>
                                @if ($mode === 'owner')
                                <span class="h-3 w-px shrink-0 bg-zinc-300" aria-hidden="true"></span>
                                <span class="inline-flex items-center gap-2">
                                    <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-sky-400/80 bg-sky-400/10" aria-hidden="true">
                                        <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-sky-500 bg-white text-[8px] font-semibold text-sky-700 ring-2 ring-white">S</span>
                                    </span>
                                    Second opinion
                                </span>
                                @endif
                                <span class="h-3 w-px shrink-0 bg-zinc-300" aria-hidden="true"></span>
                                <span class="inline-flex items-center gap-2">
                                    <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-zinc-400/80 bg-zinc-400/10" aria-hidden="true">
                                        <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-zinc-500 bg-white text-[8px] font-semibold text-zinc-700 ring-2 ring-white">G</span>
                                    </span>
                                    Guest
                                </span>
                            </div>

                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 sm:block" aria-hidden="true"></span>

                            <div class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-zinc-500">
                                <span>{{ $mode === 'guest' ? 'Drag to suggest · click for point' : 'Drag to mark · click for point' }}</span>
                                <span class="hidden h-3 w-px shrink-0 bg-zinc-300 sm:block" aria-hidden="true"></span>
                                <span>Space+drag to pan</span>
                            </div>
                        </div>
                    </div>
                @endif
                </div>
            @else
                <flux:callout variant="warning">No screenshots on this review yet.</flux:callout>
            @endif

            </div>
            </div>
        </section>

        @php($stripMarks = $this->activeMarks)
        @php($stripSecondOpinion = $this->openSecondOpinion->filter(fn ($f) => $f->hasRegion())->values())
        @php($stripGuest = $this->openGuestSuggestions->filter(
            fn ($f) => $f->hasRegion() || ($f->x !== null && $f->y !== null)
        )->values())

        @if ($shot && ($stripMarks->isNotEmpty() || $stripSecondOpinion->isNotEmpty() || $stripGuest->isNotEmpty()))
            <div class="shrink-0 overflow-x-auto overscroll-x-contain border-y border-zinc-200/80 bg-zinc-50/90 px-3 py-2 [scrollbar-width:none] md:hidden sm:px-4 [&::-webkit-scrollbar]:hidden">
                <div class="flex w-max items-center gap-x-3 gap-y-2 text-[11px] text-zinc-600">
                    @if ($stripMarks->isNotEmpty())
                        <div class="inline-flex items-center gap-2">
                            <span class="relative h-4 w-7 shrink-0 rounded border-2 border-rose-500/80 bg-rose-500/10" aria-hidden="true">
                                <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 text-[8px] font-semibold text-accent-contrast ring-2 ring-white">M</span>
                            </span>
                            <span class="sr-only">Your marks</span>
                            <div class="flex items-center gap-1">
                                @foreach ($stripMarks as $pin)
                                    <button
                                        type="button"
                                        class="flex h-6 min-w-6 shrink-0 items-center justify-center bg-accent px-1 text-[10px] font-semibold text-ink ring-1 ring-zinc-200/80 transition hover:ring-zinc-300"
                                        x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} ? 'ring-2 ring-rose-400' : ''"
                                        x-on:click="
                                            $store.rmFocus.mark = $store.rmFocus.mark === {{ $pin->id }} ? null : {{ $pin->id }};
                                            $store.rmFocus.finding = null;
                                            document.getElementById('fb-mark-{{ $pin->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                        "
                                        aria-label="Jump to mark M{{ $pin->number }}"
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
                                        class="flex h-6 min-w-6 shrink-0 items-center justify-center border border-dashed border-sky-500 bg-white px-1 text-[10px] font-semibold text-sky-700 transition hover:bg-sky-50"
                                        x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-sky-600 bg-sky-50 ring-2 ring-sky-300' : ''"
                                        x-on:click="
                                            $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                            $store.rmFocus.mark = null;
                                            @if ($secondOpinionSourceTab !== 'all' && (
                                                ($secondOpinionSourceTab === 'checklist' && ! $finding->isChecklistSource())
                                                || ($secondOpinionSourceTab === 'vision' && ! $finding->isVisionSource())
                                            ))
                                                $wire.setSecondOpinionSourceTab('all').then(() => document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                                            @elseif ($secondOpinionTab !== 'all' && $secondOpinionTab !== $finding->severity)
                                                $wire.setSecondOpinionTab('all').then(() => document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
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
                                        class="flex h-6 min-w-6 shrink-0 items-center justify-center border border-dashed border-zinc-500 bg-white px-1 text-[10px] font-semibold text-zinc-700 transition hover:bg-zinc-50"
                                        x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-zinc-600 bg-zinc-50 ring-2 ring-zinc-300' : ''"
                                        x-on:click="
                                            $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                            $store.rmFocus.mark = null;
                                            document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

        @if ($pendingX !== null)
            {{-- Real wrapper (not display:contents) so Alpine $refs stay reliable; position via
                 x-bind:style so Livewire morphs cannot wipe left/top back to the top-left. --}}
            <div
                wire:key="pending-note-{{ $pendingX }}-{{ $pendingY }}-{{ $pendingW }}-{{ $pendingH }}"
                class="pointer-events-none fixed inset-0 z-50"
                x-data="{
                    left: null,
                    top: null,
                    origin: 'left center',
                    placed: false,
                    anchorHeight: null,
                    _placeScheduled: false,
                    mobile() {
                        return window.matchMedia('(max-width: 767px)').matches;
                    },
                    get panelStyle() {
                        if (this.mobile()) {
                            return '';
                        }
                        if (! this.placed || this.left == null || this.top == null) {
                            return 'visibility: hidden;';
                        }
                        return 'left:' + this.left + 'px; top:' + this.top + 'px; right: auto; bottom: auto; transform-origin: ' + this.origin + ';';
                    },
                    place() {
                        const panel = this.$refs.panel;
                        if (! panel) return false;

                        if (this.mobile()) {
                            this.left = null;
                            this.top = null;
                            this.placed = true;
                            this.origin = 'bottom center';
                            return true;
                        }

                        const mark = document.querySelector('[data-pending-mark]');
                        if (! mark) return false;

                        const rect = mark.getBoundingClientRect();
                        // Mark not laid out yet — retry rather than pinning to 0,0 → top-left.
                        if (rect.width < 1 && rect.height < 1) return false;

                        const gap = 12;
                        const pw = panel.offsetWidth || 320;
                        const measured = panel.offsetHeight || 280;
                        if (this.anchorHeight == null) {
                            this.anchorHeight = measured;
                        }
                        const ph = this.anchorHeight;
                        const spaceRight = window.innerWidth - rect.right;
                        const placeRight = spaceRight >= pw + gap || spaceRight >= rect.left;
                        let left = placeRight ? rect.right + gap : rect.left - pw - gap;
                        let top = rect.top + (rect.height / 2) - (ph / 2);
                        left = Math.max(8, Math.min(left, window.innerWidth - pw - 8));
                        top = Math.max(8, Math.min(top, window.innerHeight - ph - 8));
                        this.left = left;
                        this.top = top;
                        this.origin = placeRight ? 'left center' : 'right center';
                        this.placed = true;
                        return true;
                    },
                    placeWhenReady(attempts = 0) {
                        if (this.place() || attempts >= 20) {
                            this.focusNote();
                            return;
                        }
                        requestAnimationFrame(() => this.placeWhenReady(attempts + 1));
                    },
                    schedulePlace() {
                        if (this._placeScheduled) return;
                        this._placeScheduled = true;
                        this.$nextTick(() => {
                            this._placeScheduled = false;
                            this.place();
                        });
                    },
                    onScroll(e) {
                        if (e?.target?.closest?.('.rm-note-composer')) return;
                        this.place();
                    },
                    focusNote() {
                        this.$nextTick(() => {
                            this.$refs.note?.focus?.();
                            const el = this.$refs.note?.querySelector?.('textarea') || this.$refs.note;
                            el?.focus?.();
                        });
                    }
                }"
                x-init="
                    $nextTick(() => placeWhenReady());
                    let unhook = window.Livewire?.hook?.('morph.updated', () => schedulePlace());
                    return () => { if (typeof unhook === 'function') unhook(); };
                "
                x-on:resize.window.debounce.50ms="place()"
                x-on:scroll.window.capture="onScroll($event)"
            >
                <button
                    type="button"
                    class="pointer-events-auto fixed inset-0 z-40 bg-zinc-950/25 md:bg-transparent"
                    wire:click="cancelPin"
                    aria-label="Dismiss note"
                ></button>

                <div
                    x-ref="panel"
                    x-bind:style="panelStyle"
                    class="rm-note-composer pointer-events-auto fixed inset-x-0 bottom-0 z-50 max-h-[min(78svh,34rem)] overflow-y-auto rounded-t-2xl border border-zinc-200 bg-white p-4 shadow-[0_-12px_40px_-18px_rgba(24,24,27,0.45)] md:inset-x-auto md:bottom-auto md:w-[min(20rem,calc(100vw-1rem))] md:rounded-2xl md:p-3.5 md:shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]"
                    role="dialog"
                    aria-label="{{ $mode === 'guest' ? 'Suggest a change' : 'Leave a note' }}"
                    x-on:keydown.escape.window="$wire.cancelPin()"
                >
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-zinc-200 md:hidden" aria-hidden="true"></div>
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <flux:heading size="sm">
                            @if ($pendingW !== null && $pendingH !== null && $pendingW >= 0.01 && $pendingH >= 0.01)
                                {{ $mode === 'guest' ? 'Suggest a change here' : 'Leave a note here' }}
                            @else
                                {{ $mode === 'guest' ? 'Suggest a change on this spot' : 'Leave a note on this spot' }}
                            @endif
                        </flux:heading>
                        <button
                            type="button"
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                            wire:click="cancelPin"
                            aria-label="Cancel"
                        >
                            <flux:icon.x-mark class="size-4" />
                        </button>
                    </div>
                    <div class="space-y-3">
                        @if ($mode === 'guest')
                            <flux:input
                                wire:model="guestName"
                                placeholder="Your name"
                                maxlength="40"
                                x-data
                                x-init="if (! $wire.guestName) { $wire.guestName = localStorage.getItem('revisemy_guest_name') || '' }"
                                x-on:change="if ($event.target.value) { localStorage.setItem('revisemy_guest_name', $event.target.value) }"
                            />
                            <flux:error name="guestName" />
                        @endif
                        <div x-ref="note">
                            <flux:textarea wire:model="draftBody" rows="3" placeholder="Be specific — what feels off, and what would be better?" />
                            <flux:error name="draftBody" />
                        </div>
                        @if ($mode === 'owner')
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Models\Annotation::severityLabels() as $value => $label)
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-sm has-[:checked]:border-zinc-400 has-[:checked]:bg-white has-[:checked]:shadow-sm">
                                    <input type="radio" wire:model.live="draftSeverity" value="{{ $value }}" class="{{ \App\Models\Annotation::accentClass($value) }}">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @if (in_array($draftSeverity, [\App\Models\Annotation::SEVERITY_MUST_FIX, \App\Models\Annotation::SEVERITY_NIT], true))
                            <div>
                                <flux:textarea wire:model="draftSuggestedCopy" rows="2" placeholder="Suggested copy (optional) — exact string for the agent" />
                                <flux:error name="draftSuggestedCopy" />
                            </div>
                        @endif
                        @endif
                        <div class="flex flex-col gap-2 sm:flex-row">
                            @if ($mode === 'guest')
                                <flux:button variant="primary" icon="chat-bubble-left-ellipsis" wire:click="savePin" class="w-full !bg-yellow-400 !text-zinc-900 hover:!bg-yellow-300 sm:w-auto">Suggest</flux:button>
                            @else
                                <flux:button variant="primary" icon="check" wire:click="savePin" class="w-full sm:w-auto">Save mark</flux:button>
                            @endif
                            <flux:button variant="ghost" icon="x-mark" wire:click="cancelPin" class="w-full sm:w-auto">Cancel</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
