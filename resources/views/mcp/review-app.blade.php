{{-- Inline MCP App UI for ReviseMy reviews. Rendered in a sandboxed iframe by
     MCP Apps hosts (Claude web/desktop, etc.).

     Parity with web review/board (keep in sync in the same PR as chrome changes):
     - Marker / status / severity maps ↔ Annotation::markerClass, statusBadgeClass, severityLabels
     - Board columns / empty copy ↔ Annotation::boardColumnMeta
     - Mark focus crop via pin.focus_preview ↔ MarkFocus + mark-focus-preview
     - Control height h-8 ↔ Flux size="sm" on web
     - Agent pill (x-agent-tag → x-who-pill) on notes, statuses the agent set,
       after images, replies and agent findings ↔ the same component on the web
     - Mark timeline (steps + comments, who did each) ↔ App\Support\MarkTimeline,
       components/mark-timeline; actor pills ↔ Annotation::ACTOR_PILLS
     - Element picking: hover outline, click snaps, Alt-click for a spot, drag
       matches, Snap toggle, Wider, Inspect ↔ resources/js/element-pick.js (shared),
       ElementTarget, components/element-chip + element-inspect; chip colour
       ↔ Annotation::ELEMENT_CHIP
     Intentionally web-only: guest-link settings, drag columns, second-opinion
     accept/dismiss, pan, editable title.

     Styles and Alpine are compiled from resources/css/mcp-app.css and
     resources/js/mcp-app.js (the same tokens as the site) and inlined by
     App\Mcp\Resources\ReviewApp; nothing loads from a CDN. The bridge is inline.
     App-only tools: add_mark / add_comment / set_comment_reaction / decide_review / verify_mark /
     get_shot_elements. --}}
<style>{!! $styles !!}</style>

<div class="bg-background text-foreground" x-data="reviewApp()" x-init="init()" :style="safeAreaStyle()" x-cloak>
    <div class="mx-auto max-w-5xl px-4 py-4 sm:px-6">
        <template x-if="!payload">
            {{-- Before the result: what's being made, from the call's arguments
                 (ui/notifications/tool-input), so a 20–60 second capture isn't a
                 blank spinner. Then the error, if the call failed. --}}
            <div class="py-2" role="status" aria-live="polite">
                <template x-if="failed">
                    <div>
                        <p class="text-sm font-medium text-zinc-900" x-text="failedTitle"></p>
                        <p class="mt-1 text-sm text-pretty text-zinc-500" x-text="failed"></p>
                    </div>
                </template>
                <template x-if="!failed">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="size-2 shrink-0 animate-pulse rounded-full" :class="workingDot()"></span>
                            <p class="text-sm font-medium text-zinc-900" x-text="working.title"></p>
                            <span class="ml-auto text-xs tabular-nums text-zinc-500" x-show="elapsed >= 3" x-text="elapsedLabel()"></span>
                        </div>
                        <p class="mt-1 text-sm text-pretty text-zinc-500" x-show="working.detail" x-text="working.detail"></p>
                    </div>
                </template>
            </div>
        </template>

        {{-- An older copy of this review in the chat: fold away, point down. --}}
        <template x-if="payload && superseded">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-card px-3 py-2.5">
                <p class="min-w-0 text-sm text-zinc-700"><span class="font-medium text-zinc-900" x-text="payload.title"></span> continues further down the chat.</p>
                <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition hover:bg-chip-hover" @click="openFullReview()">Open full review</button>
            </div>
        </template>
        <template x-if="payload && !superseded">
            <div>
                {{-- header: title + chips, matching the review page header line --}}
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                    <h1 class="min-w-0 truncate text-lg font-semibold tracking-tight text-zinc-900" x-text="payload.title"></h1>
                    <span class="text-xs text-muted-foreground" x-show="payload.pass > 1" x-text="'Pass ' + payload.pass"></span>
                    {{-- The review's state, in the same tones as the web header (Annotation::TONES). --}}
                    <template x-if="reviewTone()">
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-4" :class="tone(reviewTone()[0]).tag"><span class="size-1.5 shrink-0 rounded-full" :class="tone(reviewTone()[0]).dot" aria-hidden="true"></span><span x-text="reviewTone()[1]"></span></span>
                    </template>
                </div>

                {{-- View controls and sharing --}}
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <div class="inline-flex rounded-full bg-trough p-0.5">
                        <button type="button" class="h-8 rounded-full px-3 text-xs font-medium transition-colors"
                            :class="view === 'screenshot' ? 'bg-raised text-zinc-900 shadow-xs' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'screenshot'; reviewingFixes = false">Screenshot</button>
                        <button type="button" class="h-8 rounded-full px-3 text-xs font-medium transition-colors"
                            :class="view === 'board' ? 'bg-raised text-zinc-900 shadow-xs' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'board'" x-text="'Board · ' + boardPins().length"></button>
                    </div>
                    <button type="button" class="ml-auto inline-flex h-8 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover disabled:opacity-50"
                        :disabled="busy" :aria-expanded="shareOpen.toString()" aria-controls="inline-share" @click="toggleShare()">Share</button>
                    <button type="button" class="inline-flex size-8 items-center justify-center rounded-full text-zinc-600 transition-colors hover:bg-chip disabled:opacity-50"
                        :disabled="busy" @click="refresh()" aria-label="Refresh" title="Refresh">
                        <flux:icon.arrow-path variant="micro" class="size-4" />
                    </button>
                    <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition hover:bg-chip-hover"
                        x-show="canFullscreen" @click="toggleFullscreen()"
                        x-text="displayMode === 'fullscreen' ? 'Back to the chat' : 'Full screen'"></button>
                </div>

                @include('mcp.partials.share')

                {{-- SCREENSHOT VIEW --}}
                <div class="mt-3" x-show="view === 'screenshot'">
                    {{-- Which shot: the same segmented control as the review page header. --}}
                    <div class="mb-2 inline-flex max-w-full items-center gap-0.5 overflow-x-auto rounded-xl bg-chip p-0.5" role="tablist" aria-label="Screenshots" x-show="payload.screenshots.length > 1">
                        <template x-for="(shot, i) in payload.screenshots" :key="shot.id">
                            <button type="button" role="tab" class="h-7 shrink-0 whitespace-nowrap rounded-[0.6rem] px-2.5 text-xs font-medium transition-colors"
                                :class="i === activeIndex ? 'bg-raised text-zinc-900 shadow-xs ring-1 ring-black/[0.06]' : 'text-zinc-500 hover:text-zinc-900'"
                                :aria-selected="(i === activeIndex).toString()"
                                @click="setActive(i)" x-text="shotLabel(shot, i)"></button>
                        </template>
                    </div>

                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs"
                        x-show="activeShot()?.capture_quality || activeShot()?.comparison || activeShot()?.meta?.elements_source === 'separate_load' || (payload.screenshots.length === 1 && activeShot()?.meta?.state)">
                        <div role="status" class="text-muted-foreground">
                            <span class="font-semibold" x-show="payload.screenshots.length === 1" x-text="activeShot()?.meta?.state || ''"></span>
                            <span x-text="({ failed: 'Capture failed', partial: 'Partial capture', unverified: 'Capture unverified', ready: 'Capture ready' })[activeShot()?.capture_quality?.status] || ''"></span>
                            <span x-text="activeShot()?.capture_quality?.message || ''"></span>
                            <span x-show="activeShot()?.meta?.elements_source === 'separate_load'">Element positions came from a separate page load. Mark an area directly.</span>
                        </div>
                        <button type="button" class="link font-medium" x-show="activeShot()?.comparison"
                            @click="comparing = !comparing; closeComposer(); closeDetail()"
                            :aria-pressed="comparing.toString()"
                            x-text="comparing ? 'Back to screenshot' : 'Compare with previous pass'"></button>
                    </div>
                    <template x-if="comparing && activeShot()?.comparison">
                        <div class="max-h-[min(70dvh,36rem)] overflow-auto rounded-2xl bg-well p-3">
                            <p class="mb-2 text-xs text-problem-ink" x-show="activeShot().comparison.warning" x-text="activeShot().comparison.warning"></p>
                            <div class="grid grid-cols-2 items-start gap-3">
                                <figure class="min-w-0">
                                    <figcaption class="sticky top-0 bg-well py-1 text-xs font-medium">Previous pass</figcaption>
                                    <img class="block h-auto w-full rounded" :src="activeShot().comparison.url" alt="Previous pass screenshot">
                                </figure>
                                <figure class="min-w-0">
                                    <figcaption class="sticky top-0 bg-well py-1 text-xs font-medium">This pass</figcaption>
                                    <img class="block h-auto w-full rounded" :src="activeShot().url" alt="This pass screenshot">
                                </figure>
                            </div>
                        </div>
                    </template>

                    {{-- Canvas first, as on the review page: the brief floats over the
                         corner and the fit modes sit bottom right. --}}
                    <div class="relative" x-show="activeShot() && activeShot().capture_quality?.status !== 'failed' && !comparing" @resize.window.debounce.100ms="layoutTick++">
                    <div class="pointer-events-auto absolute left-3 top-3 z-30 w-[min(18rem,calc(100%-1.5rem))] rounded-2xl bg-lift p-3 shadow-[0_10px_30px_-12px_rgba(0,0,0,0.25)] ring-1 ring-black/[0.07]"
                        x-show="(payload.context || payload.taste?.label) && brief" x-cloak>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <h2 class="text-base font-semibold tracking-tight text-zinc-900">What to look at</h2>
                            <button type="button" class="text-xs font-medium text-zinc-500 transition-colors hover:text-zinc-900" @click="brief = false">Hide</button>
                        </div>
                        <p class="max-h-32 overflow-y-auto text-sm leading-relaxed text-pretty text-zinc-700" x-show="payload.context" x-text="payload.context"></p>
                        <span class="relative mt-2 inline-flex" x-data="{ tasteOpen: false }" x-show="payload.taste && payload.taste.label">
                            <button type="button"
                                class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-800"
                                @click="tasteOpen = ! tasteOpen"
                                x-text="payload.taste.label"></button>
                            <div class="absolute left-0 z-40 mt-8 w-64 rounded-xl bg-lift p-3 text-left shadow-lg ring-1 ring-black/[0.07]"
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
                                <p class="mt-3 text-xs leading-relaxed text-zinc-400"
                                    x-text="payload.taste.disclaimer"></p>
                            </div>
                        </span>
                    </div>
                    <div class="absolute bottom-3 right-3 z-30 flex items-center gap-0.5 rounded-full bg-raised/95 p-0.5 shadow-md shadow-black/[0.06] ring-1 ring-black/[0.07]">
                        <button type="button" class="h-7 rounded-full px-2.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-chip"
                            x-show="(payload.context || payload.taste?.label) && !brief" @click="brief = true">Brief</button>
                        <button type="button" class="flex h-7 items-center gap-1 rounded-full px-2.5 text-xs font-medium transition-colors"
                            x-show="isPending && elementsFor(activeShot())"
                            :class="pickOn ? 'bg-foreground text-background' : 'text-zinc-600 hover:bg-chip'"
                            :aria-pressed="pickOn.toString()" @click="togglePick()"
                            title="When on, a click marks the whole part of the page under it">Snap</button>
                        <select aria-label="Zoom preset" :value="fit" @change="setFit($event.target.value)"
                            class="h-7 rounded-full bg-transparent px-2.5 text-xs font-medium text-zinc-700 hover:bg-chip">
                            <option value="width">Fit width</option>
                            <option value="page">Whole page</option>
                            <option value="actual">Actual size</option>
                        </select>
                    </div>
                    <button type="button" data-mark-toggle
                        class="absolute bottom-3 left-3 z-30 inline-flex h-11 items-center gap-1.5 rounded-full px-4 text-sm font-semibold shadow-md shadow-black/[0.12] transition-colors"
                        x-show="coarse && isPending" x-cloak
                        :class="marking ? 'bg-foreground text-background' : 'bg-key text-accent-contrast'"
                        :aria-pressed="marking.toString()"
                        @click="marking = ! marking; hover = null; if (marking) closeDetail()"
                        x-text="marking ? 'Cancel' : 'Mark'"></button>
                    <div class="relative max-h-[min(70dvh,36rem)] overflow-auto overscroll-contain rounded-2xl bg-well p-3 pb-14"
                        x-ref="shotBox">
                        <div class="relative w-full rounded-lg bg-raised shadow-[0_1px_3px_rgba(0,0,0,0.06),0_8px_24px_rgba(0,0,0,0.05)]" x-show="activeShot()" :style="shotFrameStyle(activeShot())">
                            <img class="block w-full rounded-lg" :src="activeShot()?.url" :alt="payload.title" draggable="false">
                            <div class="absolute inset-0 cursor-crosshair" x-ref="overlay"
                                :class="marking ? 'touch-none ring-2 ring-key/60 rounded-lg [&_button]:pointer-events-none' : 'touch-pan-y'"
                                @pointerdown="startDraw($event)" @pointermove="moveDraw($event)"
                                @pointerup="endDraw($event)" @pointercancel="cancelDraw()" @pointerleave="cancelDraw(); hover = null">

                                {{-- human marks: key-coloured region + M# badge (review page classes) --}}
                                <template x-for="pin in (activeShot()?.pins || [])" :key="'p'+pin.id">
                                    <div>
                                        <div class="pointer-events-none absolute rounded-md border-2 border-key/80 bg-key/10"
                                            x-show="pin.area"
                                            :class="{ 'opacity-50': isSettled(pin) }" :style="pin.area ? rectStyle(pin.area) : ''"></div>
                                        <button type="button"
                                            class="pointer-events-auto absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full px-1 text-xs font-semibold shadow-lg ring-2 ring-white transition before:absolute before:-inset-2 before:content-['']"
                                            :class="markerBg(pin.severity) + (isSettled(pin) ? ' opacity-60' : '') + (activePin && activePin.id === pin.id ? ' ring-zinc-900' : '')"
                                            :style="pinStyle(pin)" x-text="'M' + pin.number"
                                            @pointerdown.stop @pointerup.stop @click.stop="showPin(pin)"></button>
                                    </div>
                                </template>

                                {{-- second-opinion region hints (numbered list shared with findings strip) --}}
                                <template x-for="item in numberedRegionFindings()" :key="item.key">
                                    <div class="pointer-events-none absolute z-[5]" :style="rectStyle(item.finding.area)">
                                        <div class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-sky-400/80 bg-sky-400/10"></div>
                                        <button type="button"
                                            class="pointer-events-auto absolute -left-2 -top-2 z-[6] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-0.5 text-xs font-semibold text-sky-700 shadow-sm transition"
                                            :class="activeFinding && activeFinding.key === item.key ? 'ring-2 ring-sky-300' : ''"
                                            x-text="'S' + item.number"
                                            @pointerdown.stop @pointerup.stop @click.stop="showFinding(item.finding)"></button>
                                    </div>
                                </template>

                                {{-- the element a click would mark (as on the page) --}}
                                <div class="pointer-events-none absolute z-[16] rounded-sm bg-key/5 ring-2 ring-key/70"
                                    x-show="hover !== null" :style="hoverStyle()" data-element-hover>
                                    <span class="absolute bottom-full left-0 mb-1 max-w-[min(20rem,90vw)] truncate rounded-md bg-foreground px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap text-background shadow-sm"
                                        x-text="hoverLabel()"></span>
                                </div>

                                {{-- draft rectangle / pending composer pin (dashed key, like the page) --}}
                                <div class="pointer-events-none absolute z-[15] rounded-md border-2 border-dashed border-key bg-key/15"
                                    x-show="draft.drawing && draft.w > 0.01" :style="draftRectStyle()"></div>
                                <div class="pointer-events-none absolute z-[18] rounded-md border-2 border-dashed border-key bg-key/15"
                                    x-show="composer.open && composer.area" :style="composer.area ? rectStyle(composer.area) : ''"></div>
                                <div class="pointer-events-none absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-accent text-xs font-semibold text-ink shadow-lg ring-2 ring-white"
                                    x-show="composer.open && !composer.area" :style="pinStyle(composer)">+</div>
                            </div>
                        </div>
                    </div>

                    </div>

                    <p class="mt-2 text-xs text-sky-700" x-show="activeShot() && activeShot().second_opinion_status === 'queued'">
                        Generating second-opinion hints…
                    </p>

                    {{-- Mark detail --}}
                    <div x-show="activePin" x-cloak>@include('mcp.partials.mark-detail', ['detailView' => 'screenshot'])</div>

                    {{-- Second-opinion finding note --}}
                    <div class="mt-3 rounded-2xl bg-card p-3 shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]" x-show="activeFinding" x-cloak>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-6 min-w-6 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-1 text-xs font-semibold text-sky-700 shadow-sm"
                                    x-text="activeFinding && activeFinding.label"></span>
                                <span class="text-xs text-zinc-500" x-text="activeFinding && severityLabel(activeFinding.severity)"></span>
                            </div>
                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700" @click="activeFinding = null" aria-label="Close">×</button>
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-700" x-text="activeFinding && activeFinding.body"></p>
                    </div>

                    <p class="mt-2 text-center text-xs text-muted-foreground" x-text="!isPending ? 'Click a numbered mark to read it.' : (elementsFor(activeShot()) ? 'Click a part to mark it, drag for an area, or Alt-click for a spot.' : 'Drag to mark a region, or click for a point.')"></p>

                    {{-- mark composer --}}
                    <div class="mt-3 rounded-2xl bg-card px-3 py-3 sm:px-4" x-show="composer.open" @keydown.escape="closeComposer()">
                        {{-- What the mark is tied to; your agent gets the same. --}}
                        <div class="mb-2 space-y-1.5" x-show="composer.target" data-pending-element>
                            <div class="flex items-center gap-1">
                                @include('mcp.partials.element-chip', ['source' => 'composer.target', 'class' => 'min-w-0 flex-1', 'quiet' => true])
                                <button type="button" class="h-6 shrink-0 rounded-md px-1.5 text-[11px] font-medium text-zinc-500 transition hover:bg-chip hover:text-zinc-900"
                                    x-show="composer.target && composer.target.parent !== null && composer.element !== null" @click="widen()" title="Mark the part around it instead">Wider</button>
                                <button type="button" class="h-6 shrink-0 rounded-md px-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-chip hover:text-zinc-900"
                                    @click="dropElement()" title="Keep the marked area and detach the element">Use area</button>
                            </div>
                            @include('mcp.partials.element-inspect', ['source' => 'composer.target', 'references' => 'composer.references'])
                        </div>
                        <div class="overflow-hidden rounded-xl border border-input bg-background focus-within:ring-2 focus-within:ring-key/40">
                            @include('mcp.partials.element-reference-chips', ['references' => 'composer.references', 'removable' => true])
                            <textarea x-model="composer.body" rows="3" aria-label="Mark note" placeholder="What should change here?"
                                @input="if (!composer.manualType) composer.severity = window.rmSuggestMarkType($event.target.value)"
                                class="block w-full resize-y border-0 bg-transparent px-3 py-2 text-sm text-zinc-800 outline-none placeholder:text-zinc-500"></textarea>
                            <div class="flex items-center px-2 pb-2">
                                <x-mark-type-picker x-model="composer.severity" x-on:change="composer.manualType = true" />
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex h-8 items-center btn-lit rounded-full px-3 text-sm font-medium disabled:opacity-50"
                                :disabled="busy || !composer.body.trim()" @click="saveMark()">Add mark</button>
                            <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-sm font-medium text-zinc-700 transition hover:bg-chip-hover"
                                @click="closeComposer()">Cancel</button>
                        </div>
                        <p class="mt-2 text-sm text-problem-ink" x-show="error" x-text="error"></p>
                    </div>

                    {{-- Marks on this pass --}}
                    <div class="mt-3 flex flex-col gap-2" x-show="currentPins().length">
                        <template x-for="pin in currentPins()" :key="'l'+pin.id">
                            @include('mcp.partials.mark-row')
                        </template>
                    </div>

                    {{-- The pass before, paler and folded --}}
                    <div class="mt-3" x-show="parentPins().length">
                        <button type="button" class="flex w-full items-center gap-2 text-left text-sm text-zinc-600 hover:text-zinc-900"
                            @click="previousOpen = ! previousOpen" :aria-expanded="previousOpen.toString()">
                            <span class="min-w-0 flex-1" x-text="'From pass ' + (payload.previous_pass && payload.previous_pass.pass)"></span>
                            <span class="tabular-nums text-zinc-400" x-text="parentPins().length"></span>
                            <span class="text-zinc-400 transition" :class="previousOpen && 'rotate-180'" aria-hidden="true">▾</span>
                        </button>
                        <div class="mt-2 flex flex-col gap-2" x-show="previousOpen" x-cloak>
                            <template x-for="pin in parentPins()" :key="'prev'+pin.id">
                                @include('mcp.partials.mark-row', ['previous' => true])
                            </template>
                        </div>
                    </div>
                    {{-- Hints: second opinion, after the marks as on the web review --}}
                    <div class="mt-3 flex flex-col gap-2" x-show="allFindings().length || checklistElsewhere()">
                        <p class="text-sm font-medium text-zinc-700">Hints</p>
                        {{-- The checklist covers the whole review, so it sits on the first shot. --}}
                        <p class="text-xs text-zinc-500" x-show="checklistElsewhere()">
                            The checklist covers every shot, so it sits on the first one.
                            <button type="button" class="font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900" @click="setActive(0)">Show it</button>
                        </p>
                        <template x-for="f in allFindings()" :key="findingKey(f)">
                            <button type="button"
                                class="rounded-xl bg-raised p-3 text-left shadow-xs ring-1 ring-black/[0.04] transition-shadow hover:ring-sky-300"
                                :class="activeFinding && activeFinding.key === findingKey(f) && '!ring-2 !ring-sky-400'"
                                @click="showFinding(f)">
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full border border-dashed border-sky-500 bg-white px-1 text-xs font-semibold text-sky-700"
                                        x-show="hasRegion(f)"
                                        x-text="'S' + regionNumber(f)"></span>
                                    <span class="text-xs text-zinc-500" x-text="severityLabel(f.severity)"></span>
                                    <span class="inline-flex" x-show="f.source === 'agent'"><x-agent-tag /></span>
                                    <span class="rounded-md bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-800"
                                        x-show="hasRegion(f) && f.source !== 'agent'" x-text="findingSourceLabel(f)"></span>
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700" x-text="f.body"></p>
                            </button>
                        </template>
                    </div>

                </div>

                {{-- BOARD VIEW --}}
                <div class="mt-3" x-show="view === 'board'">
                    <button type="button" class="btn-lit mb-3 h-8 rounded-full px-3 text-xs font-medium disabled:opacity-50"
                        x-show="!reviewingFixes && readyFixes().length" :disabled="busy" @click="startFixReview()"
                        x-text="'Review ' + readyFixes().length + (readyFixes().length === 1 ? ' fix' : ' fixes')"></button>
                    <p class="mb-3 text-sm text-muted-foreground" role="status" x-show="fixesNotice" x-text="fixesNotice"></p>
                    <div class="hatch rounded-2xl px-6 py-8 text-center text-zinc-300" x-show="!boardPins().length">
                        <p class="text-sm font-semibold text-zinc-900">No marks yet</p>
                        <p class="mt-1 text-sm text-muted-foreground">Marks you make on the screenshot land here.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" x-show="boardPins().length">
                    <template x-for="col in boardColumns" :key="col.status">
                        <div class="flex flex-col rounded-xl bg-well p-3">
                            <div class="mb-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-start gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-zinc-900" x-text="col.label"></p>
                                        </div>
                                    </div>
                                    <span class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 px-2 text-sm font-semibold tabular-nums text-zinc-700"
                                        x-text="pinsByStatus(col.status).length"></span>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col gap-2">
                                <template x-for="pin in pinsByStatus(col.status)" :key="'b'+pin.id">
                                    <button type="button"
                                        class="rounded-xl bg-lift p-3 text-left shadow-md shadow-black/[0.06] ring-1 ring-black/[0.07] transition-shadow hover:shadow-lg"
                                        :class="activePin && activePin.id === pin.id ? 'border-zinc-400 ring-1 ring-zinc-300' : ''"
                                        @click="showPin(pin)">
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold"
                                                :class="markerBg(pin.severity)" x-text="'M' + pin.number"></span>
                                            <span class="text-xs text-zinc-500" x-text="severityLabel(pin.severity)"></span>
                                            <span class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-4" :class="statusBadge(pin.status)"><span class="size-1.5 shrink-0 rounded-full" :class="statusDot(pin.status)" aria-hidden="true"></span><span x-text="statusLabel(pin.status)"></span></span>
                                            <span class="inline-flex" x-show="agentSet(pin)"><x-agent-tag /></span>
                                            <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-500"
                                                x-show="pin._from_parent" x-text="'P' + pin._pass"></span>
                                            <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600"
                                                x-show="pin.comment_count > 0" x-text="pin.comment_count"></span>
                                        </div>
                                        <p class="text-sm leading-relaxed text-zinc-700" x-text="pin.body"></p>
                                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900"
                                            x-show="pin.resolution_note" @click.stop>
                                            <x-agent-tag class="me-1 align-[1px]" />
                                            <span x-text="pin.resolution_note"></span>
                                        </div>
                                    </button>
                                </template>
                                <p class="py-2 text-xs text-muted-foreground"
                                    x-show="!pinsByStatus(col.status).length" x-text="col.empty"></p>
                            </div>
                        </div>
                    </template>
                    </div>
                </div>

                {{-- Mark detail, opened from the board --}}
                <div x-show="view === 'board' && activePin" x-cloak>@include('mcp.partials.mark-detail', ['detailView' => 'board'])</div>

                {{-- decision bar --}}
                <div class="mt-4 rounded-2xl bg-card px-3 py-3 sm:px-4" x-show="isPending">
                    <details class="mb-2" x-show="!deciding">
                        <summary class="w-fit cursor-pointer py-1 text-xs font-medium text-muted-foreground">Add a note</summary>
                        <input type="text" x-model="decisionNote" placeholder="Optional note for the agent…"
                        class="h-8 w-full rounded-lg border border-input bg-background px-3 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-key/40">
                    </details>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-if="!deciding">
                            <div class="contents">
                                <button type="button" class="inline-flex h-8 items-center btn-lit rounded-full px-3 text-sm font-medium disabled:opacity-50"
                                    :disabled="busy" @click="startDecision('approved')">Approve</button>
                                <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-sm font-medium text-zinc-800 transition hover:bg-chip-hover disabled:opacity-50"
                                    :disabled="busy" @click="startDecision('changes_requested')">Changes</button>
                            </div>
                        </template>
                        {{-- The web's few seconds to take a decision back, here too. --}}
                        <template x-if="deciding">
                            <div class="contents" role="status" aria-live="polite">
                                <span class="text-sm tabular-nums text-zinc-700" x-text="(deciding === 'approved' ? 'Approving' : 'Sending changes') + ' in ' + decidingLeft + 's'"></span>
                                <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-sm font-medium text-zinc-800 transition hover:bg-chip-hover"
                                    @click="cancelDecision()">Undo</button>
                                <button type="button" class="inline-flex h-8 items-center rounded-md px-3 text-sm font-medium text-zinc-500 transition hover:text-zinc-800"
                                    @click="commitDecision()">Send now</button>
                            </div>
                        </template>
                        <button type="button" class="inline-flex h-8 items-center rounded-md px-3 text-sm font-medium text-zinc-500 transition hover:text-zinc-800"
                            @click="openFullReview()">Open full review</button>
                    </div>
                </div>

                <div class="mt-4 space-y-3" x-show="!isPending">
                    <p class="rounded-xl bg-attention-soft px-3 py-2.5 text-sm text-attention-ink sm:px-4" x-show="handoffNote" x-text="handoffNote"></p>
                    <div class="rounded-xl bg-card px-3 py-2.5 text-sm leading-relaxed text-zinc-700 sm:px-4"
                        x-show="payload.decision_note">
                        <strong class="font-medium text-zinc-900">Note to the agent:</strong>
                        <span x-text="payload.decision_note"></span>
                    </div>
                    <div class="rounded-xl bg-card px-3 py-2.5 text-sm leading-relaxed text-zinc-700 sm:px-4"
                        x-show="payload.status === 'changes_requested'">
                        <strong class="font-medium text-zinc-900">What’s next:</strong>
                        The agent should apply your marks, then open a new checkup pass with fresh screenshots (linked to this review). You’ll get another link to approve.
                    </div>
                    <div class="rounded-xl bg-card px-3 py-2.5 text-sm leading-relaxed text-zinc-700 sm:px-4"
                        x-show="payload.status === 'approved'">
                        <strong class="font-medium text-zinc-900">Loop complete for this pass.</strong>
                        Ask the agent for another checkup anytime if the UI changes again.
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-sm font-medium text-zinc-700 transition hover:bg-chip-hover"
                            @click="openFullReview()">Open full review</button>
                        <button type="button" class="inline-flex h-8 items-center rounded-md px-3 text-sm font-medium text-zinc-500 transition hover:text-zinc-800 disabled:opacity-50"
                            x-show="payload.can_change_status" :disabled="busy" @click="reopen()">Reopen for review</button>
                    </div>
                </div>

                <p class="mt-2 text-sm text-problem-ink" x-show="error && !composer.open" x-text="error"></p>
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
        let onToolInput = null;
        let lastToolInput = null;
        let onToolCancelled = null;
        let lastCancelReason = null;
        // What the host told us about itself: display modes, platform, safe
        // area, and which app→host messages it takes (hostCapabilities).
        let hostContext = {};
        let hostCapabilities = null;
        let onHostContext = null;
        let onTeardown = null;

        function send(msg) { window.parent.postMessage(msg, '*'); }

        function request(method, params, timeout = 0) {
            const id = nextId++;
            return new Promise((resolve, reject) => {
                const timer = timeout ? setTimeout(() => {
                    pending.delete(id);
                    reject(new Error('No answer from the host. Try refreshing or open the full review.'));
                }, timeout) : null;
                pending.set(id, { resolve, reject, timer });
                send({ jsonrpc: '2.0', id, method, params: params || {} });
            });
        }

        function mergeHostContext(update) {
            hostContext = Object.assign({}, hostContext, update || {});
            if (hostContext.theme) { hostTheme = hostContext.theme; applyTheme(); }
            if (onHostContext) onHostContext(hostContext);
        }

        // Tell the host how tall we are, so the inline frame fits the review.
        function watchSize() {
            if (typeof ResizeObserver === 'undefined' || !document.body) return;
            let queued = false;
            new ResizeObserver(() => {
                if (queued) return;
                queued = true;
                requestAnimationFrame(() => {
                    queued = false;
                    const html = document.documentElement;
                    const previous = html.style.height;
                    html.style.height = 'max-content';
                    const height = Math.ceil(html.getBoundingClientRect().height);
                    html.style.height = previous;
                    send({ jsonrpc: '2.0', method: 'ui/notifications/size-changed', params: { width: Math.ceil(window.innerWidth), height } });
                });
            }).observe(document.body);
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
                clearTimeout(p.timer);
                if ('error' in msg) p.reject(new Error(msg.error?.message || 'Host error'));
                else p.resolve(msg.result);
                return;
            }

            // Notifications from the host. The call's arguments arrive first
            // (partially while the agent is still writing them), the result
            // when the tool finishes.
            if (msg.method === 'ui/notifications/tool-input' || msg.method === 'ui/notifications/tool-input-partial') {
                lastToolInput = msg.params?.arguments || {};
                if (onToolInput) onToolInput(lastToolInput);
                return;
            }

            if (msg.method === 'ui/notifications/tool-cancelled') {
                lastCancelReason = msg.params?.reason || '';
                if (onToolCancelled) onToolCancelled(lastCancelReason);
                return;
            }

            if (msg.method === 'ui/notifications/tool-result') {
                lastToolResult = msg.params || {};
                if (onToolResult) onToolResult(lastToolResult);
                return;
            }

            if (msg.method === 'ui/notifications/host-context-changed') {
                mergeHostContext(msg.params);
                return;
            }

            // The host is about to remove us: stop timers first, then say so.
            if (msg.method === 'ui/resource-teardown' && msg.id != null) {
                if (onTeardown) onTeardown();
                send({ jsonrpc: '2.0', id: msg.id, result: {} });
                return;
            }

            // Any other request from the host — acknowledge.
            if (msg.id != null && msg.method) {
                send({ jsonrpc: '2.0', id: msg.id, result: {} });
            }
        });

        // Light or dark: the host's theme when it says, the system's when it doesn't.
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)');
        let hostTheme = null;
        function applyTheme() {
            const dark = hostTheme ? hostTheme === 'dark' : systemDark.matches;
            document.documentElement.classList.toggle('dark', dark);
        }
        systemDark.addEventListener('change', applyTheme);
        applyTheme();

        async function connect() {
            const result = await request('ui/initialize', {
                protocolVersion: '2026-01-26',
                appInfo: { name: 'ReviseMy review', version: @json(config('revisemy.version')) },
                appCapabilities: { availableDisplayModes: ['inline', 'fullscreen'] },
            });
            hostCapabilities = result?.hostCapabilities || null;
            mergeHostContext(result?.hostContext || {});
            send({ jsonrpc: '2.0', method: 'ui/notifications/initialized', params: {} });
            watchSize();
        }

        window.mcpBridge = {
            connect,
            set ontoolresult(fn) { onToolResult = fn; if (fn && lastToolResult) fn(lastToolResult); },
            set ontoolinput(fn) { onToolInput = fn; if (fn && lastToolInput) fn(lastToolInput); },
            set ontoolcancelled(fn) { onToolCancelled = fn; if (fn && lastCancelReason !== null) fn(lastCancelReason); },
            callTool: (name, args) => request('tools/call', { name, arguments: args || {} }, 20000),
            openLink: (url) => request('ui/open-link', { url }, 5000),
            // Post a message into the chat as the person, which starts the
            // agent's next turn. Rejects when the host doesn't take it.
            sendMessage: (text) => request('ui/message', { role: 'user', content: [{ type: 'text', text }] }, 8000),
            // Keep what the agent knows current without starting a turn.
            // Each call replaces the last; best-effort.
            updateModelContext: (params) => request('ui/update-model-context', params, 5000).catch(() => null),
            requestDisplayMode: (mode) => request('ui/request-display-mode', { mode }, 5000),
            get hostContext() { return hostContext; },
            get hostCapabilities() { return hostCapabilities; },
            set onhostcontext(fn) { onHostContext = fn; if (fn) fn(hostContext); },
            set onteardown(fn) { onTeardown = fn; },
        };

        connect().catch((e) => console.error('[ReviseMy] MCP connect failed', e));
    })();

    // What's being made, read from the tool call's arguments. Honest about
    // time; it doesn't pretend to know which step the server is on.
    function workingFor(args) {
        args = args || {};
        if (args.id && !args.title) return { title: 'Opening the review', detail: '' };

        let host = '';
        try { host = args.page_url ? new URL(args.page_url).hostname : ''; } catch (e) { host = ''; }

        if (args.capture_url) {
            return {
                title: 'Capturing ' + (host || 'the page'),
                detail: 'Desktop, mobile and tablet, full page. This usually takes 20 to 60 seconds.',
            };
        }
        if (args.pdf) return { title: 'Rendering the slides', detail: 'One shot per page, up to five.' };
        if (args.html) return { title: 'Rendering the email', detail: 'At about 600px, like a mail client.' };
        if (Array.isArray(args.images)) {
            const n = args.images.length;
            return { title: 'Saving ' + n + (n === 1 ? ' screenshot' : ' screenshots'), detail: '' };
        }
        return { title: 'Making the review', detail: '' };
    }

    function reviewApp() {
        // Keep in sync with Annotation::severityLabels(),
        // statusLabels(), and boardColumnMeta().
        const MARKER = @json(\App\Models\Annotation::make()->markerClass());
        const SEVERITY_LABELS = {
            'must-fix': 'Must fix', 'nit': 'Nice to have', 'question': 'Question', 'keep': 'Keep this',
            'wording': 'Wording', 'spacing': 'Spacing', 'size': 'Size', 'color': 'Color', 'alignment': 'Alignment',
            'suggestion': 'Suggestion', 'a11y': 'A11y', 'polish': 'Polish',
        };
        // The same tones the web review uses, from Annotation::TONES.
        const TONES = @json(\App\Models\Annotation::TONES);
        const STATUS_TONES = @json(\App\Models\Annotation::statusTones());
        // Who did something: Annotation::ACTOR_PILLS, as the web's who-pill.
        const ACTOR_PILLS = @json(\App\Models\Annotation::ACTOR_PILLS);
        const STATUS_LABELS = { 'open': 'Open', 'in_progress': 'In progress', 'resolved': 'Resolved', 'verified': 'Verified' };

        return {
            payload: null,
            view: 'screenshot',
            reviewingFixes: false,
            comparisonView: 'after',
            fixesNotice: '',
            comparing: false,
            activeIndex: 0,
            fit: 'width',
            brief: false,
            shareOpen: false,
            shareReady: false,
            shareStatus: '',
            reactionOptions: @json(\App\Models\AnnotationComment::REACTIONS),
            layoutTick: 0,
            activePin: null,
            pinInspection: null,
            activeFinding: null,
            busy: false,
            error: '',
            replyBody: '',
            replyReferences: [],
            talkOnly: false,
            // Handing back to the agent and staying in step with the host.
            handoffNote: '',
            displayMode: 'inline',
            canFullscreen: false,
            platform: '',
            safeArea: null,
            // One live copy: every get_review leaves another copy in the chat.
            // The one whose first result is newest stays live; older ones
            // fold away and stop polling (see claimLatest()).
            firstSnapshot: null,
            superseded: false,
            channel: null,
            onScreen: true,
            contextKey: '',
            pollTimer: null,
            working: { title: 'Opening the review', detail: '' },
            elapsed: 0,
            failed: '',
            failedTitle: '',
            decisionNote: '',
            deciding: null,
            decidingLeft: 0,
            decidingTimer: null,
            previousOpen: false,
            draft: { drawing: false, x0: 0, y0: 0, x: 0, y: 0, w: 0, h: 0 },
            composer: { references: [], open: false, x: 0, y: 0, area: null, severity: 'must-fix', manualType: false, body: '', element: null, snap: true, target: null },
            // Element picking: each shot's element list (from get_shot_elements),
            // the one under the pointer, a touch tap in progress, and whether
            // clicks snap. Hit-testing is window.rmPick, shared with the web.
            elementLists: {},
            hover: null,
            tap: null,
            // Touch, as on the review page: a finger scrolls and opens marks, and
            // Mark turns the next drag or tap into one.
            coarse: window.matchMedia('(pointer: coarse)').matches,
            marking: false,
            pickOn: (() => { try { return localStorage.getItem('rm-pick') !== 'off'; } catch (e) { return true; } })(),
            // Labels/owners mirror Annotation::boardColumnMeta(); empty copy is
            // button-flow (no drag columns in the MCP app).
            boardColumns: [
                { status: 'open', label: 'Open', owner: 'You', empty: 'No open marks' },
                { status: 'in_progress', label: 'In progress', owner: 'Agent', empty: 'Agent starts fixes here' },
                { status: 'resolved', label: 'Resolved', owner: 'You or agent', empty: 'Nothing to verify' },
                { status: 'verified', label: 'Verified', owner: 'You', empty: 'None verified yet' },
            ],

            init() {
                const started = Date.now();
                const tick = setInterval(() => {
                    this.elapsed = Math.floor((Date.now() - started) / 1000);
                    if (this.payload || this.failed) clearInterval(tick);
                }, 1000);

                window.mcpBridge.ontoolinput = (args) => { this.working = workingFor(args); };
                window.mcpBridge.ontoolcancelled = () => {
                    if (this.payload) return;
                    this.failedTitle = 'Stopped';
                    this.failed = 'The call was stopped before the review was made.';
                };
                window.mcpBridge.ontoolresult = (params) => {
                    const data = params.structuredContent;
                    if (data && data.id) { this.apply(data); return; }

                    // An error (no credits, a bad source) has no review to show.
                    // Say what the tool said instead of loading forever.
                    if (!this.payload) {
                        const text = (params.content || []).find((c) => c.type === 'text')?.text || '';
                        this.failedTitle = 'No review this time';
                        this.failed = text.split('\n')[0] || 'The tool didn’t return a review.';
                    }
                };

                window.mcpBridge.onhostcontext = (ctx) => {
                    if (ctx.displayMode) this.displayMode = ctx.displayMode;
                    this.canFullscreen = (ctx.availableDisplayModes || []).includes('fullscreen');
                    this.platform = ctx.platform || '';
                    this.safeArea = ctx.safeAreaInsets || null;
                };
                window.mcpBridge.onteardown = () => this.stop();
                this.$watch('payload', () => this.loadElements());
                this.$watch('activeIndex', () => { this.hover = null; this.loadElements(); });

                // Only poll while someone can see it, and only the live copy.
                if (typeof IntersectionObserver !== 'undefined') {
                    new IntersectionObserver((entries) => { this.onScreen = entries.some((e) => e.isIntersecting); })
                        .observe(this.$el);
                }
                this.pollTimer = setInterval(() => {
                    if (!this.payload || this.busy || this.deciding || this.superseded || document.hidden || !this.onScreen) return;
                    if (this.shareOpen || this.payload.status === 'pending' || this.payload.status === 'changes_requested') {
                        this.refresh();
                    }
                }, 12000);
            },

            stop() {
                clearInterval(this.pollTimer);
                if (this.channel) this.channel.close();
            },

            // Copies of this review in the chat share an origin, so they can
            // talk. Each says when its first result was made; any copy that
            // hears of a newer one folds away.
            claimLatest(snapshot) {
                if (this.firstSnapshot || !snapshot) return;
                this.firstSnapshot = snapshot;
                if (typeof BroadcastChannel === 'undefined' || !this.payload) return;
                this.channel = new BroadcastChannel('revisemy:' + this.payload.id);
                this.channel.onmessage = (event) => {
                    const theirs = event.data && event.data.snapshot;
                    if (!theirs || theirs === this.firstSnapshot) return;
                    if (theirs > this.firstSnapshot) { this.superseded = true; return; }
                    // An older copy just woke up: tell it there's a newer one.
                    this.channel.postMessage({ snapshot: this.firstSnapshot });
                };
                this.channel.postMessage({ snapshot: this.firstSnapshot });
            },

            // What the agent should know without being asked: replaced on
            // every real change, never starts a turn.
            pushContext() {
                if (this.superseded || !this.payload) return;
                const p = this.payload;
                const open = this.boardPins()
                    .filter((pin) => pin.status === 'open' || pin.status === 'in_progress')
                    .map((pin) => ({
                        id: pin.id, number: pin.number, severity: pin.severity, status: pin.status,
                        body: (pin.body || '').slice(0, 140), awaiting_reply: !!pin.awaiting_reply,
                    }));
                const structured = {
                    review_id: p.id, title: p.title, pass: p.pass, status: p.status,
                    updated_at: p.updated_at, open_marks: open,
                    awaiting_reply_count: (p.loop && p.loop.awaiting_reply_count) || 0,
                };
                const key = JSON.stringify(structured);
                if (key === this.contextKey) return;
                this.contextKey = key;
                const waiting = open.filter((m) => m.awaiting_reply).map((m) => 'M' + m.number);
                const text = 'ReviseMy review "' + p.title + '", pass ' + p.pass + ', status ' + String(p.status).replace('_', ' ') + '. '
                    + open.length + (open.length === 1 ? ' open mark' : ' open marks')
                    + (waiting.length ? ', waiting on your reply: ' + waiting.join(', ') : '')
                    + '. Review id ' + p.id + '; call get_review for the full record.';
                window.mcpBridge.updateModelContext({ content: [{ type: 'text', text }], structuredContent: structured });
            },

            // After a decision, say so in the chat as the person, so the agent
            // picks it up now rather than on its next poll.
            async handOff(decision, note) {
                const p = this.payload;
                if (!p) return;
                const where = p.title + ', pass ' + p.pass;
                const count = (p.loop && p.loop.outstanding_count) || 0;
                let text = decision === 'approved'
                    ? 'I approved ' + where + '.'
                    : 'I asked for changes on ' + where + (count ? ': ' + count + (count === 1 ? ' mark' : ' marks') + ' to fix.' : '.');
                if (note) text += ' My note: “' + note + '”';
                if (decision !== 'approved') text += ' Go ahead.';
                this.handoffNote = '';
                try {
                    await window.mcpBridge.sendMessage(text);
                } catch (e) {
                    this.handoffNote = 'Tell your agent in the chat to carry on.';
                }
            },

            async toggleFullscreen() {
                const want = this.displayMode === 'fullscreen' ? 'inline' : 'fullscreen';
                try {
                    const result = await window.mcpBridge.requestDisplayMode(want);
                    this.displayMode = (result && result.mode) || want;
                    if (this.displayMode === 'fullscreen') this.setFit('page');
                } catch (e) { /* the host kept us where we are */ }
            },

            safeAreaStyle() {
                const a = this.displayMode === 'fullscreen' ? this.safeArea : null;
                if (!a) return '';
                return 'padding:' + (a.top || 0) + 'px ' + (a.right || 0) + 'px ' + (a.bottom || 0) + 'px ' + (a.left || 0) + 'px;';
            },

            apply(data) {
                if (data.guest_share_url !== this.payload?.guest_share_url) this.shareStatus = '';
                this.payload = data;
                if (this.activeIndex >= data.screenshots.length) this.activeIndex = 0;
                this.error = '';
                if (this.activePin) {
                    const refreshed = this.boardPins().find((p) => p.id === this.activePin.id);
                    this.activePin = refreshed || null;
                }
                this.claimLatest(data.snapshot_at);
                this.pushContext();
            },

            get isPending() { return this.payload && this.payload.status === 'pending'; },

            // The agent's tone: the same dot "In progress" wears.
            workingDot() { return TONES.agent.dot; },

            elapsedLabel() {
                const m = Math.floor(this.elapsed / 60);
                const s = String(this.elapsed % 60).padStart(2, '0');
                return m + ':' + s;
            },

            markerBg() { return MARKER; },
            severityLabel(severity) { return SEVERITY_LABELS[severity] || severity; },
            statusBadge(status) { return TONES[STATUS_TONES[status] || 'neutral'].tag; },
            statusDot(status) { return TONES[STATUS_TONES[status] || 'neutral'].dot; },
            statusLabel(status) { return STATUS_LABELS[status] || status; },
            isSettled(pin) { return pin.status === 'resolved' || pin.status === 'verified'; },
            canManagePin(pin) { return this.payload?.can_manage_marks && pin && pin.severity !== 'keep' && pin.status !== 'open'; },

            // The mark timeline (MarkTimeline on the server). "Talk only"
            // keeps the mark itself, comments, and steps that carry a note.
            actorPill(actor) { return (ACTOR_PILLS[actor] || ACTOR_PILLS.owner).pill; },
            actorDot(actor) { return (ACTOR_PILLS[actor] || ACTOR_PILLS.owner).dot; },
            actorLabel(entry) {
                if (entry.actor === 'agent') return 'Agent';
                if (entry.actor === 'owner') return 'You';
                return entry.author || 'Guest';
            },
            isTalk(entry) { return entry.kind === 'marked' || entry.kind === 'comment' || !!entry.body; },
            hasSteps(pin) { return !!pin && (pin.timeline || []).some((e) => !this.isTalk(e)); },
            timelineFor(pin) {
                const entries = (pin && pin.timeline) || [];
                return this.talkOnly ? entries.filter((e) => this.isTalk(e)) : entries;
            },
            ago(iso) {
                const seconds = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
                if (seconds < 60) return 'just now';
                const steps = [[60, 'minute'], [24, 'hour'], [7, 'day'], [4.35, 'week'], [12, 'month']];
                let value = seconds / 60;
                for (const [size, unit] of steps) {
                    if (value < size) { const n = Math.floor(value); return n + ' ' + unit + (n === 1 ? '' : 's') + ' ago'; }
                    value /= size;
                }
                const n = Math.floor(value);
                return n + ' year' + (n === 1 ? '' : 's') + ' ago';
            },

            hasRegion(f) {
                const a = f && f.area;
                return !!(a && Number(a.w) >= 0.01 && Number(a.h) >= 0.01);
            },
            allFindings() {
                const shot = this.activeShot();
                return shot && Array.isArray(shot.second_opinion) ? shot.second_opinion : [];
            },
            checklistElsewhere() {
                const first = this.payload && this.payload.screenshots[0];
                return this.activeIndex > 0 && !! first && Array.isArray(first.second_opinion)
                    && first.second_opinion.some((f) => (f.source || 'checklist') === 'checklist');
            },
            regionFindings() {
                return this.allFindings().filter((f) => this.hasRegion(f));
            },
            findingStableId(f) {
                if (! f) return '';
                if (f.id != null) return String(f.id);
                const a = f.area || {};
                return [f.body || '', a.x, a.y, a.w, a.h, f.source || ''].join('|');
            },
            numberedRegionFindings() {
                return this.regionFindings().map((finding, i) => ({
                    finding,
                    number: i + 1,
                    key: 's' + this.findingStableId(finding),
                }));
            },
            regionNumber(f) {
                const id = this.findingStableId(f);
                const idx = this.regionFindings().findIndex((r) => this.findingStableId(r) === id);
                return idx >= 0 ? idx + 1 : 0;
            },
            findingKey(f) {
                if (this.hasRegion(f)) {
                    return 's' + this.findingStableId(f);
                }
                return 't' + this.findingStableId(f);
            },
            // Mirrors Annotation::changedByAgent(): a status your agent set.
            agentSet(pin) {
                return !! pin && pin.status_by === 'agent' && ['in_progress', 'resolved'].includes(pin.status);
            },
            findingSourceLabel(f) {
                const s = (f && f.source) || 'checklist';
                if (s === 'openai' || s === 'anthropic') return 'Vision';
                if (s === 'agent') return 'Agent';
                if (s === 'guest') return f.author || 'Guest';
                return 'Checklist';
            },


            // Counted over the same marks the board shows (this pass and the one
            // before), so a previous pass can't make the bar under-report.
            tone(kind) { return TONES[kind] || TONES.neutral; },
            reviewTone() {
                return { changes_requested: ['attention', 'Changes requested'], approved: ['done', 'Approved'], expired: ['neutral', 'Expired'] }[this.payload.status] || null;
            },

            currentPins() {
                if (!this.payload) return [];
                return this.payload.screenshots.flatMap((s) =>
                    (s.pins || []).map((p) => ({
                        ...p,
                        screenshot_index: s.index,
                        _pass: this.payload.pass,
                        _from_parent: false,
                    }))
                );
            },

            parentPins() {
                if (!this.payload) return [];
                const parentPass = this.payload.previous_pass;
                if (!parentPass || !Array.isArray(parentPass.marks)) return [];
                return parentPass.marks.map((p) => ({
                    ...p,
                    _pass: parentPass.pass,
                    _from_parent: true,
                }));
            },

            boardPins() {
                if (!this.payload) return [];
                return [...this.parentPins(), ...this.currentPins()].sort((a, b) => {
                    if (a._pass !== b._pass) return a._pass - b._pass;
                    if (a.screenshot_index !== b.screenshot_index) return (a.screenshot_index || 0) - (b.screenshot_index || 0);
                    return a.number - b.number;
                });
            },

            pinsByStatus(status) { return this.boardPins().filter((p) => p.status === status); },

            readyFixes() {
                if (!this.payload?.can_manage_marks) return [];
                return this.pinsByStatus('resolved').filter((pin) => pin.severity !== 'keep');
            },

            startFixReview() {
                const first = this.readyFixes()[0];
                if (!first) return;
                this.fixesNotice = '';
                this.showPin(first, true);
            },

            showPin(pin, reviewing = false) {
                this.reviewingFixes = reviewing;
                this.comparisonView = 'after';
                this.activeFinding = null;
                this.replyBody = '';
                this.replyReferences = [];
                this.pinInspection = null;
                this.activePin = !reviewing && this.activePin?.id === pin.id ? null : pin;
                // Bring the opened mark into view, like the popover on the web review.
                if (this.activePin) {
                    if (pin.element) {
                        window.mcpBridge.callTool('get_shot_elements', { review_id: this.payload.id, mark_id: pin.id })
                            .then((result) => {
                                if (this.activePin?.id === pin.id) this.pinInspection = result?.structuredContent?.target ?? null;
                            })
                            .catch(() => {});
                    }
                    this.$nextTick(() => {
                        const detail = Array.from(this.$root.querySelectorAll('[data-mark-detail]')).find((el) => el.offsetParent !== null);
                        detail?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest' });
                    });
                }
            },

            showFinding(finding) {
                this.activePin = null;
                const key = this.findingKey(finding);
                const number = this.regionNumber(finding);
                this.activeFinding = this.activeFinding && this.activeFinding.key === key ? null : {
                    key,
                    label: number > 0 ? 'S' + number : 'Hint',
                    severity: finding.severity,
                    body: finding.body,
                };
            },

            closeDetail() {
                this.reviewingFixes = false;
                this.activePin = null;
                this.activeFinding = null;
            },


            shotLabel(shot, i) {
                if (shot.label) return shot.label;
                const v = shot.meta && shot.meta.viewport;
                const page = shot.meta && shot.meta.page;
                if (page) return 'Page ' + page;
                // Same as Screenshot::railLabel(): "desktop-1280" reads as "Desktop".
                if (v) { const name = v.split('-')[0]; return name.charAt(0).toUpperCase() + name.slice(1); }
                return 'Shot ' + (i + 1);
            },

            setActive(i) { this.activeIndex = i; this.comparing = false; this.closeComposer(); this.closeDetail(); },
            activeShot() { return this.payload ? this.payload.screenshots[this.activeIndex] : null; },
            // Mirrors the review page's fit modes and Screenshot::pixelRatio():
            // 100% is the size the page was drawn at, so 2× stays sharp and a
            // phone isn't blown up. Fit width never goes past it; whole page fits
            // the box's height too.
            shotFrameStyle(shot) {
                this.layoutTick;
                const ratio = Number(shot && shot.meta && shot.meta.dpr) || 1;
                const width = Number(shot && shot.width);
                const height = Number(shot && shot.height);
                if (! (width > 0)) return '';
                const actual = Math.round(width / ratio);
                if (this.fit === 'actual') return `width:${actual}px;max-width:none;margin-inline:auto;`;
                const box = this.$refs.shotBox;
                if (this.fit === 'page' && box && height > 0) {
                    const cs = getComputedStyle(box);
                    // The box's max height (min(70dvh, 36rem)), not its current
                    // height, which shrinks with the shot.
                    const availH = Math.min(window.innerHeight * 0.7, 576) - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
                    const availW = box.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
                    const fitted = Math.max(1, Math.min(actual, availW, availH * (width / height)));
                    return `width:${Math.round(fitted)}px;margin-inline:auto;`;
                }
                return `max-width:${actual}px;margin-inline:auto;`;
            },
            setFit(mode) {
                this.fit = mode;
                this.$refs.shotBox?.scrollTo({ top: 0, left: 0 });
            },

            pinStyle(p) {
                if (! p || ! Number.isFinite(Number(p.x)) || ! Number.isFinite(Number(p.y))) return '';
                return `left:${p.x * 100}%; top:${p.y * 100}%;`;
            },
            rectStyle(a) {
                if (! a || ! Number.isFinite(Number(a.x)) || ! Number.isFinite(Number(a.y))
                    || ! Number.isFinite(Number(a.w)) || ! Number.isFinite(Number(a.h))) {
                    return '';
                }
                return `left:${a.x * 100}%; top:${a.y * 100}%; width:${a.w * 100}%; height:${a.h * 100}%;`;
            },

            // Signed screenshot URL for a pin — current pass by screenshot_index,
            // else the parent pass's own shot list.
            pinShotUrl(pin) {
                if (! this.payload || ! pin) return '';
                const shots = pin._from_parent
                    ? (this.payload.previous_pass?.screenshots || [])
                    : (this.payload.screenshots || []);
                const i = Number(pin.screenshot_index);
                const shot = Number.isFinite(i)
                    ? shots.find((s) => Number(s.index) === i)
                    : null;
                return (shot || shots[0] || {}).url || '';
            },
            // Mirrors MarkFocus::backgroundStyle() — kept out of the payload so a
            // signed URL is not repeated on every copy of every mark.
            bgStyle(pin, window = pin?.focus_preview?.window) {
                const w = window;
                const url = this.pinShotUrl(pin);
                if (! w || ! url) return '';
                const sizeX = 100 / Math.max(w.w, 0.02);
                const sizeY = 100 / Math.max(w.h, 0.02);
                const posX = w.w < 1 ? (w.x / (1 - w.w)) * 100 : 0;
                const posY = w.h < 1 ? (w.y / (1 - w.h)) * 100 : 0;
                return `background-image:url(${url});background-size:${sizeX.toFixed(2)}% ${sizeY.toFixed(2)}%;`
                    + `background-position:${posX.toFixed(2)}% ${posY.toFixed(2)}%;background-repeat:no-repeat;`;
            },
            draftRectStyle() {
                const d = this.draft;
                const x = Math.min(d.x0, d.x), y = Math.min(d.y0, d.y);
                return `left:${x * 100}%; top:${y * 100}%; width:${Math.abs(d.x - d.x0) * 100}%; height:${Math.abs(d.y - d.y0) * 100}%;`;
            },

            norm(e) {
                const el = this.$refs.overlay;
                if (! el) return null;
                const r = el.getBoundingClientRect();
                if (r.width < 1 || r.height < 1) return null;
                return {
                    x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)),
                    y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)),
                };
            },

            startDraw(e) {
                if (!this.isPending) return;
                this.hover = null;
                // Touch scrolls the capture viewport and never marks, until Mark is on.
                if (e.pointerType === 'touch' && ! this.marking) return;
                if (e.button != null && e.button !== 0) return;
                const p = this.norm(e);
                if (! p) return;
                this.draft = { drawing: true, x0: p.x, y0: p.y, x: p.x, y: p.y, w: 0, h: 0 };
            },
            moveDraw(e) {
                if (!this.draft.drawing) {
                    if (e.pointerType !== 'touch' && this.isPending && !this.composer.open) this.hover = this.pickAt(e);
                    return;
                }
                const p = this.norm(e);
                if (! p) return;
                this.draft.x = p.x; this.draft.y = p.y;
                this.draft.w = Math.abs(p.x - this.draft.x0);
                this.draft.h = Math.abs(p.y - this.draft.y0);
            },
            endDraw(e) {
                if (!this.draft.drawing) return;
                const touch = e.pointerType === 'touch';
                if (touch) this.marking = false;
                this.draft.drawing = false;
                const p = this.norm(e) || { x: this.draft.x, y: this.draft.y };
                const w = Math.abs(p.x - this.draft.x0), h = Math.abs(p.y - this.draft.y0);
                // A drag of a few screen pixels is a box; anything less is a click.
                const r = this.$refs.overlay.getBoundingClientRect();
                const min = touch ? 14 : 6;
                if (w * r.width >= min && h * r.height >= min) {
                    const x = Math.min(p.x, this.draft.x0), y = Math.min(p.y, this.draft.y0);
                    this.openComposer(x + w / 2, y + h / 2, { x, y, w, h }, { snap: this.pickOn });
                    if (this.pickOn && this.elementsFor(this.activeShot())) this.describeElement({ area: { x, y, w, h } });
                } else {
                    this.clickAt(e);
                }
            },
            cancelDraw() { this.draft.drawing = false; this.tap = null; },

            // A click snaps to the element under it; Alt-click drops a spot.
            clickAt(e) {
                const picked = this.pickAt(e);
                const list = this.elementsFor(this.activeShot());
                if (picked !== null) {
                    const b = window.rmPick.box(list, picked);
                    this.openComposer(b.x + b.w / 2, b.y + b.h / 2, b, { element: picked });
                    this.describeElement({ element: picked });
                    return;
                }
                const p = this.norm(e);
                if (p) this.openComposer(p.x, p.y, null, { snap: false });
            },

            elementsFor(shot) { return shot ? this.elementLists[shot.id] || null : null; },
            loadElements() {
                const shot = this.activeShot();
                if (!shot || !this.isPending || !(shot.element_targeting ?? shot.meta?.element_count) || shot.id in this.elementLists) return;
                this.elementLists[shot.id] = null;
                window.mcpBridge.callTool('get_shot_elements', { review_id: this.payload.id, screenshot_id: shot.id })
                    .then((result) => {
                        const list = result && result.structuredContent && result.structuredContent.elements;
                        if (list && Array.isArray(list.e)) this.elementLists[shot.id] = list;
                    })
                    .catch(() => {});
            },
            pickAt(e) {
                const list = this.elementsFor(this.activeShot());
                if (!list || !this.pickOn || e.altKey || !window.rmPick) return null;
                const p = this.norm(e);
                return p ? window.rmPick.at(list, p.x, p.y) : null;
            },
            hoverStyle() {
                const list = this.elementsFor(this.activeShot());
                return this.hover === null || !list ? 'display:none' : this.rectStyle(window.rmPick.box(list, this.hover));
            },
            hoverLabel() {
                const list = this.elementsFor(this.activeShot());
                return this.hover === null || !list ? '' : window.rmPick.label(list, this.hover);
            },
            togglePick() {
                this.pickOn = !this.pickOn;
                this.hover = null;
                try { localStorage.setItem('rm-pick', this.pickOn ? 'on' : 'off'); } catch (e) {}
            },
            // The server names the element (chip, Inspect rows, the one around it).
            async describeElement(args) {
                const shot = this.activeShot();
                if (!shot) return;
                const asked = this.composer;
                try {
                    const result = await window.mcpBridge.callTool('get_shot_elements', { review_id: this.payload.id, screenshot_id: shot.id, ...args });
                    const target = result && result.structuredContent && result.structuredContent.target;
                    // Ignore an answer for a composer that has since closed or moved on.
                    if (this.composer !== asked || !this.composer.open || !target) return;
                    this.composer.target = target;
                    if (args.element !== undefined) {
                        this.composer.area = target.area;
                        this.composer.x = target.area.x + target.area.w / 2;
                        this.composer.y = target.area.y + target.area.h / 2;
                    }
                } catch (e) {}
            },
            widen() {
                const parent = this.composer.target && this.composer.target.parent;
                if (parent === null || parent === undefined) return;
                this.composer.element = parent;
                this.describeElement({ element: parent });
            },
            dropElement() {
                this.composer.element = null;
                this.composer.target = null;
                this.composer.snap = false;
            },

            openComposer(x, y, area, opts = {}) {
                this.closeDetail();
                this.hover = null;
                this.composer = { open: true, x, y, area, severity: 'must-fix', manualType: false, body: '', element: opts.element ?? null, snap: opts.snap ?? true, target: null, references: [] };
                // A phone caps the inline frame's height: mark in full screen.
                if (this.platform === 'mobile' && this.canFullscreen && this.displayMode !== 'fullscreen') this.toggleFullscreen();
            },
            closeComposer() { this.composer.open = false; this.composer.body = ''; },

            toggleElementReference(references, option) {
                if (references.some(item => item.key === option.key)) {
                    return references.filter(item => item.key !== option.key);
                }
                return references.length < {{ \App\Support\ElementTarget::MAX_REFERENCES }} ? [...references, option] : references;
            },

            async saveMark() {
                const shot = this.activeShot();
                if (!shot || !this.composer.body.trim()) return;
                const ok = await this.run(() => window.mcpBridge.callTool('add_mark', {
                    review_id: this.payload.id,
                    screenshot_id: shot.id,
                    x: this.composer.x,
                    y: this.composer.y,
                    // A plain copy: an area from get_shot_elements is reactive, and postMessage can't clone that.
                    area: this.composer.area ? { x: +this.composer.area.x, y: +this.composer.area.y, w: +this.composer.area.w, h: +this.composer.area.h } : undefined,
                    severity: this.composer.severity,
                    body: this.composer.body.trim(),
                    element: this.composer.element ?? undefined,
                    snap: this.composer.snap,
                    element_references: this.composer.references.map(item => item.key),
                }));
                // Keep the note on screen if the server refused it, so nothing typed is lost.
                if (ok) this.closeComposer();
            },

            async sendReply(pin) {
                const body = this.replyBody.trim();
                if (!pin || !body) return;
                const ok = await this.run(() => window.mcpBridge.callTool('add_comment', {
                    review_id: this.payload.id, mark_id: pin.id, body, element_references: this.replyReferences.map(item => item.key),
                }));
                // Keep what was typed if the server refused it.
                if (ok) { this.replyBody = ''; this.replyReferences = []; }
            },

            async react(entry, reaction, selected) {
                if (this.busy || !entry.comment_id || !this.payload.comments_enabled) return;
                await this.run(() => window.mcpBridge.callTool('set_comment_reaction', {
                    review_id: this.payload.id, comment_id: entry.comment_id, reaction, selected,
                }));
            },

            async verifyMark(pin, action) {
                const ok = await this.run(() => window.mcpBridge.callTool('verify_mark', {
                    review_id: this.payload.id, mark_id: pin.id, action,
                }));
                if (!ok || !this.reviewingFixes || this.activePin?.id !== pin.id) return;
                const next = this.readyFixes()[0];
                if (next) {
                    this.showPin(next, true);
                } else {
                    this.closeDetail();
                    this.fixesNotice = 'All ready fixes reviewed.';
                }
            },

            startDecision(decision) {
                this.cancelDecision();
                this.deciding = decision;
                this.decidingLeft = 8;
                this.decidingTimer = setInterval(() => { if (--this.decidingLeft <= 0) this.commitDecision(); }, 1000);
            },

            cancelDecision() {
                clearInterval(this.decidingTimer);
                this.decidingTimer = null;
                this.deciding = null;
            },

            commitDecision() {
                const decision = this.deciding;
                this.cancelDecision();
                if (decision) this.decide(decision);
            },

            async reopen() {
                await this.run(() => window.mcpBridge.callTool('decide_review', { review_id: this.payload.id, decision: 'pending' }));
            },

            async decide(decision) {
                const note = this.decisionNote.trim();
                const ok = await this.run(() => window.mcpBridge.callTool('decide_review', {
                    review_id: this.payload.id, decision, note: note || undefined,
                }));
                if (ok) {
                    this.decisionNote = '';
                    await this.handOff(decision, note);
                }
            },

            async refresh() {
                return this.run(() => window.mcpBridge.callTool('get_review', { id: this.payload.id }));
            },

            async toggleShare() {
                this.shareOpen = !this.shareOpen;
                if (!this.shareOpen) return;
                this.shareReady = false;
                this.shareStatus = '';
                this.shareReady = await this.refresh();
            },

            get guestLinkAvailable() {
                const access = this.payload?.guest_access;
                return this.shareReady && access?.open && this.payload.guest_share_url
                    && (!access.until || Date.parse(access.until) > Date.now());
            },

            guestLinkExpiry() {
                const until = this.payload.guest_access?.until;
                return until ? new Date(until).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
            },

            async copyGuestLink() {
                if (!this.guestLinkAvailable) return;
                const input = this.$refs.guestLink;
                try {
                    await navigator.clipboard.writeText(input.value);
                    this.shareStatus = 'Guest link copied';
                } catch {
                    input.focus();
                    input.select();
                    this.shareStatus = 'Copy the selected link to share it.';
                }
            },

            async openFullReview(sharing = false, feedback = false, mark = null) {
                if (!this.payload) return;
                const url = new URL(this.payload.review_url);
                if (sharing) url.searchParams.set('share', '1');
                if (feedback) url.searchParams.set('feedback', '1');
                if (mark !== null) url.searchParams.set('mark', mark);
                try {
                    await window.mcpBridge.openLink(url.href);
                } catch {
                    this.error = 'Open the owner review link from the chat to continue on the web.';
                }
            },

            // Resolves true on success. Tool errors (Response::error) arrive as a
            // normal result with isError set, not as a rejection — surface them.
            async run(fn) {
                this.busy = true; this.error = '';
                try {
                    const result = await fn();
                    if (result && result.isError) {
                        const text = (result.content || []).find((c) => c.type === 'text')?.text;
                        this.error = text || 'Something went wrong. Try the full review page.';
                        return false;
                    }
                    const data = result && result.structuredContent;
                    if (!data?.id) {
                        this.error = 'The host didn’t return the review. Try refreshing or open the full review.';
                        return false;
                    }
                    this.apply(data);
                    return true;
                } catch (e) {
                    this.error = e.message || 'Something went wrong. Try the full review page.';
                    return false;
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
<script>{!! $picker ?? '' !!}</script>
<script>{!! $markType ?? '' !!}</script>
<script type="module">{!! $script !!}</script>
