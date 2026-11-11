        <section class="relative flex min-h-0 min-w-0 flex-1 flex-col bg-well">
            @php
                $multipleShots = $review->screenshots->count() > 1;
                $hasBrief = $review->context || ($this->isOwner() && $review->isOpenForFeedback());
                $previewSource = $shot?->previewSource();
                $liveUrl = $previewSource && ! $review->isExpired() ? route('reviews.screenshot.live', [$token, $shot]) : null;
                $canMarkPreview = $shot?->canMarkPreview() && $review->isOpenForFeedback();
                $websiteUrl = $review->sourceKind() === \App\Models\Review::SOURCE_URL && preg_match('#^https?://#i', $review->page_url ?? '')
                    ? $review->page_url : null;
                $quality = $shot?->captureQuality();
                $approximate = ($shot?->meta['elements_source'] ?? null) === 'separate_load';
                $previousShot = $shot && $this->isOwner() ? $review->previousScreenshotFor($shot) : null;
                $pdfPage = $shot?->isPdfPage() && $review->pdf_path && ! $review->isExpired();
                $pdfChange = $pdfPage && $previousShot
                    ? \App\Support\PdfPageMatch::changes($previousShot->meta['pdf_text'] ?? '', $shot->meta['pdf_text'] ?? '') : null;
            @endphp

            <div class="relative flex min-h-0 flex-1 flex-col">

            <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">

            @if ($shot)
                <div
                    wire:key="shot-viewer-{{ $shot->id }}"
                    class="relative flex min-h-0 min-w-0 flex-1 flex-col"
                    x-data="{
                        zoom: 1,
                        fit: 'width',
                        zoomMin: 1,
                        zoomMax: {{ $pdfPage ? 5 : 3 }},
                        zoomStep: 0.25,
                        comparing: false,
                        viewerInitialized: false,
                        disposePdf: null,
                        @if ($pdfPage)
                            pdfReady: false,
                            pdfSelectText: true,
                            pdfSelection: null,
                            pdfJump: {{ $activeScreenshotIndex + 1 }},
                            pdfQuery: '',
                            pdfResults: [],
                            pdfSearchOpen: false,
                            pdfSearchStatus: '',
                            pdfUrl: @js(route('reviews.pdf.original', $token)),
                            goToPdfPage(number) {
                                const index = Number(number) - 1;
                                if (!Number.isInteger(index) || index < 0 || index >= {{ $review->screenshots->count() }}) return;
                                this.pdfSearchOpen = false;
                                return this.changeShot(index);
                            },
                            async findPdfText() {
                                this.pdfSearchOpen = true;
                                this.pdfResults = [];
                                const query = this.pdfQuery.trim();
                                this.pdfSearchStatus = query ? 'Searching…' : 'Enter words to search for.';
                                if (!query) return;
                                try {
                                    const results = await (await window.rmPdfViewer()).search(this.pdfUrl, query);
                                    if (query !== this.pdfQuery.trim()) return;
                                    this.pdfResults = results;
                                    this.pdfSearchStatus = results.length ? results.length + (results.length === 1 ? ' matching page' : ' matching pages') : 'No matching text.';
                                } catch (error) {
                                    this.pdfSearchStatus = 'Could not search this PDF.';
                                }
                            },
                            markPdfSelection(kind) {
                                if (!this.pdfSelection) return;
                                const { quote, rects } = this.pdfSelection;
                                this.pdfSelection = null;
                                window.getSelection()?.removeAllRanges();
                                return $wire.startPdfPin({{ $shot->id }}, kind, quote, rects);
                            },
                        @endif
                        naturalWidth: {{ (int) ($shot->width ?: 0) }},
                        naturalHeight: {{ (int) ($shot->height ?: 0) }},
                        pixelRatio: {{ $shot->pixelRatio() }},
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
                        {{-- Element picking: the shot's element list, the one under the cursor, and whether clicks snap to it. --}}
                        elementsUrl: @js($review->isOpenForFeedback() ? $shot->elementsUrl() : null),
                        elements: null,
                        hover: null,
                        hoverFrame: null,
                        pickOn: (() => { try { return localStorage.getItem('rm-pick') !== 'off'; } catch (e) { return true; } })(),
                        {{-- Touch: a finger scrolls the shot and opens marks; Mark turns the next
                             drag or tap into a mark. Two fingers zoom the shot, never the page. --}}
                        coarse: window.matchMedia('(pointer: coarse)').matches,
                        marking: false,
                        lastTouch: 0,
                        pinch: null,
                        touchLens: null,
                        lensSize: 88,
                        changeShot(index) {
                            $store.rmFocus.finding = null;
                            return $wire.selectScreenshot(index).then(() => $nextTick(() => document.querySelector('[data-shot-picker]')?.focus({ preventScroll: true })));
                        },
                        toggleMarking() {
                            this.marking = ! this.marking;
                            this.cancelDraw();
                            if (this.marking) this.$store.rmFocus?.close();
                        },
                        touchedRecently() {
                            return Date.now() - this.lastTouch < 800;
                        },
                        onTouchStart(e) {
                            this.lastTouch = Date.now();
                            if (e.touches.length === 2) {
                                this.cancelDraw();
                                this.startPinch(e);
                                return;
                            }
                            if (e.touches.length !== 1 || ! this.marking || this.pinch || $wire.pendingX !== null) return;
                            const touch = e.touches[0];
                            const image = this.$refs.shotImg?.getBoundingClientRect();
                            if (! image || touch.clientX < image.left || touch.clientX > image.right || touch.clientY < image.top || touch.clientY > image.bottom) return;
                            const p = this.norm(touch);
                            if (! p) return;
                            this.updateTouchLens(touch, p);
                            this.drawing = true;
                            this.hover = null;
                            this.startX = p.x;
                            this.startY = p.y;
                            this.draft = { x: p.x, y: p.y, w: 0, h: 0 };
                        },
                        onTouchMove(e) {
                            if (this.pinch && e.touches.length === 2) {
                                e.preventDefault();
                                this.movePinch(e);
                                return;
                            }
                            if (this.drawing && this.marking) {
                                e.preventDefault();
                                const touch = e.touches[0];
                                this.moveDraw(touch);
                                this.updateTouchLens(touch, this.norm(touch));
                            }
                        },
                        onTouchEnd(e) {
                            this.lastTouch = Date.now();
                            if (this.pinch) {
                                if (e.touches.length < 2) this.pinch = null;
                                return;
                            }
                            if (! this.drawing || ! this.marking) return;
                            {{-- No emulated mouse events or click after: this tap was a mark. --}}
                            e.preventDefault();
                            this.marking = false;
                            this.touchLens = null;
                            const touch = e.changedTouches[0];
                            this.moveDraw(touch);
                            this.endDraw(touch, 8);
                        },
                        startPinch(e) {
                            const [a, b] = e.touches;
                            this.pinch = { d: Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY) || 1, zoom: this.zoom };
                        },
                        movePinch(e) {
                            const vp = this.$refs.viewport;
                            if (! vp) return;
                            const [a, b] = e.touches;
                            const d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY) || 1;
                            const next = Math.min(this.zoomMax, Math.max(this.zoomMin, +(this.pinch.zoom * d / this.pinch.d).toFixed(3)));
                            this.zoomAt(next, (a.clientX + b.clientX) / 2, (a.clientY + b.clientY) / 2);
                        },
                        zoomAt(next, clientX, clientY) {
                            if (next === this.zoom) return;
                            const vp = this.$refs.viewport;
                            const image = this.$refs.shotImg;
                            const point = this.norm({ clientX, clientY });
                            this.zoom = next;
                            if (! vp || ! image || ! point) return;

                            this.$nextTick(() => {
                                const rect = image.getBoundingClientRect();
                                vp.scrollLeft += rect.left + point.x * rect.width - clientX;
                                vp.scrollTop += rect.top + point.y * rect.height - clientY;
                            });
                        },
                        updateTouchLens(touch, point) {
                            const image = this.$refs.shotImg;
                            const viewport = this.$refs.viewport?.getBoundingClientRect();
                            if (! point || ! image || ! viewport) return;

                            const { width, height } = image.getBoundingClientRect();
                            const size = this.lensSize;
                            const magnification = 2.5;
                            const x = touch.clientX - viewport.left;
                            const y = touch.clientY - viewport.top;
                            this.touchLens = {
                                left: Math.max(6, Math.min(viewport.width - size - 6, x - size / 2)) + 'px',
                                top: Math.max(4, Math.min(viewport.height - size - 4, y >= size + 26 ? y - size - 22 : y + 30)) + 'px',
                                width: size + 'px',
                                height: size + 'px',
                                backgroundImage: 'url(' + image.currentSrc + ')',
                                backgroundSize: width * magnification + 'px ' + height * magnification + 'px',
                                backgroundPosition: (size / 2 - point.x * width * magnification) + 'px ' + (size / 2 - point.y * height * magnification) + 'px',
                            };
                        },
                        {{-- Live: the page's code as captured, framed so a click picks the real element (LiveSnapshot). --}}
                        liveUrl: @js($liveUrl),
                        live: false,
                        liveLoaded: false,
                        liveWidth: {{ max(1, (int) ($shot->meta['css_width'] ?? (round($shot->cssSize()['w']) ?: 1280))) }},
                        liveHeight: {{ max(1, (int) ($shot->meta['css_height'] ?? 800)) }},
                        liveScale: 1,
                        toggleLive() {
                            this.live = ! this.live;
                            this.$store.rmFocus.previewMode = this.live;
                            this.hover = null;
                            if (this.live) {
                                this.liveLoaded = true;
                                this.$nextTick(() => { this.measureLive(); this.loadLive(); });
                            }
                        },
                        {{-- Fetched here and handed to the frame as srcdoc, not loaded by the
                             frame itself: some browsers and extensions block a framed page load.
                             The frame's sandbox and the CSP inside the page still hold. --}}
                        liveFetched: false,
                        liveFailed: false,
                        liveLoading: false,
                        liveMarkError: false,
                        loadLive(frame = this.$refs.liveFrame) {
                            if (this.liveFetched || this.liveLoading || !frame) return;
                            this.liveLoading = true;
                            this.liveFailed = false;
                            fetch(this.liveUrl, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
                                .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
                                .then((html) => {
                                    if (!frame.isConnected) return;
                                    frame.srcdoc = html;
                                    this.liveFetched = true;
                                })
                                .catch(() => { this.liveFailed = true; })
                                .finally(() => { this.liveLoading = false; });
                        },
                        measureLive() {
                            const box = this.$refs.liveViewport;
                            if (! box) return;
                            const cs = getComputedStyle(box);
                            const avail = box.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
                            const height = box.clientHeight - parseFloat(cs.paddingTop) - (this.$refs.snapshotNotice?.offsetHeight || 0) - 16;
                            this.liveScale = Math.min(1, Math.max(0.1, Math.min(avail / this.liveWidth, height / this.liveHeight)));
                            this.tellLiveScale();
                        },
                        {{-- The picker draws its bar at 1 / scale, so it reads at full size. --}}
                        tellLiveScale() {
                            this.$refs.liveFrame?.contentWindow?.postMessage({ source: 'rm-review', type: 'scale', value: this.liveScale }, '*');
                        },
                        onLiveMessage(e) {
                            const data = e.data;
                            if (! this.$refs.liveFrame || e.source !== this.$refs.liveFrame.contentWindow || ! data || data.source !== 'rm-live') return;
                            if (data.type === 'size') {
                                this.tellLiveScale();
                            } else if (this.live && (data.type === 'pick' || data.type === 'edit')) {
                                $wire.markLiveElement(String(data.selector || ''), data.box || {}, data.type === 'edit' ? String(data.after || '') : null, {{ $shot->id }})
                                    .then((marked) => {
                                        this.liveMarkError = !marked;
                                        if (!marked) return;
                                        this.toggleLive();
                                        this.$nextTick(() => this.$root.querySelector('[data-pending-mark]')?.scrollIntoView({ block: 'center', behavior: 'smooth' }));
                                    });
                            }
                        },
                        init() {
                            if (this.viewerInitialized) return;
                            this.viewerInitialized = true;
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
                                if (this.liveUrl && this.$store.rmFocus?.previewMode) this.toggleLive();
                                @if ($pdfPage)
                                    const host = this.$refs.pdfLayer;
                                    window.rmPdfViewer?.().then((viewer) => {
                                        if (!host?.isConnected) return;
                                        const search = viewer.searchState(this.pdfUrl);
                                        this.pdfQuery = search.query;
                                        this.pdfResults = search.results;
                                        this.disposePdf = viewer.mount(this.pdfUrl, {{ $shot->meta['page'] }}, host, {
                                            ready: () => { this.pdfReady = true; },
                                            failed: () => { this.pdfReady = false; },
                                            selection: (selection) => { this.pdfSelection = selection; },
                                        });
                                    }).catch(() => { this.pdfReady = false; });
                                @endif
                            });
                            window.rmPick?.load(this.elementsUrl).then((list) => { this.elements = list; });
                        },
                        togglePick() {
                            this.pickOn = ! this.pickOn;
                            this.hover = null;
                            try { localStorage.setItem('rm-pick', this.pickOn ? 'on' : 'off'); } catch (e) {}
                        },
                        canPick(e) {
                            return this.pickOn && this.elements && window.rmPick && ! (e && e.altKey);
                        },
                        {{-- The element under the pointer, or null when it's off the shot. --}}
                        elementAt(e) {
                            const target = this.$refs.shotImg;
                            if (! target || ! this.canPick(e)) return null;
                            const rect = target.getBoundingClientRect();
                            if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) return null;
                            return window.rmPick.at(this.elements, (e.clientX - rect.left) / rect.width, (e.clientY - rect.top) / rect.height);
                        },
                        trackHover(e) {
                            if (this.hoverFrame) return;
                            this.hoverFrame = requestAnimationFrame(() => {
                                this.hoverFrame = null;
                                this.hover = this.drawing || this.spaceHeld || this.touchedRecently() || $wire.pendingX !== null ? null : this.elementAt(e);
                            });
                        },
                        hoverStyle() {
                            if (this.hover === null || ! this.elements) return 'display:none';
                            const b = window.rmPick.box(this.elements, this.hover);
                            return 'left:' + (b.x * 100) + '%;top:' + (b.y * 100) + '%;width:' + (b.w * 100) + '%;height:' + (b.h * 100) + '%';
                        },
                        hoverLabel() {
                            return this.hover === null || ! this.elements ? '' : window.rmPick.label(this.elements, this.hover);
                        },
                        destroy() {
                            this.resizeObserver?.disconnect();
                            this.disposePdf?.();
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
                            const cs = getComputedStyle(vp);
                            const availWidth = Math.max(1, vp.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight));
                            const availHeight = Math.max(1, vp.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom));
                            // A 2× capture's 100% is the size the page was drawn at.
                            const actual = this.naturalWidth ? this.naturalWidth / this.pixelRatio : availWidth;
                            const aspect = this.naturalWidth && this.naturalHeight ? this.naturalWidth / this.naturalHeight : 0;
                            if (this.fit === 'actual') {
                                this.baseWidth = actual;
                            } else if (this.fit === 'page' && aspect) {
                                this.baseWidth = Math.min(availWidth, actual, availHeight * aspect);
                            } else {
                                this.baseWidth = Math.min(availWidth, actual);
                            }
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
                            this.zoomStepBy(this.zoomStep);
                        },
                        zoomOut() {
                            this.zoomStepBy(-this.zoomStep);
                        },
                        zoomStepBy(step) {
                            const next = Math.max(this.zoomMin, Math.min(this.zoomMax, +(this.zoom + step).toFixed(2)));
                            const viewport = this.$refs.viewport?.getBoundingClientRect();
                            const image = this.$refs.shotImg?.getBoundingClientRect();
                            if (! this.coarse || ! viewport || ! image) {
                                this.zoom = next;
                                return;
                            }
                            this.zoomAt(
                                next,
                                (Math.max(viewport.left, image.left) + Math.min(viewport.right, image.right)) / 2,
                                (Math.max(viewport.top, image.top) + Math.min(viewport.bottom, image.bottom)) / 2,
                            );
                        },
                        setFit(mode) {
                            this.fit = mode;
                            this.zoom = 1;
                            this.measureLayout();
                            this.$refs.viewport?.scrollTo({ top: 0, left: 0, behavior: 'instant' });
                        },
                        resetZoom() {
                            this.zoom = 1;
                            this.$refs.viewport?.scrollTo({ top: 0, left: 0, behavior: 'instant' });
                        },
                        onKeyDown(e) {
                            if (e.code !== 'Space') return;
                            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable) return;
                            // Space presses a focused button or link — only hijack it for panning
                            // when nothing interactive has focus.
                            if (e.target.closest?.('button, a[href], summary, [role=button], [role=tab], [role=dialog]')) return;
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
                            this.trackHover(e);
                        },
                        onMouseUp(e) {
                            if (this.panning) {
                                this.endPan();
                                return;
                            }
                            this.endDraw(e);
                        },
                        beginDraw(e) {
                            {{-- A tap's emulated mouse events: touch marks only in Mark mode (onTouchStart). --}}
                            if (this.touchedRecently()) return;
                            if (this.isPanMode(e)) {
                                this.beginPan(e);
                                return;
                            }
                            if (e.button !== 0) return;
                            if (e.target.closest('[data-finding], [data-pin], [data-zoom-controls]')) return;
                            {{-- With a mark open, a click on the shot first puts it away. --}}
                            if (this.$store.rmFocus?.mark) {
                                this.$store.rmFocus.close();
                                e.preventDefault();
                                return;
                            }
                            const p = this.norm(e);
                            if (! p) return;
                            this.drawing = true;
                            this.hover = null;
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
                        endDraw(e, minDrag = 6) {
                            if (! this.drawing) return;
                            this.drawing = false;
                            const draft = this.draft;
                            this.draft = null;
                            if (! draft) return;
                            {{-- A drag of a few screen pixels is a box; anything less is a click. --}}
                            const rect = this.$refs.shotImg?.getBoundingClientRect();
                            if (rect && draft.w * rect.width >= minDrag && draft.h * rect.height >= minDrag) {
                                $wire.startPin(draft.x, draft.y, draft.w, draft.h, null, this.pickOn);
                                return;
                            }
                            {{-- A click snaps to the element under it; Alt-click drops a spot. --}}
                            const picked = this.elementAt(e);
                            if (picked !== null) {
                                const b = window.rmPick.box(this.elements, picked);
                                $wire.startPin(b.x, b.y, b.w, b.h, picked);
                            } else {
                                $wire.startPin(this.startX, this.startY);
                            }
                        },
                        cancelDraw() {
                            this.touchLens = null;
                            this.drawing = false;
                            this.draft = null;
                            this.panning = false;
                            this.hover = null;
                        }
                    }"
                    x-init="init(); return () => destroy()"
                    x-on:keydown.window="onKeyDown($event)"
                    x-on:keyup.window="onKeyUp($event)"
                    x-on:message.window="onLiveMessage($event)"
                    x-on:resize.window.debounce.100ms="live && measureLive()"
                >
                    @if ($hasBrief || $liveUrl || $multipleShots)
                        <div class="@container relative z-30 flex shrink-0 flex-wrap items-center justify-between gap-x-2 gap-y-1 px-3 pt-2 md:grid md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:px-4" data-canvas-toolbar>
                            @if ($hasBrief)
                                <div class="min-w-0 md:col-start-1 md:row-start-1 md:justify-self-start">
                                    @include('review.partials.brief')
                                </div>
                            @endif

                            @if ($liveUrl || $multipleShots)
                                <div class="ml-auto inline-flex min-w-0 items-center gap-1 rounded-full bg-raised p-0.5 md:col-start-2 md:row-start-1 md:ml-0 md:justify-self-center"
                                    role="group" aria-label="Preview controls">
                                    @if ($liveUrl)
                                        <div data-view-switch role="tablist" aria-label="View" class="hidden items-center gap-0.5 @min-[28rem]:flex">
                                            <button
                                                type="button"
                                                role="tab"
                                                class="flex h-7 items-center gap-1.5 rounded-full px-3 text-xs font-medium transition-colors"
                                                x-bind:class="! live ? 'bg-foreground text-background' : 'text-zinc-600 hover:bg-chip'"
                                                x-bind:aria-selected="(! live).toString()"
                                                x-on:click="live && toggleLive()"
                                                title="The captured screenshot"
                                            ><flux:icon.photo variant="micro" class="size-3.5" />Screenshot</button>
                                            <button
                                                type="button"
                                                role="tab"
                                                data-live-toggle
                                                class="flex h-7 items-center gap-1.5 rounded-full px-3 text-xs font-medium transition-colors"
                                                x-bind:class="live ? 'bg-foreground text-background' : 'text-zinc-600 hover:bg-chip'"
                                                x-bind:aria-selected="live.toString()"
                                                x-on:click="live || toggleLive()"
                                                title="The page’s code as captured: click a part to mark it, or change its words"
                                            ><flux:icon.code-bracket variant="micro" class="size-3.5" />Page preview</button>
                                        </div>
                                        <div class="min-w-0 @min-[28rem]:hidden">
                                            <flux:dropdown position="bottom" align="start">
                                                <button type="button" aria-label="Preview mode"
                                                    class="inline-flex h-8 items-center gap-1.5 rounded-full px-2.5 text-sm font-medium text-zinc-800 transition-colors hover:bg-chip focus-visible:outline-key">
                                                    <span x-text="live ? 'Page preview' : 'Screenshot'"></span>
                                                    <flux:icon.chevron-down variant="micro" class="size-3.5 shrink-0 text-muted-foreground" />
                                                </button>
                                                <flux:menu class="min-w-36">
                                                    <flux:menu.item icon="photo" x-on:click="if (live) toggleLive()" x-bind:aria-current="(! live).toString()">Screenshot</flux:menu.item>
                                                    <flux:menu.item icon="code-bracket" x-on:click="if (! live) toggleLive()" x-bind:aria-current="live.toString()">Page preview</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    @endif

                                    @if ($multipleShots)
                                        @if ($liveUrl)
                                            <span class="h-4 w-px shrink-0 bg-border" aria-hidden="true"></span>
                                        @endif
                                        <button type="button" aria-label="Previous screenshot"
                                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-full hover:bg-chip disabled:opacity-30 md:hidden"
                                            @disabled($activeScreenshotIndex === 0)
                                            wire:loading.attr="disabled" wire:target="selectScreenshot"
                                            x-on:click="changeShot({{ $activeScreenshotIndex - 1 }})">
                                            <flux:icon.chevron-left variant="micro" class="size-4" />
                                        </button>
                                        <flux:dropdown position="bottom" align="end" class="min-w-0">
                                            <button type="button" data-shot-picker
                                                aria-label="{{ isset($shot->meta['viewport']) ? 'Viewport' : 'Screenshot' }}: {{ $shot->railLabel($activeScreenshotIndex) }}"
                                                class="inline-flex h-10 max-w-full items-center gap-1 rounded-full px-1.5 md:px-2.5 text-sm font-medium text-zinc-800 transition-colors hover:bg-chip focus-visible:outline-key md:h-7 md:text-xs">
                                                <span class="truncate">{{ $shot->railLabel($activeScreenshotIndex) }}</span>
                                                <flux:icon.chevron-down variant="micro" class="size-3.5 shrink-0 text-muted-foreground" />
                                            </button>
                                            <flux:menu class="min-w-36 {{ $pdfPage ? 'max-h-80 overflow-y-auto' : '' }}">
                                                @foreach ($review->screenshots as $index => $shotOption)
                                                    <flux:menu.item
                                                        wire:key="shot-option-{{ $shotOption->id }}"
                                                        aria-current="{{ $activeScreenshotIndex === $index ? 'true' : 'false' }}"
                                                        x-on:click="
                                                            if ({{ $activeScreenshotIndex }} === {{ $index }}) return;
                                                            changeShot({{ $index }});
                                                        "
                                                    >
                                                        @if ($pdfPage)
                                                            <img src="{{ $shotOption->thumbUrl() }}" alt="" loading="lazy" class="h-8 w-12 shrink-0 rounded border border-border object-contain">
                                                        @endif
                                                        <span class="size-4 shrink-0" aria-hidden="true">
                                                            @if ($activeScreenshotIndex === $index)
                                                                <flux:icon.check variant="micro" class="size-4 text-zinc-800" />
                                                            @endif
                                                        </span>
                                                        {{ $shotOption->railLabel($index) }}
                                                        @if (isset($shotOption->meta['css_width'], $shotOption->meta['css_height']))
                                                            <span class="ml-auto text-xs text-muted-foreground">{{ $shotOption->meta['css_width'] }} × {{ $shotOption->meta['css_height'] }}</span>
                                                        @endif
                                                    </flux:menu.item>
                                                @endforeach
                                            </flux:menu>
                                        </flux:dropdown>
                                        <button type="button" aria-label="Next screenshot"
                                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-full hover:bg-chip disabled:opacity-30 md:hidden"
                                            @disabled($activeScreenshotIndex === $review->screenshots->count() - 1)
                                            wire:loading.attr="disabled" wire:target="selectScreenshot"
                                            x-on:click="changeShot({{ $activeScreenshotIndex + 1 }})">
                                            <flux:icon.chevron-right variant="micro" class="size-4" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($pdfPage)
                        @include('review.partials.pdf-tools')
                    @endif

                    @if (($quality['message'] ?? null) || $approximate || $previousShot || $websiteUrl)
                        <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-3 gap-y-1 px-3 pt-2 text-xs md:px-6">
                            @if (($quality['message'] ?? null) || $approximate)
                                <p class="{{ ($quality['message'] ?? null) ? 'text-problem-ink' : 'text-muted-foreground' }}" role="status" x-show="!live">
                                    {{ $quality['message'] ?? '' }}
                                    @if ($approximate)
                                        <span>Element positions came from a separate page load. Mark an area directly.</span>
                                    @endif
                                </p>
                            @endif
                            @if ($previousShot)
                                <button type="button" data-compare-capture class="link ml-auto font-medium" x-show="!live"
                                    x-on:click="comparing = !comparing; cancelDraw(); marking = false"
                                    x-bind:aria-pressed="comparing.toString()"
                                    x-text="comparing ? 'Back to screenshot' : 'Compare with previous pass'">Compare with previous pass</button>
                            @endif
                            @if ($websiteUrl)
                                <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" class="ml-auto inline-flex items-center gap-1 link">
                                    Open live site <flux:icon.arrow-top-right-on-square variant="micro" class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    @endif

                    @if ($previousShot)
                        <template x-if="comparing && !live">
                            <div class="min-h-0 flex-1 overflow-auto px-3 py-3 md:px-6">
                                @if ($warning = $shot->comparisonWarning($previousShot))
                                    <p class="mb-2 text-xs text-problem-ink">{{ $warning }}</p>
                                @endif
                                @if ($pdfChange)
                                    <div class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Changed text">
                                        @if ($pdfChange['before'] !== '') <del class="rounded bg-red-50 px-1 text-red-800">{{ $pdfChange['before'] }}</del> @endif
                                        @if ($pdfChange['after'] !== '') <ins class="rounded bg-green-50 px-1 text-green-800">{{ $pdfChange['after'] }}</ins> @endif
                                    </div>
                                @endif
                                <div class="grid grid-cols-2 items-start gap-3">
                                    @foreach (['Previous pass' => $previousShot, 'This pass' => $shot] as $label => $comparisonShot)
                                        <figure class="min-w-0">
                                            <figcaption class="sticky top-0 bg-well py-1 text-xs font-medium text-muted-foreground">{{ $label }} @if ($pdfPage) · Page {{ $comparisonShot->meta['page'] }} @endif</figcaption>
                                            <img class="block h-auto w-full rounded bg-raised" src="{{ $comparisonShot->url() }}" alt="{{ $label }} screenshot" loading="lazy">
                                        </figure>
                                    @endforeach
                                </div>
                            </div>
                        </template>
                    @endif

                    <div x-show="!comparing || live" @class([
                        'relative flex min-h-0 flex-1 flex-col',
                    ])>
                        <div data-zoom-controls x-show="!live && @js(! $shot->captureFailed())" class="absolute bottom-3 left-3 z-30 flex items-center gap-0.5 rounded-full bg-raised/95 p-0.5 shadow-md shadow-black/[0.06] ring-1 ring-black/[0.07] backdrop-blur md:left-1/2 md:-translate-x-1/2">
                            <template x-if="elements">
                                <div class="flex items-center">
                                    <button
                                        type="button"
                                        data-pick-toggle
                                        class="flex h-9 items-center gap-1 rounded-full px-2.5 text-xs font-medium transition-colors md:h-7"
                                        x-bind:class="pickOn ? 'bg-foreground text-background' : 'text-zinc-600 hover:bg-chip'"
                                        x-bind:aria-pressed="pickOn.toString()"
                                        x-on:click="togglePick()"
                                        title="When on, a click marks the whole part of the page under it"
                                    ><flux:icon.cursor-arrow-rays variant="micro" class="size-3.5" />Snap</button>
                                    <span class="mx-0.5 h-4 w-px bg-zinc-200" aria-hidden="true"></span>
                                </div>
                            </template>
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-full text-sm text-zinc-600 transition hover:bg-chip disabled:opacity-40 md:size-7"
                                x-on:click="zoomOut()"
                                x-bind:disabled="zoom <= zoomMin"
                                aria-label="Zoom out"
                            >−</button>
                            <flux:dropdown position="top" align="center">
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1 rounded-full px-2.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-chip md:h-7"
                                    aria-label="Zoom preset"
                                >
                                    <span x-text="fit === 'width' ? 'Fit width' : (fit === 'page' ? 'Whole page' : 'Actual size')">Fit width</span>
                                    <flux:icon.chevron-down variant="micro" class="size-3.5" />
                                </button>
                                <flux:menu>
                                    <flux:menu.item x-on:click="setFit('width')">Fit width</flux:menu.item>
                                    <flux:menu.item x-on:click="setFit('page')">Whole page</flux:menu.item>
                                    <flux:menu.item x-on:click="setFit('actual')">Actual size</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-full text-sm text-zinc-600 transition hover:bg-chip disabled:opacity-40 md:size-7"
                                x-on:click="zoomIn()"
                                x-bind:disabled="zoom >= zoomMax"
                                aria-label="Zoom in"
                            >+</button>
                        </div>

                        @if ($review->isOpenForFeedback() && ($mode === 'owner' || $review->allowsGuestAccess()))
                            @if ($this->activeMarks->isEmpty() && $this->openGuestSuggestions->isEmpty())
                                <p x-show="! coarse && ! live" class="pointer-events-none absolute bottom-4 left-4 z-20 hidden text-xs text-muted-foreground xl:block">
                                    {{ $mode === 'guest' ? 'Click the preview to suggest a change' : 'Click the preview to leave a mark' }}
                                </p>
                            @endif
                            {{-- Touch: the way in to marking, so a scroll or a stray tap never leaves a mark. --}}
                            <button
                                type="button"
                                data-mark-toggle
                                x-show="coarse && !live && @js(! $shot->captureFailed())"
                                x-cloak
                                class="absolute right-3 bottom-3 z-30 inline-flex h-11 items-center gap-1.5 rounded-full px-4 text-sm font-semibold shadow-md shadow-black/[0.12] transition-colors"
                                x-bind:class="marking ? 'bg-foreground text-background' : 'bg-key text-accent-contrast'"
                                x-on:click="toggleMarking()"
                                x-bind:aria-pressed="marking.toString()"
                            >
                                <flux:icon.plus variant="micro" class="size-4" x-show="! marking" />
                                <flux:icon.x-mark variant="micro" class="size-4" x-show="marking" x-cloak />
                                <span x-text="marking ? 'Cancel' : '{{ $mode === 'guest' ? 'Suggest' : 'Mark' }}'">{{ $mode === 'guest' ? 'Suggest' : 'Mark' }}</span>
                            </button>
                            <p
                                data-marking-hint
                                x-show="marking && ! drawing"
                                x-cloak
                                x-transition.opacity.duration.120ms
                                class="pointer-events-none absolute inset-x-3 bottom-[4.25rem] z-30 mx-auto w-fit max-w-[calc(100%-1.5rem)] rounded-full bg-foreground px-3 py-1.5 text-center text-xs font-medium text-background shadow-md"
                                x-text="elements && pickOn ? 'Tap an element or drag to outline it' : 'Tap a spot or drag to outline an area'"
                            ></p>
                        @endif

                    <template x-if="touchLens">
                        <div data-touch-lens aria-hidden="true"
                            class="pointer-events-none absolute z-40 rounded-xl bg-well bg-no-repeat shadow-lg ring-2 ring-key"
                            x-bind:style="touchLens">
                            <span class="absolute left-1/2 top-1/2 h-5 w-px -translate-x-1/2 -translate-y-1/2 bg-key shadow-sm"></span>
                            <span class="absolute left-1/2 top-1/2 h-px w-5 -translate-x-1/2 -translate-y-1/2 bg-key shadow-sm"></span>
                        </div>
                    </template>

                    <template x-if="liveLoaded">
                        <div
                            x-show="live"
                            x-ref="liveViewport"
                            data-live-view
                            class="absolute inset-0 z-20 overflow-auto overscroll-contain bg-well px-3 pt-3 pb-20 md:px-6"
                        >
                            <p x-ref="snapshotNotice" class="mx-auto mb-2 max-w-full text-xs text-muted-foreground" x-bind:style="'width:' + (liveWidth * liveScale) + 'px'">
                                @if ($previewSource === 'separate_load')
                                    Preview from a separate page load. Use the screenshot to mark it.
                                @elseif ($canMarkPreview)
                                    Saved page preview. Scroll to explore, or click an element to leave feedback.
                                @else
                                    Saved page preview. Use the screenshot to mark it.
                                @endif
                            </p>
                            <p x-show="liveLoading" x-cloak class="mx-auto mb-2 text-center text-xs text-muted-foreground" role="status">Loading page preview…</p>
                            <p x-show="liveMarkError" x-cloak class="mx-auto mb-2 text-center text-xs text-problem-ink" role="alert">
                                This element couldn’t be matched to the screenshot. <button type="button" class="link" x-on:click="toggleLive()">Mark it on the screenshot</button>
                            </p>
                            <p x-show="liveFailed" x-cloak class="mx-auto mb-2 text-sm text-problem-ink" role="alert" x-bind:style="{ width: (liveWidth * liveScale) + 'px' }">
                                The page’s code didn’t load. <button type="button" class="link" x-on:click="liveFailed = false; loadLive()">Try again</button>
                            </p>
                            <div class="relative mx-auto overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(0,0,0,0.06),0_8px_24px_rgba(0,0,0,0.05)]" x-bind:style="'width:' + (liveWidth * liveScale) + 'px;height:' + (liveHeight * liveScale) + 'px'">
                                <iframe
                                    x-ref="liveFrame"
                                    x-init="$nextTick(() => { measureLive(); loadLive($el) })"
                                    sandbox="allow-scripts"
                                    referrerpolicy="no-referrer"
                                    title="Saved page preview"
                                    class="absolute left-0 top-0 origin-top-left border-0"
                                    x-bind:style="'width:' + liveWidth + 'px;height:' + liveHeight + 'px;transform:scale(' + liveScale + ')'"
                                ></iframe>
                            </div>
                        </div>
                    </template>

                    <div
                        x-ref="viewport"
                        @if ($shot->captureFailed()) hidden @endif
                        class="relative min-h-0 flex-1 overflow-auto overscroll-contain px-3 pt-3 pb-20 md:px-6 md:pt-4"
                        x-bind:class="{
                            '[touch-action:none]': marking,
                            '[touch-action:pan-x_pan-y]': ! marking,
                            'cursor-grab': canPan() && spaceHeld && !panning,
                            'cursor-grabbing': panning
                        }"
                        x-on:wheel="onWheel($event)"
                        x-on:mousedown="isPanMode($event) && beginPan($event)"
                        x-on:mousemove.window="onMouseMove($event)"
                        x-on:mouseup.window="onMouseUp($event)"
                        @if ($review->isOpenForFeedback())
                            x-on:touchstart="onTouchStart($event)"
                            x-on:touchmove="onTouchMove($event)"
                            x-on:touchend="onTouchEnd($event)"
                            x-on:touchcancel="cancelDraw(); pinch = null"
                        @else
                            x-on:touchstart.passive="lastTouch = Date.now(); $event.touches.length === 2 && startPinch($event)"
                            x-on:touchmove="pinch && $event.touches.length === 2 && ($event.preventDefault(), movePinch($event))"
                            x-on:touchend.passive="if ($event.touches.length < 2) pinch = null"
                        @endif
                        x-on:gesturestart.prevent
                    >
                        <div
                            x-ref="canvas"
                            class="relative mx-auto max-w-full select-none rounded-lg bg-raised shadow-[0_1px_3px_rgba(0,0,0,0.06),0_8px_24px_rgba(0,0,0,0.05)]"
                            x-bind:style="canvasStyle()"
                            @if ($review->isOpenForFeedback())
                                x-bind:class="(zoom > 1 || fit === 'actual' ? '!max-w-none ' : '') + (! spaceHeld ? 'cursor-crosshair ' : '') + (marking ? '[&_[data-pin]]:pointer-events-none [&_[data-finding]]:pointer-events-none ring-2 ring-key/60' : '')"
                                x-on:mousedown="beginDraw($event)"
                                x-on:keydown.escape.window="cancelDraw()"
                            @else
                                x-bind:class="zoom > 1 || fit === 'actual' ? '!max-w-none' : ''"
                            @endif
                        >
                            <img
                                x-ref="shotImg"
                                src="{{ $shot->url() }}"
                                alt="Screenshot {{ $activeScreenshotIndex + 1 }}"
                                @if ($shot->width) width="{{ $shot->width }}" @endif
                                @if ($shot->height) height="{{ $shot->height }}" @endif
                                class="pointer-events-none block h-auto w-full max-w-full rounded-lg"
                                @if ($pdfPage) x-bind:class="pdfReady ? 'opacity-0' : ''" @endif
                                draggable="false"
                                x-on:load="onImageLoad()"
                            />
                            @if ($pdfPage)
                                <div wire:ignore x-ref="pdfLayer" data-pdf-layer class="rm-pdf-layer absolute inset-0"
                                    x-bind:class="!pdfReady ? 'pointer-events-none opacity-0' : pdfSelectText && !marking ? 'select-text' : 'pointer-events-none select-none'"
                                    x-on:mousedown.stop x-on:touchstart.stop x-on:touchmove.stop>
                                    <canvas aria-hidden="true" class="pointer-events-none block size-full rounded-lg"></canvas>
                                    <div class="textLayer" aria-label="PDF page text"></div>
                                </div>
                                @foreach ($shot->annotations as $mark)
                                    @foreach ($mark->pdf_anchor['rects'] ?? [] as $rect)
                                        <span aria-hidden="true" class="pointer-events-none absolute z-[4] {{ $mark->pdf_anchor['kind'] === 'strikeout' ? 'bg-red-600/60' : 'bg-yellow-300/40' }}"
                                            style="left:{{ $rect['x'] * 100 }}%;top:{{ ($rect['y'] + ($mark->pdf_anchor['kind'] === 'strikeout' ? $rect['h'] / 2 : 0)) * 100 }}%;width:{{ $rect['w'] * 100 }}%;height:{{ $mark->pdf_anchor['kind'] === 'strikeout' ? '2px' : ($rect['h'] * 100).'%' }}"></span>
                                    @endforeach
                                @endforeach
                            @endif

                            @php($openFindings = $this->openSecondOpinion)
                            @php($guestFindings = $this->openGuestSuggestions)
                            @php($textOnlyGuest = $guestFindings->filter(fn ($f) => ! $f->hasRegion() && ($f->x === null || $f->y === null))->values())
                            @foreach ($openFindings as $findingIndex => $finding)
                                @php($area = $finding->region())
                                @if ($area)
                                    @php($badgePosition = ($area['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($area['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <div
                                        data-finding
                                        class="absolute pointer-events-none z-[5]"
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
                                            class="pointer-events-auto absolute {{ $badgePosition }} z-[6] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-0.5 text-xs font-semibold text-sky-700 shadow-sm transition"
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
                                        class="absolute pointer-events-none z-[7]"
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
                                            class="pointer-events-auto absolute {{ $badgePosition }} z-[8] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-zinc-500 bg-white px-0.5 text-xs font-semibold text-zinc-700 shadow-sm transition"
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
                                        class="absolute z-[7] flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-zinc-500 bg-white px-0.5 text-xs font-semibold text-zinc-700 shadow-sm transition"
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
                                            class="pointer-events-auto flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-zinc-500 bg-white px-0.5 text-xs font-semibold text-zinc-700 shadow-sm transition"
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
                                        data-mark-id="{{ $annotation->id }}"
                                        aria-label="Open mark M{{ $annotation->number }}"
                                        aria-haspopup="dialog"
                                        x-on:click.stop="$store.rmFocus.open({{ $annotation->id }}, 'canvas')"
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border-2 border-key/80 bg-key/10 transition"
                                            x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'ring-2 ring-key ring-offset-1' : ''"
                                        ></div>
                                        <span
                                            class="pointer-events-none absolute {{ $markBadgePosition }} z-[9] flex h-6 min-w-6 items-center justify-center rounded-full px-0.5 text-xs font-semibold shadow-sm ring-2 ring-white transition {{ $annotation->markerClass() }}"
                                            x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'scale-110' : ''"
                                        >
                                            M{{ $annotation->number }}
                                        </span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        data-pin
                                        class="absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-xs font-semibold shadow-lg ring-2 ring-white transition before:absolute before:-inset-2 before:content-[''] {{ $annotation->markerClass() }} {{ $markState }}"
                                        style="left: {{ $annotation->x * 100 }}%; top: {{ $annotation->y * 100 }}%;"
                                        data-mark-id="{{ $annotation->id }}"
                                        aria-label="Open mark M{{ $annotation->number }}"
                                        aria-haspopup="dialog"
                                        x-on:click.stop="$store.rmFocus.open({{ $annotation->id }}, 'canvas')"
                                        x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'scale-110 ring-4 ring-key/60' : ''"
                                    >
                                        M{{ $annotation->number }}
                                    </button>
                                @endif
                            @endforeach

                            {{-- The element a click would mark. --}}
                            <div
                                data-element-hover
                                class="pointer-events-none absolute z-[16] rounded-sm bg-key/5 ring-2 ring-key/70"
                                x-bind:style="hoverStyle()"
                                x-cloak
                            >
                                <span
                                    class="absolute bottom-full left-0 mb-1 max-w-[min(20rem,90vw)] truncate rounded-md bg-foreground px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap text-background shadow-sm"
                                    x-text="hoverLabel()"
                                ></span>
                            </div>

                            <template x-if="draft && draft.w > 0 && draft.h > 0">
                                <div
                                    class="pointer-events-none absolute z-[15] rounded-md border-2 border-dashed {{ $mode === 'guest' ? 'border-zinc-400 bg-zinc-400/15' : 'border-key bg-key/15' }}"
                                    x-bind:style="'left:' + (draft.x * 100) + '%;top:' + (draft.y * 100) + '%;width:' + (draft.w * 100) + '%;height:' + (draft.h * 100) + '%'"
                                ></div>
                            </template>

                            @if ($pendingX !== null && $pendingY !== null)
                                @if ($pendingW !== null && $pendingH !== null && $pendingW >= \App\Support\NormalizedArea::MIN_MARK_SIDE && $pendingH >= \App\Support\NormalizedArea::MIN_MARK_SIDE)
                                    <div
                                        data-pending-mark
                                        class="pointer-events-none absolute z-[18] rounded-md border-2 border-dashed {{ $mode === 'guest' ? 'border-zinc-400 bg-zinc-400/15' : 'border-key bg-key/15' }}"
                                        style="left: {{ ($pendingX - $pendingW / 2) * 100 }}%; top: {{ ($pendingY - $pendingH / 2) * 100 }}%; width: {{ $pendingW * 100 }}%; height: {{ $pendingH * 100 }}%;"
                                    ></div>
                                @else
                                    <div
                                        data-pending-mark
                                        class="pointer-events-none absolute z-[18] h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full {{ $mode === 'guest' ? 'bg-zinc-500' : 'bg-key' }}"
                                        style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                                    ></div>
                                @endif
                                <div
                                    class="absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full text-xs font-semibold shadow-lg ring-2 ring-white {{ $mode === 'guest' ? 'border-2 border-dashed border-zinc-400 bg-white text-zinc-700' : 'bg-key text-accent-contrast' }}"
                                    style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                                >
                                    {{ $mode === 'guest' ? 'G' : '+' }}
                                </div>
                            @endif
                        </div>
                    </div>
                    </div>

                </div>
            @else
                @if ($hasBrief)
                    <div class="relative z-30 px-3 py-2 md:px-4">@include('review.partials.brief')</div>
                @endif
                <flux:callout variant="warning">No screenshots on this review yet.</flux:callout>
            @endif

            </div>
            </div>
        </section>

        @include('review.partials.mark-popover')

        @if ($pendingX !== null)
            {{-- Real wrapper (not display:contents) so Alpine $refs stay reliable; position via
                 x-bind:style so Livewire morphs cannot wipe left/top back to the top-left. --}}
            <div
                wire:key="pending-note-{{ $pendingX }}-{{ $pendingY }}-{{ $pendingW }}-{{ $pendingH }}"
                class="pointer-events-none fixed inset-0 z-50"
                x-data="{
                    manualType: false,
                    copyOpen: false,
                    left: null,
                    top: null,
                    origin: 'left center',
                    placed: false,
                    revealed: false,
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
                            if (! this.revealed) {
                                this.revealed = true;
                                this.$nextTick(() => window.rmRevealAbove(document.querySelector('[data-pending-mark]'), panel.offsetHeight));
                            }
                            return true;
                        }

                        const mark = document.querySelector('[data-pending-mark]');
                        if (! mark) return false;

                        if (this.anchorHeight == null) {
                            this.anchorHeight = panel.offsetHeight || 280;
                        }
                        // Mark not laid out yet: null, so we retry rather than pin to the top-left.
                        const at = window.rmPlaceBeside(mark.getBoundingClientRect(), panel.offsetWidth || 320, this.anchorHeight);
                        if (! at) return false;
                        this.left = at.left;
                        this.top = at.top;
                        this.origin = at.origin;
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
                        if (this.mobile()) return;
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
                    class="pointer-events-auto fixed inset-0 z-40 bg-black/25 md:bg-transparent"
                    wire:click="cancelPin"
                    aria-label="Dismiss note"
                ></button>

                <div
                    x-ref="panel"
                    x-bind:style="panelStyle"
                    class="rm-note-composer pointer-events-auto fixed inset-x-0 bottom-0 z-50 max-h-[min(78svh,34rem)] overflow-y-auto rounded-t-2xl bg-lift p-4 pb-[max(1rem,env(safe-area-inset-bottom))] md:pb-3.5 shadow-[0_-12px_40px_-18px_rgba(0,0,0,0.45)] ring-1 ring-black/[0.07] md:inset-x-auto md:bottom-auto md:w-[min(22rem,calc(100vw-1rem))] md:rounded-2xl md:p-3.5 md:shadow-[0_18px_50px_-24px_rgba(0,0,0,0.45)]"
                    role="dialog"
                    aria-label="{{ $mode === 'guest' ? 'Suggest a change' : 'Leave a note' }}"
                    x-on:keydown.escape.window="$wire.cancelPin()"
                >
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-zinc-200 md:hidden" aria-hidden="true"></div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <flux:heading size="sm">{{ $mode === 'guest' ? 'Suggest a change' : 'Leave a note' }}</flux:heading>
                        <button
                            type="button"
                            class="flex size-9 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 md:size-7"
                            wire:click="cancelPin"
                            aria-label="Close note"
                        >
                            <flux:icon.x-mark class="size-4" />
                        </button>
                    </div>
                    <div class="space-y-3">
                        @if ($pendingPdfAnchor)
                            <blockquote class="border-l-2 border-key px-2 text-sm text-zinc-700">{{ $pendingPdfAnchor['quote'] }}</blockquote>
                        @endif
                        @if ($pendingTarget)
                            {{-- What the mark is tied to; the agent gets the same. --}}
                            <div class="space-y-1.5" data-pending-element>
                                <div class="flex items-center gap-1">
                                    <x-element-chip :target="$pendingTarget" quiet class="min-w-0 flex-1" />
                                    @if ($pendingParent !== null)
                                        <button type="button" class="h-9 shrink-0 rounded-md px-2 text-xs font-medium text-zinc-500 transition hover:bg-chip hover:text-zinc-900 md:h-6 md:px-1.5 md:text-[11px]" wire:click="widenPendingElement" title="Mark the part around it instead">Wider</button>
                                    @endif
                                    <button type="button"
                                        class="h-8 shrink-0 rounded-md px-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-chip hover:text-zinc-900 md:h-6"
                                        wire:click="dropPendingElement" title="Keep the marked area and detach the element">
                                        Use area
                                    </button>
                                </div>
                                <x-element-inspect :target="$pendingTarget" :screenshot="$shot" reference-action="toggleDraftElementReference" :selected-references="$draftBodyReferences" />
                            </div>
                        @endif
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
                            <div class="overflow-hidden rounded-xl border border-input bg-background focus-within:ring-2 focus-within:ring-key/40">
                                <x-element-reference-chips :references="$draftBodyReferences" action="toggleDraftElementReference" class="px-2 pt-2" />
                                <textarea wire:model="draftBody" rows="3" aria-label="Mark note" placeholder="Be specific — what feels off, and what would be better?"
                                    class="block w-full resize-y border-0 bg-transparent px-3 py-2 text-base text-zinc-800 outline-none placeholder:text-zinc-500 sm:text-sm"
                                    @if ($mode === 'owner')
                                        x-on:input="if (! manualType) $wire.draftSeverity = window.rmSuggestMarkType($event.target.value)"
                                    @endif
                                    x-on:keydown.meta.enter.prevent="$wire.savePin()" x-on:keydown.ctrl.enter.prevent="$wire.savePin()"></textarea>
                                @if ($mode === 'owner')
                                    <div class="flex flex-wrap items-center justify-between gap-x-2 px-2 pb-2">
                                        <x-mark-type-picker x-model="$wire.draftSeverity" x-on:change="manualType = true" />
                                        <button type="button"
                                            class="inline-flex h-8 items-center gap-1 rounded-full px-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-chip hover:text-zinc-900 focus-visible:outline-key"
                                            x-cloak x-show="! copyOpen && ! $wire.draftSuggestedCopy && ['must-fix', 'nit'].includes($wire.draftSeverity)"
                                            aria-controls="mark-suggested-copy"
                                            x-on:click="copyOpen = true; $nextTick(() => $refs.suggestedCopy.querySelector('textarea').focus())">
                                            <flux:icon.plus variant="micro" class="size-3.5" />
                                            Add copy
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <flux:error name="draftBody" />
                            @if ($mode === 'owner')
                                <div id="mark-suggested-copy" class="mt-2" x-ref="suggestedCopy" x-cloak x-show="copyOpen || $wire.draftSuggestedCopy || @js(($pendingPdfAnchor['kind'] ?? null) === 'replace')">
                                    <flux:textarea wire:model="draftSuggestedCopy" rows="2" label="Suggested copy" placeholder="Enter the exact replacement text" />
                                    <flux:error name="draftSuggestedCopy" />
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            @if ($mode === 'guest')
                                <flux:button size="sm" variant="primary" icon="chat-bubble-left-ellipsis" wire:click="savePin" class="w-full !rounded-full max-md:!h-11 sm:w-auto">Suggest</flux:button>
                            @else
                                <flux:button size="sm" variant="primary" icon="check" wire:click="savePin" class="w-full !rounded-full max-md:!h-11 sm:w-auto">Save mark</flux:button>
                            @endif
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancelPin" class="w-full !rounded-full max-md:!h-11 sm:w-auto">Cancel</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
