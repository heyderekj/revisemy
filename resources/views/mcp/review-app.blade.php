{{-- Inline MCP App UI for ReviseMy reviews. Rendered in a sandboxed iframe by
     MCP Apps hosts (Claude web/desktop, etc.).

     Parity with web review/board (keep in sync in the same PR as chrome changes):
     - Marker / status / severity maps ↔ Annotation::markerClass, statusBadgeClass, severityLabels
     - Board columns / empty copy ↔ Annotation::boardColumnMeta
     - Mark focus crop via pin.focus_preview ↔ MarkFocus + mark-focus-preview
     - Control height h-8 ↔ Flux size="sm" on web
     Intentionally web-only: comment threads, share/guest, drag columns, second-opinion
     accept/dismiss. When comment_count > 0, link out via review_url / board_url.

     Tailwind + Alpine from CSP-allowlisted CDNs; bridge is inline. App-only tools:
     add_mark / decide_review / verify_mark. --}}
{!! $libraryScripts !!}
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600">
<script>
    // Mirrors the @theme block in resources/css/app.css. This surface loads the
    // Tailwind v3 Play CDN and never sees app.css, so the design tokens have to be
    // restated here — keep the two in sync. `borderRadius.full` is left alone via
    // `extend` so pills and avatars survive the hard-corner pass.
    tailwind.config = {
        theme: {
            extend: {
                borderRadius: {
                    none: '0px', sm: '0px', DEFAULT: '0px', md: '0px',
                    lg: '0px', xl: '0px', '2xl': '0px', '3xl': '0px', full: '0px',
                },
                colors: {
                    accent: {
                        DEFAULT: '#ffc53d', hover: '#ffba18',
                        contrast: '#21201c',
                    },
                    ink: '#21201c',
                    guest: '#82827c',
                    // zinc -> Radix sand
                    zinc: {
                        50: '#f9f9f8', 100: '#f1f0ef', 200: '#dad9d6', 300: '#cfceca',
                        400: '#8d8d86', 500: '#6b6b65', 600: '#63635e', 700: '#4a4943',
                        800: '#33322d', 900: '#21201c', 950: '#141310',
                    },
                    // rose -> yellow fills at the light end, ink at the dark end
                    rose: {
                        50: '#fffbe8', 100: '#fff7c2', 200: '#ffee9c', 300: '#fbe577',
                        400: '#ffd166', 500: '#ffc53d', 600: '#21201c', 700: '#33322d',
                        800: '#21201c', 900: '#141310', 950: '#141310',
                    },
                    amber: {
                        50: '#fefbe9', 100: '#fff7c2', 200: '#ffee9c', 300: '#fbe577',
                        400: '#e9c162', 500: '#ffc53d', 600: '#ffba18', 700: '#4a4943',
                        800: '#33322d', 900: '#21201c',
                    },
                    // emerald -> Radix jade
                    emerald: {
                        50: '#f4fbf7', 100: '#e6f7ed', 200: '#c3e9d7', 300: '#8bceb6',
                        400: '#56ba9f', 500: '#29a383', 600: '#26997b', 700: '#208368',
                        800: '#1d6a54', 900: '#1d3b31',
                    },
                    // red -> Radix tomato
                    red: {
                        50: '#fff8f7', 100: '#feebe7', 200: '#ffdcd3', 300: '#fdbdaf',
                        400: '#ec8e7b', 500: '#e54d2e', 600: '#dd4425', 700: '#d13415',
                        800: '#a32b12', 900: '#5c271f',
                    },
                },
            },
        },
    };
</script>
<style>
    body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, sans-serif; }
    [x-cloak] { display: none !important; }
    ::selection { background: #ffee9c; color: #21201c; }
</style>

<div class="bg-[#fdfdfc] text-zinc-900" x-data="reviewApp()" x-init="init()" x-cloak>
    <div class="mx-auto max-w-5xl px-4 py-4 sm:px-6">
        <template x-if="!payload">
            <p class="text-sm text-zinc-500">Loading review…</p>
        </template>

        <template x-if="payload">
            <div>
                {{-- header: title + chips, matching the review page header line --}}
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                    <h1 class="min-w-0 truncate text-lg font-semibold tracking-tight text-zinc-900" x-text="payload.title"></h1>
                    <span class="inline-flex shrink-0 items-center rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600"
                        x-text="'Pass ' + payload.pass"></span>
                    <span class="inline-flex shrink-0 items-center rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-sky-700"
                        x-show="payload.type" x-text="payload.type"></span>
                    <span class="relative inline-flex shrink-0" x-data="{ tasteOpen: false }" x-show="payload.taste && payload.taste.label">
                        <button type="button"
                            class="inline-flex items-center gap-1 rounded-full border border-sky-200 bg-white px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-sky-800"
                            @click="tasteOpen = ! tasteOpen"
                            x-text="payload.taste.label"></button>
                        <div class="absolute left-0 z-40 mt-8 w-64 rounded-xl border border-sky-200 bg-white p-3 text-left shadow-lg"
                            x-show="tasteOpen" x-cloak @click.outside="tasteOpen = false">
                            <p class="text-xs font-medium text-sky-950">Craft lenses for this review</p>
                            <template x-for="lens in (payload.taste.lenses || [])" :key="lens.id">
                                <div class="mt-2">
                                    <p class="text-xs font-semibold text-zinc-800" x-text="lens.name"></p>
                                    <p class="mt-0.5 text-[11px] leading-relaxed text-zinc-500" x-text="lens.blurb"></p>
                                    <a class="mt-1 inline-block text-[11px] font-medium text-sky-700 underline"
                                        :href="lens.source_url" target="_blank" rel="noopener noreferrer"
                                        x-text="lens.source_label || lens.source_url" x-show="lens.source_url"></a>
                                </div>
                            </template>
                            <p class="mt-3 border-t border-sky-100 pt-2 text-[10px] leading-relaxed text-zinc-400"
                                x-text="payload.taste.disclaimer"></p>
                        </div>
                    </span>
                    <span class="inline-flex shrink-0 items-center rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800"
                        x-show="payload.status === 'changes_requested'">Changes requested</span>
                    <span class="inline-flex shrink-0 items-center rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800"
                        x-show="payload.status === 'approved'">Approved</span>
                    <span class="inline-flex shrink-0 items-center rounded-md border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-800"
                        x-show="payload.status === 'expired'">Expired</span>
                    <span class="inline-flex shrink-0 items-center rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-zinc-600"
                        x-show="payload.status === 'pending'">Waiting on your eye</span>
                </div>
                <p class="mt-1 text-sm text-zinc-500" x-show="payload.context" x-text="payload.context"></p>

                {{-- counts row --}}
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <template x-for="chip in countChips()" :key="chip.label">
                        <span class="inline-flex items-center rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600"
                            x-text="chip.label + ' ' + chip.value"></span>
                    </template>
                </div>

                {{-- toolbar: view toggle + verified progress + refresh --}}
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-lg border border-zinc-200 bg-zinc-100 p-0.5">
                        <button type="button" class="h-8 rounded-md px-3 text-xs font-medium transition"
                            :class="view === 'screenshot' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'screenshot'">Screenshot</button>
                        <button type="button" class="h-8 rounded-md px-3 text-xs font-medium transition"
                            :class="view === 'board' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'board'" x-text="'Board · ' + boardPins().length"></button>
                    </div>
                    <div class="flex min-w-24 flex-1 items-center gap-2 sm:max-w-48">
                        <span class="shrink-0 text-xs tabular-nums text-zinc-500"
                            x-text="payload.loop.verified_count + '/' + boardPins().length"></span>
                        <div class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-zinc-200/80" role="progressbar" aria-label="Marks verified">
                            <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300 ease-out" :style="'width:' + verifiedPct() + '%'"></div>
                        </div>
                    </div>
                    <button type="button" class="inline-flex h-8 items-center gap-1 rounded-md bg-zinc-100 px-3 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80 disabled:opacity-50"
                        :disabled="busy" @click="refresh()">↻ Refresh</button>
                </div>

                {{-- SCREENSHOT VIEW --}}
                <div class="mt-3" x-show="view === 'screenshot'">
                    <div class="mb-2 flex flex-wrap gap-1.5" x-show="payload.screenshots.length > 1">
                        <template x-for="(shot, i) in payload.screenshots" :key="shot.id">
                            <button type="button" class="rounded-lg border px-2.5 py-1 text-xs font-medium transition"
                                :class="i === activeIndex ? 'border-zinc-400 bg-white text-zinc-900 shadow-sm' : 'border-zinc-200 bg-white text-zinc-500 hover:text-zinc-800'"
                                @click="setActive(i)" x-text="shotLabel(shot, i)"></button>
                        </template>
                    </div>

                    <template x-if="activeShot()">
                        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50">
                            <img class="block w-full" :src="activeShot().url" :alt="payload.title" draggable="false">
                            <div class="absolute inset-0 cursor-crosshair touch-none" x-ref="overlay"
                                @pointerdown="startDraw($event)" @pointermove="moveDraw($event)"
                                @pointerup="endDraw($event)" @pointerleave="cancelDraw()">

                                {{-- human marks: rose region + rose M# badge (review page classes) --}}
                                <template x-for="pin in activeShot().pins" :key="'p'+pin.id">
                                    <div>
                                        <template x-if="pin.area">
                                            <div class="pointer-events-none absolute rounded-md border-2 border-rose-500/80 bg-rose-500/10"
                                                :class="{ 'opacity-50': isSettled(pin) }" :style="rectStyle(pin.area)"></div>
                                        </template>
                                        <button type="button"
                                            class="absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full px-1 text-[10px] font-semibold shadow-lg ring-2 ring-white transition"
                                            :class="markerBg(pin.severity) + (isSettled(pin) ? ' opacity-60' : '') + (activePin && activePin.id === pin.id ? ' ring-zinc-900' : '')"
                                            :style="pinStyle(pin)" x-text="'M' + pin.number"
                                            @pointerdown.stop @pointerup.stop @click.stop="showPin(pin)"></button>
                                    </div>
                                </template>

                                {{-- second-opinion hints: dashed sky region + corner S# badge (vision only) --}}
                                <template x-for="(f, fi) in activeShot().second_opinion" :key="'s'+fi">
                                    <template x-if="f.area && f.area.w >= 0.01 && f.area.h >= 0.01">
                                        <div class="absolute" :style="rectStyle(f.area)">
                                            <div class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-sky-400/80 bg-sky-400/10"></div>
                                            <button type="button"
                                                class="absolute -left-2 -top-2 z-[6] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-0.5 text-[10px] font-semibold text-sky-700 shadow-sm transition"
                                                :class="activeFinding && activeFinding.key === 's'+fi ? 'ring-2 ring-sky-300' : ''"
                                                x-text="'S' + (fi + 1)"
                                                @pointerdown.stop @pointerup.stop @click.stop="showFinding(f, fi)"></button>
                                        </div>
                                    </template>
                                </template>

                                {{-- draft rectangle / pending composer pin (rose dashed, like the page) --}}
                                <div class="pointer-events-none absolute z-[15] rounded-md border-2 border-dashed border-rose-500 bg-rose-500/15"
                                    x-show="draft.drawing && draft.w > 0.01" :style="draftRectStyle()"></div>
                                <div class="pointer-events-none absolute z-[18] rounded-md border-2 border-dashed border-rose-500 bg-rose-500/15"
                                    x-show="composer.open && composer.area" :style="composer.area ? rectStyle(composer.area) : ''"></div>
                                <div class="absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-accent text-xs font-semibold text-ink shadow-lg ring-2 ring-white"
                                    x-show="composer.open && !composer.area" :style="pinStyle(composer)">+</div>
                            </div>
                        </div>
                    </template>

                    {{-- Mark detail (focus crop + parity with board mark sheet) --}}
                    <div class="mt-3 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]" x-show="activePin" x-cloak>
                        <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-3 py-3 sm:px-4">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <span class="flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-[11px] font-semibold"
                                    :class="markerBg(activePin ? activePin.severity : '')"
                                    x-text="activePin && ('M' + activePin.number)"></span>
                                <span class="text-xs text-zinc-500" x-text="activePin && severityLabel(activePin.severity)"></span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="statusBadge(activePin ? activePin.status : '')"
                                    x-text="activePin && statusLabel(activePin.status)"></span>
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500"
                                    x-show="activePin && activePin._from_parent"
                                    x-text="activePin && ('Pass ' + activePin._pass)"></span>
                            </div>
                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700" @click="closeDetail()" aria-label="Close">×</button>
                        </div>

                        <template x-if="activePin && activePin.focus_preview && activePin.focus_preview.bg_style">
                            <div class="w-full max-h-[min(40dvh,22rem)] overflow-hidden border-b border-zinc-100 bg-zinc-100">
                                <div class="relative w-full bg-zinc-100 bg-no-repeat"
                                    :style="'aspect-ratio:' + Math.max(activePin.focus_preview.ratio || 1.6, 0.01) + ';' + activePin.focus_preview.bg_style"
                                    role="img" :aria-label="'Cropped screenshot focused on mark M' + activePin.number">
                                    <template x-if="activePin.focus_preview.overlay">
                                        <div class="pointer-events-none absolute rounded-md border-2 border-rose-500 bg-rose-500/15"
                                            :style="rectStyle(activePin.focus_preview.overlay)"></div>
                                    </template>
                                    <template x-if="activePin.focus_preview.point">
                                        <span class="pointer-events-none absolute flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-[10px] font-semibold shadow ring-2 ring-white"
                                            :class="markerBg(activePin.severity)"
                                            :style="pinStyle(activePin.focus_preview.point)"
                                            x-text="'M' + activePin.number"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="space-y-3 px-3 py-3 sm:px-4">
                            <div>
                                <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-400">Feedback</p>
                                <p class="mt-1 text-sm leading-relaxed text-pretty text-zinc-800" x-text="activePin && activePin.body"></p>
                            </div>
                            <div class="rounded-lg bg-emerald-50/80 px-3 py-2 text-sm text-emerald-950"
                                x-show="activePin && activePin.resolution_note">
                                <span class="font-medium">Agent:</span>
                                <span x-text="activePin && activePin.resolution_note"></span>
                            </div>
                            <div x-show="activePin && activePin.after_screenshot_url">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-400">Before / after</p>
                                <div class="mt-1 grid grid-cols-2 gap-2">
                                    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                                        <p class="border-b border-zinc-100 px-2 py-1 text-[10px] font-medium text-zinc-500">Before</p>
                                        <div class="aspect-[4/3] max-h-28 bg-cover bg-top bg-no-repeat"
                                            :style="activePin && activePin.focus_preview && activePin.focus_preview.bg_style ? activePin.focus_preview.bg_style : ''"></div>
                                    </div>
                                    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                                        <p class="border-b border-zinc-100 px-2 py-1 text-[10px] font-medium text-zinc-500">After</p>
                                        <img class="aspect-[4/3] max-h-28 w-full object-cover object-top" :src="activePin && activePin.after_screenshot_url" alt="After">
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-3"
                                x-show="activePin && (activePin.comment_count > 0 || activePin.status === 'resolved' || activePin.status === 'verified')">
                                <template x-if="activePin && activePin.comment_count > 0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs text-zinc-500"
                                            x-text="activePin.comment_count + (activePin.comment_count === 1 ? ' comment' : ' comments')"></span>
                                        <button type="button"
                                            class="inline-flex h-8 items-center rounded-md bg-zinc-100 px-3 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80"
                                            @click="openComments()">View comments</button>
                                    </div>
                                </template>
                                <div class="ml-auto flex flex-wrap gap-2" x-show="activePin && (activePin.status === 'resolved' || activePin.status === 'verified')">
                                    <button type="button" class="inline-flex h-8 items-center rounded-md bg-accent px-3 text-xs font-medium text-accent-contrast transition hover:bg-accent-hover disabled:opacity-50"
                                        x-show="activePin && activePin.status === 'resolved'" :disabled="busy" @click="verifyMark(activePin, 'verify')">Verify</button>
                                    <button type="button" class="inline-flex h-8 items-center rounded-md bg-zinc-100 px-3 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80 disabled:opacity-50"
                                        :disabled="busy" @click="verifyMark(activePin, 'reopen')">Reopen</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Second-opinion finding note --}}
                    <div class="mt-3 rounded-2xl border border-zinc-200 bg-white p-3 shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]" x-show="activeFinding" x-cloak>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-6 min-w-6 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-1 text-[10px] font-semibold text-sky-700 shadow-sm"
                                    x-text="activeFinding && activeFinding.label"></span>
                                <span class="text-xs text-zinc-500" x-text="activeFinding && severityLabel(activeFinding.severity)"></span>
                            </div>
                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700" @click="activeFinding = null" aria-label="Close">×</button>
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-700" x-text="activeFinding && activeFinding.body"></p>
                    </div>

                    <p class="mt-2 text-xs text-zinc-400" x-show="isPending">Click a spot or drag a box on the screenshot to leave a mark. Click a numbered mark to read it.</p>

                    {{-- mark composer --}}
                    <div class="mt-3 rounded-xl border border-zinc-200/80 bg-zinc-50/90 px-3 py-2.5 sm:px-4" x-show="composer.open" @keydown.escape="closeComposer()">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="sev in severities" :key="sev.value">
                                <button type="button"
                                    class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-full border px-2.5 text-sm transition"
                                    :class="composer.severity === sev.value ? 'border-zinc-400 bg-white shadow-sm' : 'border-zinc-200 bg-zinc-50'"
                                    @click="composer.severity = sev.value">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="markerBg(sev.value)"></span>
                                    <span x-text="sev.label"></span>
                                </button>
                            </template>
                        </div>
                        <textarea x-model="composer.body" rows="3" placeholder="What should change here?"
                            class="mt-2 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-rose-500/30"></textarea>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex h-8 items-center rounded-md bg-accent px-3 text-sm font-medium text-accent-contrast transition hover:bg-accent-hover disabled:opacity-50"
                                :disabled="busy || !composer.body.trim()" @click="saveMark()">Add mark</button>
                            <button type="button" class="inline-flex h-8 items-center rounded-md bg-zinc-100 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200/80"
                                @click="closeComposer()">Cancel</button>
                        </div>
                    </div>

                    {{-- linear mark list (current pass) --}}
                    <div class="mt-3 flex flex-col gap-2" x-show="currentPins().length">
                        <template x-for="pin in currentPins()" :key="'l'+pin.id">
                            <button type="button"
                                class="rounded-xl border border-zinc-200 bg-white p-3 text-left shadow-sm transition hover:border-zinc-300 hover:shadow-md"
                                :class="activePin && activePin.id === pin.id ? 'border-zinc-400 ring-1 ring-zinc-300' : ''"
                                @click="showPin(pin)">
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold"
                                        :class="markerBg(pin.severity)" x-text="'M' + pin.number"></span>
                                    <span class="text-xs text-zinc-500" x-text="severityLabel(pin.severity)"></span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge(pin.status)" x-text="statusLabel(pin.status)"></span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600"
                                        x-show="pin.comment_count > 0" x-text="pin.comment_count"></span>
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700" x-text="pin.body"></p>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- BOARD VIEW --}}
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" x-show="view === 'board'">
                    <template x-for="col in boardColumns" :key="col.status">
                        <div class="flex min-h-[8rem] flex-col rounded-2xl border p-3 transition"
                            :class="col.status === 'in_progress' ? 'border-dashed border-zinc-200/90 bg-zinc-50/80' : 'border-zinc-200 bg-white/70'">
                            <div class="mb-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-start gap-2">
                                        <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100" aria-hidden="true">
                                            <svg x-show="col.status === 'open'" class="size-4 shrink-0 text-zinc-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path d="M2.75 2a.75.75 0 0 0-.75.75v10.5a.75.75 0 0 0 1.5 0v-2.624l.33-.083A6.044 6.044 0 0 1 8 11c1.29.645 2.77.807 4.17.457l1.48-.37a.462.462 0 0 0 .35-.448V3.56a.438.438 0 0 0-.544-.425l-1.287.322C10.77 3.808 9.291 3.646 8 3a6.045 6.045 0 0 0-4.17-.457l-.34.085A.75.75 0 0 0 2.75 2Z"/>
                                            </svg>
                                            <svg x-show="col.status === 'in_progress'" class="size-4 shrink-0 text-zinc-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path d="M6 6v4h4V6H6Z"/>
                                                <path fill-rule="evenodd" d="M5.75 1a.75.75 0 0 0-.75.75V3a2 2 0 0 0-2 2H1.75a.75.75 0 0 0 0 1.5H3v.75H1.75a.75.75 0 0 0 0 1.5H3v.75H1.75a.75.75 0 0 0 0 1.5H3a2 2 0 0 0 2 2v1.25a.75.75 0 0 0 1.5 0V13h.75v1.25a.75.75 0 0 0 1.5 0V13h.75v1.25a.75.75 0 0 0 1.5 0V13a2 2 0 0 0 2-2h1.25a.75.75 0 0 0 0-1.5H13v-.75h1.25a.75.75 0 0 0 0-1.5H13V6.5h1.25a.75.75 0 0 0 0-1.5H13a2 2 0 0 0-2-2V1.75a.75.75 0 0 0-1.5 0V3h-.75V1.75a.75.75 0 0 0-1.5 0V3H6.5V1.75A.75.75 0 0 0 5.75 1ZM11 4.5a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V5a.5.5 0 0 1 .5-.5h6Z" clip-rule="evenodd"/>
                                            </svg>
                                            <svg x-show="col.status === 'resolved'" class="size-4 shrink-0 text-zinc-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd"/>
                                            </svg>
                                            <svg x-show="col.status === 'verified'" class="size-4 shrink-0 text-zinc-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M8.5 1.709a.75.75 0 0 0-1 0 8.963 8.963 0 0 1-4.84 2.217.75.75 0 0 0-.654.72 10.499 10.499 0 0 0 5.647 9.672.75.75 0 0 0 .694-.001 10.499 10.499 0 0 0 5.647-9.672.75.75 0 0 0-.654-.719A8.963 8.963 0 0 1 8.5 1.71Zm2.34 5.504a.75.75 0 0 0-1.18-.926L7.394 9.17l-1.156-.99a.75.75 0 1 0-.976 1.138l1.75 1.5a.75.75 0 0 0 1.078-.106l2.75-3.5Z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-zinc-900" x-text="col.label"></p>
                                            <p class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-zinc-400" x-text="col.owner"></p>
                                        </div>
                                    </div>
                                    <span class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 px-2 text-sm font-semibold tabular-nums text-zinc-700"
                                        x-text="pinsByStatus(col.status).length"></span>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col gap-2">
                                <template x-for="pin in pinsByStatus(col.status)" :key="'b'+pin.id">
                                    <button type="button"
                                        class="rounded-xl border border-zinc-200 bg-white p-3 text-left shadow-sm transition hover:border-zinc-300 hover:shadow-md"
                                        :class="activePin && activePin.id === pin.id ? 'border-zinc-400 ring-1 ring-zinc-300' : ''"
                                        @click="showPin(pin)">
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold"
                                                :class="markerBg(pin.severity)" x-text="'M' + pin.number"></span>
                                            <span class="text-xs text-zinc-500" x-text="severityLabel(pin.severity)"></span>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge(pin.status)" x-text="statusLabel(pin.status)"></span>
                                            <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-500"
                                                x-show="pin._from_parent" x-text="'P' + pin._pass"></span>
                                            <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600"
                                                x-show="pin.comment_count > 0" x-text="pin.comment_count"></span>
                                        </div>
                                        <p class="text-sm leading-relaxed text-zinc-700" x-text="pin.body"></p>
                                    </button>
                                </template>
                                <p class="rounded-xl border border-dashed border-zinc-200 px-3 py-6 text-center text-xs text-zinc-400"
                                    x-show="!pinsByStatus(col.status).length" x-text="col.empty"></p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Shared mark detail when opened from board (screenshot view has its own copy above) --}}
                <div class="mt-3" x-show="view === 'board' && activePin" x-cloak>
                    <template x-if="view === 'board' && activePin">
                        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]">
                            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-3 py-3 sm:px-4">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <span class="flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-[11px] font-semibold"
                                        :class="markerBg(activePin.severity)" x-text="'M' + activePin.number"></span>
                                    <span class="text-xs text-zinc-500" x-text="severityLabel(activePin.severity)"></span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge(activePin.status)" x-text="statusLabel(activePin.status)"></span>
                                </div>
                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700" @click="closeDetail()" aria-label="Close">×</button>
                            </div>
                            <template x-if="activePin.focus_preview && activePin.focus_preview.bg_style">
                                <div class="w-full max-h-[min(40dvh,22rem)] overflow-hidden border-b border-zinc-100 bg-zinc-100">
                                    <div class="relative w-full bg-zinc-100 bg-no-repeat"
                                        :style="'aspect-ratio:' + Math.max(activePin.focus_preview.ratio || 1.6, 0.01) + ';' + activePin.focus_preview.bg_style"
                                        role="img" :aria-label="'Cropped screenshot focused on mark M' + activePin.number">
                                        <template x-if="activePin.focus_preview.overlay">
                                            <div class="pointer-events-none absolute rounded-md border-2 border-rose-500 bg-rose-500/15"
                                                :style="rectStyle(activePin.focus_preview.overlay)"></div>
                                        </template>
                                        <template x-if="activePin.focus_preview.point">
                                            <span class="pointer-events-none absolute flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-[10px] font-semibold shadow ring-2 ring-white"
                                                :class="markerBg(activePin.severity)"
                                                :style="pinStyle(activePin.focus_preview.point)"
                                                x-text="'M' + activePin.number"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <div class="space-y-3 px-3 py-3 sm:px-4">
                                <p class="text-sm leading-relaxed text-pretty text-zinc-800" x-text="activePin.body"></p>
                                <div class="rounded-lg bg-emerald-50/80 px-3 py-2 text-sm text-emerald-950" x-show="activePin.resolution_note">
                                    <span class="font-medium">Agent:</span> <span x-text="activePin.resolution_note"></span>
                                </div>
                                <div class="overflow-hidden rounded-lg border border-zinc-200" x-show="activePin.after_screenshot_url">
                                    <p class="border-b border-zinc-100 px-2 py-1 text-[10px] font-medium text-zinc-500">After</p>
                                    <img class="max-h-40 w-full object-contain object-top" :src="activePin.after_screenshot_url" alt="After">
                                </div>
                                <div class="flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-3">
                                    <template x-if="activePin.comment_count > 0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs text-zinc-500" x-text="activePin.comment_count + (activePin.comment_count === 1 ? ' comment' : ' comments')"></span>
                                            <button type="button" class="inline-flex h-8 items-center rounded-md bg-zinc-100 px-3 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80" @click="openComments()">View comments</button>
                                        </div>
                                    </template>
                                    <div class="ml-auto flex flex-wrap gap-2" x-show="activePin.status === 'resolved' || activePin.status === 'verified'">
                                        <button type="button" class="inline-flex h-8 items-center rounded-md bg-accent px-3 text-xs font-medium text-accent-contrast transition hover:bg-accent-hover disabled:opacity-50"
                                            x-show="activePin.status === 'resolved'" :disabled="busy" @click="verifyMark(activePin, 'verify')">Verify</button>
                                        <button type="button" class="inline-flex h-8 items-center rounded-md bg-zinc-100 px-3 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80 disabled:opacity-50"
                                            :disabled="busy" @click="verifyMark(activePin, 'reopen')">Reopen</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- decision bar --}}
                <div class="mt-4 rounded-xl border border-zinc-200/80 bg-zinc-50/90 px-3 py-2.5 sm:px-4" x-show="isPending">
                    <input type="text" x-model="decisionNote" placeholder="Optional note for the agent…"
                        class="h-8 w-full rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-rose-500/30">
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" class="inline-flex h-8 items-center rounded-md bg-accent px-3 text-sm font-medium text-accent-contrast transition hover:bg-accent-hover disabled:opacity-50"
                            :disabled="busy" @click="decide('approved')">Approve</button>
                        <button type="button" class="inline-flex h-8 items-center rounded-md bg-zinc-100 px-3 text-sm font-medium text-zinc-800 transition hover:bg-zinc-200/80 disabled:opacity-50"
                            :disabled="busy" @click="decide('changes_requested')">Request changes</button>
                        <button type="button" class="inline-flex h-8 items-center rounded-md px-3 text-sm font-medium text-zinc-500 transition hover:text-zinc-800"
                            @click="openFullReview()">Open full review</button>
                    </div>
                </div>

                <div class="mt-3" x-show="!isPending">
                    <button type="button" class="inline-flex h-8 items-center rounded-md bg-zinc-100 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200/80"
                        @click="openFullReview()">Open full review</button>
                </div>

                <p class="mt-2 text-sm text-rose-600" x-show="error" x-text="error"></p>
            </div>
        </template>
    </div>
</div>

<script>
    // Minimal MCP Apps bridge: JSON-RPC 2.0 over window.parent.postMessage.
    // Hand-rolled (spec-blessed) so the resource stays self-contained.
    (function () {
        const pending = new Map();
        let nextId = 1;
        let onToolResult = null;
        let lastToolResult = null; // buffered so a result pushed before the UI mounts isn't lost

        function send(msg) { window.parent.postMessage(msg, '*'); }

        function request(method, params) {
            const id = nextId++;
            send({ jsonrpc: '2.0', id, method, params: params || {} });
            return new Promise((resolve, reject) => pending.set(id, { resolve, reject }));
        }

        window.addEventListener('message', (event) => {
            if (event.source !== window.parent) return;
            const msg = event.data;
            if (!msg || msg.jsonrpc !== '2.0') return;

            // Response to one of our requests.
            if (msg.id != null && (('result' in msg) || ('error' in msg))) {
                const p = pending.get(msg.id);
                if (!p) return;
                pending.delete(msg.id);
                if ('error' in msg) p.reject(new Error(msg.error?.message || 'Host error'));
                else p.resolve(msg.result);
                return;
            }

            // Notifications from the host.
            if (msg.method === 'ui/notifications/tool-result') {
                lastToolResult = msg.params || {};
                if (onToolResult) onToolResult(lastToolResult);
                return;
            }

            // Requests from the host (e.g. teardown) — acknowledge.
            if (msg.id != null && msg.method) {
                send({ jsonrpc: '2.0', id: msg.id, result: {} });
            }
        });

        async function connect() {
            await request('ui/initialize', {
                appInfo: { name: 'ReviseMy review', version: @json(config('revisemy.version')) },
                appCapabilities: { availableDisplayModes: ['inline'] },
            });
            send({ jsonrpc: '2.0', method: 'ui/notifications/initialized', params: {} });
        }

        window.mcpBridge = {
            connect,
            set ontoolresult(fn) { onToolResult = fn; if (fn && lastToolResult) fn(lastToolResult); },
            callTool: (name, args) => request('tools/call', { name, arguments: args || {} }),
            openLink: (url) => request('ui/open-link', { url }),
        };

        connect().catch((e) => console.error('[ReviseMy] MCP connect failed', e));
    })();

    function reviewApp() {
        // Keep in sync with Annotation::markerClass(), severityLabels(), statusBadgeClass(),
        // statusLabels(), and boardColumnMeta().
        const MARKER_BG = {
            'must-fix': 'bg-accent text-ink', 'nit': 'bg-accent text-ink', 'question': 'bg-accent text-ink', 'keep': 'bg-accent text-ink',
            'wording': 'bg-accent text-ink', 'spacing': 'bg-accent text-ink', 'size': 'bg-accent text-ink', 'color': 'bg-accent text-ink', 'alignment': 'bg-accent text-ink',
        };
        const SEVERITY_LABELS = {
            'must-fix': 'Must fix', 'nit': 'Nice to have', 'question': 'Question', 'keep': 'Keep this',
            'wording': 'Wording', 'spacing': 'Spacing', 'size': 'Size', 'color': 'Color', 'alignment': 'Alignment',
        };
        const STATUS_BADGE = {
            'in_progress': 'bg-sky-100 text-sky-800', 'resolved': 'bg-amber-100 text-amber-800',
            'verified': 'bg-emerald-100 text-emerald-800', 'open': 'bg-zinc-100 text-zinc-600',
        };
        const STATUS_LABELS = { 'open': 'Open', 'in_progress': 'In progress', 'resolved': 'Resolved', 'verified': 'Verified' };

        return {
            payload: null,
            view: 'screenshot',
            activeIndex: 0,
            activePin: null,
            activeFinding: null,
            busy: false,
            error: '',
            decisionNote: '',
            draft: { drawing: false, x0: 0, y0: 0, x: 0, y: 0, w: 0, h: 0 },
            composer: { open: false, x: 0, y: 0, area: null, severity: 'must-fix', body: '' },
            severities: [
                { value: 'must-fix', label: 'Must fix' },
                { value: 'nit', label: 'Nice to have' },
                { value: 'question', label: 'Question' },
                { value: 'keep', label: 'Keep this' },
            ],
            // Empty copy mirrors Annotation::boardColumnMeta()
            boardColumns: [
                { status: 'open', label: 'Open', owner: 'You', empty: 'Drop to reopen' },
                { status: 'in_progress', label: 'In progress', owner: 'Agent', empty: 'Agent starts fixes here' },
                { status: 'resolved', label: 'Resolved', owner: 'You or agent', empty: 'Drop to mark resolved' },
                { status: 'verified', label: 'Verified', owner: 'You', empty: 'Drop to verify' },
            ],

            init() {
                window.mcpBridge.ontoolresult = (params) => {
                    const data = params.structuredContent;
                    if (data && data.id) this.apply(data);
                };

                setInterval(() => {
                    if (!this.payload || this.busy) return;
                    if (this.payload.status === 'pending' || this.payload.status === 'changes_requested') {
                        this.refresh();
                    }
                }, 12000);
            },

            apply(data) {
                this.payload = data;
                if (this.activeIndex >= data.screenshots.length) this.activeIndex = 0;
                this.error = '';
                if (this.activePin) {
                    const refreshed = this.boardPins().find((p) => p.id === this.activePin.id);
                    this.activePin = refreshed || null;
                }
            },

            get isPending() { return this.payload && this.payload.status === 'pending'; },

            markerBg(severity) { return MARKER_BG[severity] || 'bg-accent text-ink'; },
            severityLabel(severity) { return SEVERITY_LABELS[severity] || severity; },
            statusBadge(status) { return STATUS_BADGE[status] || 'bg-zinc-100 text-zinc-600'; },
            statusLabel(status) { return STATUS_LABELS[status] || status; },
            isSettled(pin) { return pin.status === 'resolved' || pin.status === 'verified'; },

            countChips() {
                const l = this.payload.loop;
                return [
                    { label: 'Outstanding', value: l.outstanding_count },
                    { label: 'Must-fix', value: l.must_fix_count },
                    { label: 'Nits', value: l.nit_count },
                    { label: 'Questions', value: l.question_count },
                    { label: 'Keep', value: l.keep_count },
                    { label: 'Resolved', value: l.resolved_count },
                    { label: 'Verified', value: l.verified_count },
                ].filter((chip) => chip.value > 0 || ['Outstanding', 'Verified'].includes(chip.label));
            },

            verifiedPct() {
                const total = this.boardPins().length;
                return total ? Math.round((this.payload.loop.verified_count / total) * 100) : 0;
            },

            currentPins() {
                if (!this.payload) return [];
                return this.payload.screenshots.flatMap((s) =>
                    (s.pins || []).map((p) => ({ ...p, _pass: this.payload.pass, _from_parent: false }))
                );
            },

            boardPins() {
                if (!this.payload) return [];
                const current = this.currentPins();
                const parentPass = this.payload.previous_pass;
                const parent = parentPass && Array.isArray(parentPass.marks)
                    ? parentPass.marks.map((p) => ({
                        ...p,
                        _pass: parentPass.pass,
                        _from_parent: true,
                    }))
                    : [];
                return [...parent, ...current].sort((a, b) => {
                    if (a._pass !== b._pass) return a._pass - b._pass;
                    return a.number - b.number;
                });
            },

            pinsByStatus(status) { return this.boardPins().filter((p) => p.status === status); },

            showPin(pin) {
                this.activeFinding = null;
                this.activePin = this.activePin && this.activePin.id === pin.id ? null : pin;
            },

            showFinding(finding, index) {
                this.activePin = null;
                const key = 's' + index;
                this.activeFinding = this.activeFinding && this.activeFinding.key === key ? null : {
                    key, label: 'S' + (index + 1), severity: finding.severity, body: finding.body,
                };
            },

            closeDetail() {
                this.activePin = null;
                this.activeFinding = null;
            },

            openComments() {
                const url = this.payload.board_url || this.payload.review_url;
                if (url) window.mcpBridge.openLink(url);
            },

            shotLabel(shot, i) {
                const v = shot.meta && shot.meta.viewport;
                const page = shot.meta && shot.meta.page;
                if (page) return 'Page ' + page;
                if (v) return v.charAt(0).toUpperCase() + v.slice(1);
                return 'Shot ' + (i + 1);
            },

            setActive(i) { this.activeIndex = i; this.closeComposer(); this.closeDetail(); },
            activeShot() { return this.payload ? this.payload.screenshots[this.activeIndex] : null; },

            pinStyle(p) { return `left:${p.x * 100}%; top:${p.y * 100}%;`; },
            rectStyle(a) { return `left:${a.x * 100}%; top:${a.y * 100}%; width:${a.w * 100}%; height:${a.h * 100}%;`; },
            draftRectStyle() {
                const d = this.draft;
                const x = Math.min(d.x0, d.x), y = Math.min(d.y0, d.y);
                return `left:${x * 100}%; top:${y * 100}%; width:${Math.abs(d.x - d.x0) * 100}%; height:${Math.abs(d.y - d.y0) * 100}%;`;
            },

            norm(e) {
                const r = this.$refs.overlay.getBoundingClientRect();
                return {
                    x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)),
                    y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)),
                };
            },

            startDraw(e) {
                if (!this.isPending) return;
                const p = this.norm(e);
                this.draft = { drawing: true, x0: p.x, y0: p.y, x: p.x, y: p.y, w: 0, h: 0 };
            },
            moveDraw(e) {
                if (!this.draft.drawing) return;
                const p = this.norm(e);
                this.draft.x = p.x; this.draft.y = p.y;
                this.draft.w = Math.abs(p.x - this.draft.x0);
                this.draft.h = Math.abs(p.y - this.draft.y0);
            },
            endDraw(e) {
                if (!this.draft.drawing) return;
                const p = this.norm(e);
                const w = Math.abs(p.x - this.draft.x0), h = Math.abs(p.y - this.draft.y0);
                if (w >= 0.02 && h >= 0.02) {
                    const x = Math.min(p.x, this.draft.x0), y = Math.min(p.y, this.draft.y0);
                    this.openComposer(x + w / 2, y + h / 2, { x, y, w, h });
                } else {
                    this.openComposer(p.x, p.y, null);
                }
                this.draft.drawing = false;
            },
            cancelDraw() { this.draft.drawing = false; },

            openComposer(x, y, area) {
                this.closeDetail();
                this.composer = { open: true, x, y, area, severity: 'must-fix', body: '' };
            },
            closeComposer() { this.composer.open = false; this.composer.body = ''; },

            async saveMark() {
                const shot = this.activeShot();
                if (!shot || !this.composer.body.trim()) return;
                await this.run(() => window.mcpBridge.callTool('add_mark', {
                    review_id: this.payload.id,
                    screenshot_id: shot.id,
                    x: this.composer.x,
                    y: this.composer.y,
                    area: this.composer.area || undefined,
                    severity: this.composer.severity,
                    body: this.composer.body.trim(),
                }));
                this.closeComposer();
            },

            async verifyMark(pin, action) {
                await this.run(() => window.mcpBridge.callTool('verify_mark', {
                    review_id: this.payload.id, mark_id: pin.id, action,
                }));
            },

            async decide(decision) {
                await this.run(() => window.mcpBridge.callTool('decide_review', {
                    review_id: this.payload.id, decision, note: this.decisionNote.trim() || undefined,
                }));
                this.decisionNote = '';
            },

            async refresh() {
                await this.run(() => window.mcpBridge.callTool('get_review', { id: this.payload.id }));
            },

            openFullReview() {
                if (this.payload) window.mcpBridge.openLink(this.payload.review_url);
            },

            async run(fn) {
                this.busy = true; this.error = '';
                try {
                    const result = await fn();
                    const data = result && result.structuredContent;
                    if (data && data.id) this.apply(data);
                } catch (e) {
                    this.error = e.message || 'Something went wrong. Try the full review page.';
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
