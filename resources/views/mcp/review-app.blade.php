{{-- Inline MCP App UI for ReviseMy reviews. Rendered in a sandboxed iframe by
     MCP Apps hosts (Claude web/desktop, etc.).

     Parity with web review/board (keep in sync in the same PR as chrome changes):
     - Marker / status / severity maps ↔ Annotation::markerClass, statusBadgeClass, severityLabels
     - Board columns / empty copy ↔ Annotation::boardColumnMeta
     - Mark focus crop via pin.focus_preview ↔ MarkFocus + mark-focus-preview
     - Control height h-8 ↔ Flux size="sm" on web
     Intentionally web-only: comment threads, share/guest, drag columns, second-opinion
     accept/dismiss, zoom/pan, editable title. When comment_count > 0, link out via
     review_url / board_url.

     Styles and Alpine are compiled from resources/css/mcp-app.css and
     resources/js/mcp-app.js (the same tokens as the site) and inlined by
     App\Mcp\Resources\ReviewApp; nothing loads from a CDN. The bridge is inline.
     App-only tools: add_mark / decide_review / verify_mark. --}}
<style>{!! $styles !!}</style>

<div class="bg-background text-foreground" x-data="reviewApp()" x-init="init()" x-cloak>
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

        <template x-if="payload">
            <div>
                {{-- header: title + chips, matching the review page header line --}}
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                    <h1 class="min-w-0 truncate text-lg font-semibold tracking-tight text-zinc-900" x-text="payload.title"></h1>
                    <span class="inline-flex shrink-0 items-center rounded-md bg-chip px-1.5 py-0.5 text-xs font-medium tabular-nums text-zinc-600"
                        x-text="'Pass ' + payload.pass"></span>
                    <span class="inline-flex shrink-0 items-center rounded-md bg-chip px-1.5 py-0.5 text-xs font-medium text-zinc-600"
                        x-show="payload.type" x-text="({ ui: 'UI', website: 'Website', presentation: 'Slides', email: 'Email' })[payload.type] || payload.type"></span>
                    <span class="relative inline-flex shrink-0" x-data="{ tasteOpen: false }" x-show="payload.taste && payload.taste.label">
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
                    {{-- The review's state, in the same tones as the web header (Annotation::TONES). --}}
                    <template x-if="reviewTone()">
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-4" :class="tone(reviewTone()[0]).tag"><span class="size-1.5 shrink-0 rounded-full" :class="tone(reviewTone()[0]).dot" aria-hidden="true"></span><span x-text="reviewTone()[1]"></span></span>
                    </template>
                </div>
                <p class="mt-1 text-sm text-zinc-500" x-show="payload.context" x-text="payload.context"></p>

                {{-- toolbar: view toggle + verified progress + refresh --}}
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-full bg-trough p-0.5">
                        <button type="button" class="h-8 rounded-full px-3 text-xs font-medium transition-colors"
                            :class="view === 'screenshot' ? 'bg-raised text-zinc-900 shadow-xs' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'screenshot'">Screenshot</button>
                        <button type="button" class="h-8 rounded-full px-3 text-xs font-medium transition-colors"
                            :class="view === 'board' ? 'bg-raised text-zinc-900 shadow-xs' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'board'" x-text="'Board · ' + boardPins().length"></button>
                    </div>
                    <div class="flex min-w-24 flex-1 items-center gap-2 sm:max-w-48">
                        <span class="shrink-0 text-xs tabular-nums text-zinc-500"
                            x-text="verifiedCount() + '/' + boardPins().length"></span>
                        <div class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-chip" role="progressbar" aria-label="Marks verified">
                            <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300 ease-out" :style="'width:' + verifiedPct() + '%'"></div>
                        </div>
                    </div>
                    <button type="button" class="inline-flex h-8 items-center gap-1 rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition hover:bg-chip-hover disabled:opacity-50"
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

                    <div class="relative max-h-[min(70dvh,36rem)] overflow-auto overscroll-contain rounded-2xl bg-well"
                        x-show="activeShot()">
                        <div class="relative w-full" x-show="activeShot()">
                            <img class="block w-full" :src="activeShot()?.url" :alt="payload.title" draggable="false">
                            <div class="absolute inset-0 cursor-crosshair touch-pan-y" x-ref="overlay"
                                @pointerdown="startDraw($event)" @pointermove="moveDraw($event)"
                                @pointerup="endDraw($event)" @pointercancel="cancelDraw()" @pointerleave="cancelDraw()">

                                {{-- human marks: key-coloured region + M# badge (review page classes) --}}
                                <template x-for="pin in (activeShot()?.pins || [])" :key="'p'+pin.id">
                                    <div>
                                        <div class="pointer-events-none absolute rounded-md border-2 border-key/80 bg-key/10"
                                            x-show="pin.area"
                                            :class="{ 'opacity-50': isSettled(pin) }" :style="pin.area ? rectStyle(pin.area) : ''"></div>
                                        <button type="button"
                                            class="pointer-events-auto absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full px-1 text-xs font-semibold shadow-lg ring-2 ring-white transition"
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

                    <p class="mt-2 text-xs text-sky-700" x-show="activeShot() && activeShot().second_opinion_status === 'queued'">
                        Generating second-opinion hints…
                    </p>

                    {{-- Mark detail --}}
                    <div x-show="activePin" x-cloak>@include('mcp.partials.mark-detail')</div>

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

                    <p class="mt-2 text-center text-xs text-muted-foreground" x-text="isPending ? 'Drag to mark a region, or click for a point.' : 'Click a numbered mark to read it.'"></p>

                    {{-- mark composer --}}
                    <div class="mt-3 rounded-2xl bg-card px-3 py-3 sm:px-4" x-show="composer.open" @keydown.escape="closeComposer()">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="sev in severities" :key="sev.value">
                                <button type="button"
                                    class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-full border px-2.5 text-sm transition"
                                    :class="composer.severity === sev.value ? 'bg-raised shadow-sm ring-1 ring-black/[0.08]' : 'bg-chip'"
                                    @click="composer.severity = sev.value">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="markerBg(sev.value)"></span>
                                    <span x-text="sev.label"></span>
                                </button>
                            </template>
                        </div>
                        <textarea x-model="composer.body" rows="3" placeholder="What should change here?"
                            class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-key/40"></textarea>
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
                    <div class="mt-3 flex flex-col gap-2" x-show="allFindings().length">
                        <p class="text-sm font-medium text-zinc-700">Hints</p>
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
                                    <span class="rounded-md bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-800"
                                        x-show="hasRegion(f)" x-text="findingSourceLabel(f)"></span>
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700" x-text="f.body"></p>
                            </button>
                        </template>
                    </div>

                </div>

                {{-- BOARD VIEW --}}
                <div class="mt-3" x-show="view === 'board'">
                    <div class="hatch rounded-2xl px-6 py-8 text-center text-zinc-300" x-show="!boardPins().length">
                        <p class="text-sm font-semibold text-zinc-900">No marks yet</p>
                        <p class="mt-1 text-sm text-muted-foreground">Marks you make on the screenshot land here.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" x-show="boardPins().length">
                    <template x-for="col in boardColumns" :key="col.status">
                        <div class="flex min-h-[8rem] flex-col rounded-2xl bg-well p-3">
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
                                            <p class="mt-0.5 text-xs text-muted-foreground" x-text="col.owner"></p>
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
                                            <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-500"
                                                x-show="pin._from_parent" x-text="'P' + pin._pass"></span>
                                            <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600"
                                                x-show="pin.comment_count > 0" x-text="pin.comment_count"></span>
                                        </div>
                                        <p class="text-sm leading-relaxed text-zinc-700" x-text="pin.body"></p>
                                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900"
                                            x-show="pin.resolution_note" @click.stop>
                                            <span class="font-medium">Agent:</span>
                                            <span x-text="pin.resolution_note"></span>
                                        </div>
                                    </button>
                                </template>
                                <p class="hatch rounded-xl px-3 py-6 text-center text-xs text-muted-foreground"
                                    x-show="!pinsByStatus(col.status).length" x-text="col.empty"></p>
                            </div>
                        </div>
                    </template>
                    </div>
                </div>

                {{-- Mark detail, opened from the board --}}
                <div x-show="view === 'board' && activePin" x-cloak>@include('mcp.partials.mark-detail')</div>

                {{-- decision bar --}}
                <div class="mt-4 rounded-2xl bg-card px-3 py-3 sm:px-4" x-show="isPending">
                    <input type="text" x-model="decisionNote" placeholder="Optional note for the agent…"
                        class="h-8 w-full rounded-lg border border-input bg-background px-3 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-key/40">
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" class="inline-flex h-8 items-center btn-lit rounded-full px-3 text-sm font-medium disabled:opacity-50"
                            :disabled="busy" @click="decide('approved')">Approve</button>
                        <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-sm font-medium text-zinc-800 transition hover:bg-chip-hover disabled:opacity-50"
                            :disabled="busy" @click="decide('changes_requested')">Changes</button>
                        <button type="button" class="inline-flex h-8 items-center rounded-md px-3 text-sm font-medium text-zinc-500 transition hover:text-zinc-800"
                            @click="openFullReview()">Open full review</button>
                    </div>
                </div>

                <div class="mt-4 space-y-3" x-show="!isPending">
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
                    <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-sm font-medium text-zinc-700 transition hover:bg-chip-hover"
                        @click="openFullReview()">Open full review</button>
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
                if (msg.params?.theme) { hostTheme = msg.params.theme; applyTheme(); }
                return;
            }

            // Requests from the host (e.g. teardown) — acknowledge.
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
                appInfo: { name: 'ReviseMy review', version: @json(config('revisemy.version')) },
                appCapabilities: { availableDisplayModes: ['inline'] },
            });
            hostTheme = result?.hostContext?.theme || null;
            applyTheme();
            send({ jsonrpc: '2.0', method: 'ui/notifications/initialized', params: {} });
        }

        window.mcpBridge = {
            connect,
            set ontoolresult(fn) { onToolResult = fn; if (fn && lastToolResult) fn(lastToolResult); },
            set ontoolinput(fn) { onToolInput = fn; if (fn && lastToolInput) fn(lastToolInput); },
            set ontoolcancelled(fn) { onToolCancelled = fn; if (fn && lastCancelReason !== null) fn(lastCancelReason); },
            callTool: (name, args) => request('tools/call', { name, arguments: args || {} }),
            openLink: (url) => request('ui/open-link', { url }),
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
        const STATUS_LABELS = { 'open': 'Open', 'in_progress': 'In progress', 'resolved': 'Resolved', 'verified': 'Verified' };

        return {
            payload: null,
            view: 'screenshot',
            activeIndex: 0,
            activePin: null,
            activeFinding: null,
            busy: false,
            error: '',
            working: { title: 'Opening the review', detail: '' },
            elapsed: 0,
            failed: '',
            failedTitle: '',
            decisionNote: '',
            previousOpen: false,
            draft: { drawing: false, x0: 0, y0: 0, x: 0, y: 0, w: 0, h: 0 },
            composer: { open: false, x: 0, y: 0, area: null, severity: 'must-fix', body: '' },
            severities: [
                { value: 'must-fix', label: 'Must fix' },
                { value: 'nit', label: 'Nice to have' },
                { value: 'question', label: 'Question' },
                { value: 'keep', label: 'Keep this' },
            ],
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
            canManagePin(pin) { return pin && pin.severity !== 'keep' && pin.status !== 'open'; },

            hasRegion(f) {
                const a = f && f.area;
                return !!(a && Number(a.w) >= 0.01 && Number(a.h) >= 0.01);
            },
            allFindings() {
                const shot = this.activeShot();
                return shot && Array.isArray(shot.second_opinion) ? shot.second_opinion : [];
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
            findingSourceLabel(f) {
                const s = (f && f.source) || 'checklist';
                if (s === 'openai' || s === 'anthropic') return 'Vision';
                if (s === 'agent') return 'Agent';
                if (s === 'guest') return f.author || 'Guest';
                return 'Checklist';
            },


            // Counted over the same marks the board shows (this pass and the one
            // before), so a previous pass can't make the bar under-report.
            verifiedCount() {
                return this.boardPins().filter((p) => p.status === 'verified').length;
            },
            verifiedPct() {
                const total = this.boardPins().length;
                return total ? Math.round((this.verifiedCount() / total) * 100) : 0;
            },
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
                    return a.number - b.number;
                });
            },

            pinsByStatus(status) { return this.boardPins().filter((p) => p.status === status); },

            showPin(pin) {
                this.activeFinding = null;
                this.activePin = this.activePin && this.activePin.id === pin.id ? null : pin;
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
            bgStyle(pin) {
                const w = pin && pin.focus_preview && pin.focus_preview.window;
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
                // Touch scrolls the capture viewport; draw with mouse/pen only.
                if (e.pointerType === 'touch') return;
                if (e.button != null && e.button !== 0) return;
                const p = this.norm(e);
                if (! p) return;
                this.draft = { drawing: true, x0: p.x, y0: p.y, x: p.x, y: p.y, w: 0, h: 0 };
            },
            moveDraw(e) {
                if (!this.draft.drawing) return;
                const p = this.norm(e);
                if (! p) return;
                this.draft.x = p.x; this.draft.y = p.y;
                this.draft.w = Math.abs(p.x - this.draft.x0);
                this.draft.h = Math.abs(p.y - this.draft.y0);
            },
            endDraw(e) {
                if (!this.draft.drawing) return;
                this.draft.drawing = false;
                const p = this.norm(e) || { x: this.draft.x, y: this.draft.y };
                const w = Math.abs(p.x - this.draft.x0), h = Math.abs(p.y - this.draft.y0);
                if (w >= 0.01 && h >= 0.01) {
                    const x = Math.min(p.x, this.draft.x0), y = Math.min(p.y, this.draft.y0);
                    this.openComposer(x + w / 2, y + h / 2, { x, y, w, h });
                } else {
                    this.openComposer(p.x, p.y, null);
                }
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
                const ok = await this.run(() => window.mcpBridge.callTool('add_mark', {
                    review_id: this.payload.id,
                    screenshot_id: shot.id,
                    x: this.composer.x,
                    y: this.composer.y,
                    area: this.composer.area || undefined,
                    severity: this.composer.severity,
                    body: this.composer.body.trim(),
                }));
                // Keep the note on screen if the server refused it, so nothing typed is lost.
                if (ok) this.closeComposer();
            },

            async verifyMark(pin, action) {
                await this.run(() => window.mcpBridge.callTool('verify_mark', {
                    review_id: this.payload.id, mark_id: pin.id, action,
                }));
            },

            async decide(decision) {
                const ok = await this.run(() => window.mcpBridge.callTool('decide_review', {
                    review_id: this.payload.id, decision, note: this.decisionNote.trim() || undefined,
                }));
                if (ok) this.decisionNote = '';
            },

            async refresh() {
                await this.run(() => window.mcpBridge.callTool('get_review', { id: this.payload.id }));
            },

            openFullReview() {
                if (this.payload) window.mcpBridge.openLink(this.payload.review_url);
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
                    if (data && data.id) this.apply(data);
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
<script type="module">{!! $script !!}</script>
