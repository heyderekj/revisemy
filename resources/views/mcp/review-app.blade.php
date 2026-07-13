{{-- Inline MCP App UI for ReviseMy reviews. Rendered in a sandboxed iframe by
     MCP Apps hosts (Claude web/desktop, etc.). Styling mirrors the web review
     page and board 1:1 — same Tailwind utility classes, severity marker
     colors (Annotation::markerClass), status badges (statusBadgeClass), and
     board column chrome (boardColumnMeta). Tailwind + Alpine load from the
     CSP-allowlisted CDNs via Library::Tailwind / Library::Alpine; the bridge
     is inline. Talks to the host over the MCP Apps postMessage protocol and
     calls the app-only add_mark / decide_review / verify_mark server tools. --}}
{!! $libraryScripts !!}
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600">
<style>
    body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, sans-serif; }
    [x-cloak] { display: none !important; }
</style>

<div class="bg-white text-zinc-900" x-data="reviewApp()" x-init="init()" x-cloak>
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

                {{-- counts row, same chips as the page header --}}
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <template x-for="chip in countChips()" :key="chip.label">
                        <span class="inline-flex items-center rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600"
                            x-text="chip.label + ' ' + chip.value"></span>
                    </template>
                </div>

                {{-- toolbar: view toggle + verified progress (board header) + refresh --}}
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-lg border border-zinc-200 bg-zinc-100 p-0.5">
                        <button type="button" class="rounded-md px-3 py-1 text-xs font-medium transition"
                            :class="view === 'screenshot' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'screenshot'">Screenshot</button>
                        <button type="button" class="rounded-md px-3 py-1 text-xs font-medium transition"
                            :class="view === 'board' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900'"
                            @click="view = 'board'" x-text="'Board · ' + allPins().length"></button>
                    </div>
                    <div class="flex min-w-24 flex-1 items-center gap-2 sm:max-w-48">
                        <span class="shrink-0 text-xs tabular-nums text-zinc-500"
                            x-text="payload.loop.verified_count + '/' + allPins().length"></span>
                        <div class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-zinc-200/80" role="progressbar" aria-label="Marks verified">
                            <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300 ease-out" :style="'width:' + verifiedPct() + '%'"></div>
                        </div>
                    </div>
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80 disabled:opacity-50"
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
                                            class="absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white shadow-lg ring-2 ring-white transition"
                                            :class="markerBg(pin.severity) + (isSettled(pin) ? ' opacity-60' : '') + (activeNote && activeNote.key === 'p'+pin.id ? ' ring-zinc-900' : '')"
                                            :style="pinStyle(pin)" x-text="'M' + pin.number"
                                            @pointerdown.stop @pointerup.stop @click.stop="showPinNote(pin)"></button>
                                    </div>
                                </template>

                                {{-- second-opinion hints: dashed sky region + corner S# badge (vision only) --}}
                                <template x-for="(f, fi) in activeShot().second_opinion" :key="'s'+fi">
                                    <template x-if="f.area && f.area.w >= 0.01 && f.area.h >= 0.01">
                                        <div class="absolute" :style="rectStyle(f.area)">
                                            <div class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-sky-400/80 bg-sky-400/10"></div>
                                            <button type="button"
                                                class="absolute -left-2 -top-2 z-[6] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-0.5 text-[10px] font-semibold text-sky-700 shadow-sm transition"
                                                :class="activeNote && activeNote.key === 's'+fi ? 'ring-2 ring-sky-300' : ''"
                                                x-text="'S' + (fi + 1)"
                                                @pointerdown.stop @pointerup.stop @click.stop="showFindingNote(f, fi)"></button>
                                        </div>
                                    </template>
                                </template>

                                {{-- draft rectangle / pending composer pin (rose dashed, like the page) --}}
                                <div class="pointer-events-none absolute z-[15] rounded-md border-2 border-dashed border-rose-500 bg-rose-500/15"
                                    x-show="draft.drawing && draft.w > 0.01" :style="draftRectStyle()"></div>
                                <div class="pointer-events-none absolute z-[18] rounded-md border-2 border-dashed border-rose-500 bg-rose-500/15"
                                    x-show="composer.open && composer.area" :style="composer.area ? rectStyle(composer.area) : ''"></div>
                                <div class="absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-rose-500 text-xs font-semibold text-white shadow-lg ring-2 ring-white"
                                    x-show="composer.open && !composer.area" :style="pinStyle(composer)">+</div>
                            </div>
                        </div>
                    </template>

                    {{-- tapped-marker note (board mark-detail card styling) --}}
                    <div class="mt-3 rounded-2xl border border-zinc-200 bg-white p-3 shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]" x-show="activeNote" x-cloak>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold shadow-sm"
                                    :class="activeNote && activeNote.sky ? 'border-2 border-dashed border-sky-500 bg-white text-sky-700' : 'text-white ring-2 ring-white ' + markerBg(activeNote ? activeNote.severity : '')"
                                    x-text="activeNote && activeNote.label"></span>
                                <span class="text-xs text-zinc-500" x-text="activeNote && severityLabel(activeNote.severity)"></span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" x-show="activeNote && activeNote.status"
                                    :class="statusBadge(activeNote ? activeNote.status : '')"
                                    x-text="activeNote && statusLabel(activeNote.status)"></span>
                            </div>
                            <button type="button" class="text-zinc-400 transition hover:text-zinc-600" @click="activeNote = null" aria-label="Close">×</button>
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-700" x-text="activeNote && activeNote.body"></p>
                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900"
                            x-show="activeNote && activeNote.resolution">
                            <span class="font-medium">Agent:</span> <span x-text="activeNote && activeNote.resolution"></span>
                        </div>
                    </div>

                    <p class="mt-2 text-xs text-zinc-400" x-show="isPending">Click a spot or drag a box on the screenshot to leave a mark. Click a numbered mark to read it.</p>

                    {{-- mark composer (page composer chrome) --}}
                    <div class="mt-3 rounded-xl border border-zinc-200/80 bg-zinc-50/90 px-3 py-2.5 sm:px-4" x-show="composer.open" @keydown.escape="closeComposer()">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="sev in severities" :key="sev.value">
                                <button type="button"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm transition"
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
                            <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-50"
                                :disabled="busy || !composer.body.trim()" @click="saveMark()">Add mark</button>
                            <button type="button" class="rounded-lg bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200/80"
                                @click="closeComposer()">Cancel</button>
                        </div>
                    </div>

                    {{-- linear mark list (board card styling; the board view groups by status) --}}
                    <div class="mt-3 flex flex-col gap-2" x-show="allPins().length">
                        <template x-for="pin in allPins()" :key="'l'+pin.id">
                            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm">
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white"
                                        :class="markerBg(pin.severity)" x-text="'M' + pin.number"></span>
                                    <span class="text-xs text-zinc-500" x-text="severityLabel(pin.severity)"></span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge(pin.status)" x-text="statusLabel(pin.status)"></span>
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700" x-text="pin.body"></p>
                                <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900" x-show="pin.resolution_note">
                                    <span class="font-medium">Agent:</span> <span x-text="pin.resolution_note"></span>
                                </div>
                                <div class="mt-2 flex gap-2" x-show="pin.status === 'resolved'">
                                    <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-rose-700 disabled:opacity-50"
                                        :disabled="busy" @click="verifyMark(pin, 'verify')">Verify</button>
                                    <button type="button" class="rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80 disabled:opacity-50"
                                        :disabled="busy" @click="verifyMark(pin, 'reopen')">Reopen</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- BOARD VIEW (mirrors /r/{token}/board columns) --}}
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" x-show="view === 'board'">
                    <template x-for="col in boardColumns" :key="col.status">
                        <div class="flex min-h-[8rem] flex-col rounded-2xl border p-3 transition"
                            :class="col.status === 'in_progress' ? 'border-dashed border-zinc-200/90 bg-zinc-50/80' : 'border-zinc-200 bg-white/70'">
                            <div class="mb-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-start gap-2">
                                        <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100" aria-hidden="true">
                                            {{-- heroicon micro: flag / cpu-chip / check-circle / shield-check (same as boardColumnMeta) --}}
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
                                    <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm transition hover:border-zinc-300 hover:shadow-md">
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white"
                                                :class="markerBg(pin.severity)" x-text="'M' + pin.number"></span>
                                            <span class="text-xs text-zinc-500" x-text="severityLabel(pin.severity)"></span>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge(pin.status)" x-text="statusLabel(pin.status)"></span>
                                        </div>
                                        <p class="text-sm leading-relaxed text-zinc-700" x-text="pin.body"></p>
                                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900" x-show="pin.resolution_note">
                                            <span class="font-medium">Agent:</span> <span x-text="pin.resolution_note"></span>
                                        </div>
                                        <div class="mt-2 flex gap-2" x-show="pin.status === 'resolved' || pin.status === 'verified'">
                                            <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-rose-700 disabled:opacity-50"
                                                x-show="pin.status === 'resolved'" :disabled="busy" @click="verifyMark(pin, 'verify')">Verify</button>
                                            <button type="button" class="rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80 disabled:opacity-50"
                                                :disabled="busy" @click="verifyMark(pin, 'reopen')">Reopen</button>
                                        </div>
                                    </div>
                                </template>
                                <p class="rounded-xl border border-dashed border-zinc-200 px-3 py-6 text-center text-xs text-zinc-400"
                                    x-show="!pinsByStatus(col.status).length" x-text="col.empty"></p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- decision bar (page action buttons: ghost Changes, rose Approve) --}}
                <div class="mt-4 rounded-xl border border-zinc-200/80 bg-zinc-50/90 px-3 py-2.5 sm:px-4" x-show="isPending">
                    <input type="text" x-model="decisionNote" placeholder="Optional note for the agent…"
                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-rose-500/30">
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-50"
                            :disabled="busy" @click="decide('approved')">Approve</button>
                        <button type="button" class="rounded-lg bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-800 transition hover:bg-zinc-200/80 disabled:opacity-50"
                            :disabled="busy" @click="decide('changes_requested')">Request changes</button>
                        <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-500 transition hover:text-zinc-800"
                            @click="openFullReview()">Open full review</button>
                    </div>
                </div>

                <div class="mt-3" x-show="!isPending">
                    <button type="button" class="rounded-lg bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200/80"
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
                appInfo: { name: 'ReviseMy review', version: '1.0.0' },
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
        // Mirrors of the web app's design maps — keep in sync with
        // Annotation::markerClass(), severityLabels(), statusBadgeClass(),
        // statusLabels(), and boardColumnMeta().
        const MARKER_BG = {
            'must-fix': 'bg-rose-600', 'nit': 'bg-rose-600', 'question': 'bg-rose-600', 'keep': 'bg-rose-600',
            'wording': 'bg-rose-600', 'spacing': 'bg-rose-600', 'size': 'bg-rose-600', 'color': 'bg-rose-600', 'alignment': 'bg-rose-600',
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
            activeNote: null,
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
            boardColumns: [
                { status: 'open', label: 'Open', owner: 'You', empty: 'No open marks' },
                { status: 'in_progress', label: 'In progress', owner: 'Agent', empty: 'Agent starts fixes here' },
                { status: 'resolved', label: 'Resolved', owner: 'You or agent', empty: 'Nothing resolved yet' },
                { status: 'verified', label: 'Verified', owner: 'You', empty: 'Nothing verified yet' },
            ],

            init() {
                // The bridge buffers a result pushed before this handler is set,
                // so registering here always receives the initial payload.
                window.mcpBridge.ontoolresult = (params) => {
                    const data = params.structuredContent;
                    if (data && data.id) this.apply(data);
                };

                // While the agent is applying fixes, poll so the board reflects
                // its progress live — the inline echo of the review page's
                // broadcasting. Stops as soon as the review is decided again.
                setInterval(() => {
                    if (this.payload && this.payload.status === 'changes_requested' && !this.busy) {
                        this.refresh();
                    }
                }, 12000);
            },

            apply(data) {
                this.payload = data;
                if (this.activeIndex >= data.screenshots.length) this.activeIndex = 0;
                this.error = '';
                // Drop a stale open note if its mark no longer exists.
                if (this.activeNote && this.activeNote.key[0] === 'p'
                    && !this.allPins().some((p) => 'p' + p.id === this.activeNote.key)) {
                    this.activeNote = null;
                }
            },

            get isPending() { return this.payload && this.payload.status === 'pending'; },

            markerBg(severity) { return MARKER_BG[severity] || 'bg-rose-600'; },
            severityLabel(severity) { return SEVERITY_LABELS[severity] || severity; },
            statusBadge(status) { return STATUS_BADGE[status] || 'bg-zinc-100 text-zinc-600'; },
            statusLabel(status) { return STATUS_LABELS[status] || status; },
            isSettled(pin) { return pin.status === 'resolved' || pin.status === 'verified'; },

            countChips() {
                const l = this.payload.loop;
                return [
                    { label: 'Must-fix', value: l.must_fix_count },
                    { label: 'Nits', value: l.nit_count },
                    { label: 'Questions', value: l.question_count },
                    { label: 'Resolved', value: l.resolved_count },
                    { label: 'Verified', value: l.verified_count },
                ];
            },

            verifiedPct() {
                const total = this.allPins().length;
                return total ? Math.round((this.payload.loop.verified_count / total) * 100) : 0;
            },

            pinsByStatus(status) { return this.allPins().filter((p) => p.status === status); },

            showPinNote(pin) {
                const key = 'p' + pin.id;
                this.activeNote = this.activeNote && this.activeNote.key === key ? null : {
                    key, sky: false, label: 'M' + pin.number, severity: pin.severity,
                    status: pin.status, body: pin.body, resolution: pin.resolution_note,
                };
            },

            showFindingNote(finding, index) {
                const key = 's' + index;
                this.activeNote = this.activeNote && this.activeNote.key === key ? null : {
                    key, sky: true, label: 'S' + (index + 1), severity: finding.severity,
                    status: '', body: finding.body, resolution: '',
                };
            },

            shotLabel(shot, i) {
                const v = shot.meta && shot.meta.viewport;
                const page = shot.meta && shot.meta.page;
                if (page) return 'Page ' + page;
                if (v) return v.charAt(0).toUpperCase() + v.slice(1);
                return 'Shot ' + (i + 1);
            },

            setActive(i) { this.activeIndex = i; this.closeComposer(); this.activeNote = null; },
            activeShot() { return this.payload ? this.payload.screenshots[this.activeIndex] : null; },
            allPins() {
                if (!this.payload) return [];
                return this.payload.screenshots.flatMap((s) => s.pins);
            },

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
