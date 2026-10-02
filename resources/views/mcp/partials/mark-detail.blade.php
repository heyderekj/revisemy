{{-- The open mark: focus crop, the feedback, the fix, and verify/reopen. One
     copy for the screenshot and board views (parity with the board's sheet). --}}
<div class="mt-3 overflow-hidden rounded-2xl bg-lift shadow-[0_18px_50px_-24px_rgba(0,0,0,0.45)] ring-1 ring-black/[0.06]">
    <div class="flex items-start justify-between gap-3 px-3 py-3 sm:px-4">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <span class="flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-xs font-semibold" :class="markerBg()" x-text="activePin && ('M' + activePin.number)"></span>
            <span class="text-xs text-muted-foreground" x-text="activePin && severityLabel(activePin.severity)"></span>
            <span class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-4" :class="statusBadge(activePin ? activePin.status : '')" x-show="activePin"><span class="size-1.5 shrink-0 rounded-full" :class="statusDot(activePin ? activePin.status : '')" aria-hidden="true"></span><span x-text="activePin && statusLabel(activePin.status)"></span></span>
            <span class="text-xs text-zinc-400" x-show="activePin && activePin._from_parent" x-text="activePin && ('From pass ' + activePin._pass)"></span>
        </div>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-full text-zinc-400 transition-colors hover:bg-chip hover:text-zinc-700" @click="closeDetail()" aria-label="Close">×</button>
    </div>

    <template x-if="activePin && activePin.focus_preview">
        <div class="w-full max-h-[min(40dvh,22rem)] overflow-hidden bg-well">
            <div class="relative w-full bg-no-repeat"
                :style="'aspect-ratio:' + Math.max(activePin.focus_preview.ratio || 1.6, 0.01) + ';' + bgStyle(activePin)"
                role="img" :aria-label="'Cropped screenshot focused on mark M' + activePin.number">
                <template x-if="activePin.focus_preview.overlay">
                    <div class="pointer-events-none absolute rounded-md border-2 border-key bg-key/15" :style="rectStyle(activePin.focus_preview.overlay)"></div>
                </template>
                <template x-if="activePin.focus_preview.point">
                    <span class="pointer-events-none absolute flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-xs font-semibold shadow ring-2 ring-white"
                        :class="markerBg()" :style="pinStyle(activePin.focus_preview.point)" x-text="'M' + activePin.number"></span>
                </template>
            </div>
        </div>
    </template>

    <div class="space-y-3 px-3 py-3 sm:px-4">
        <p class="text-sm leading-relaxed text-pretty text-zinc-800" x-text="activePin && activePin.body"></p>
        <p class="rounded-lg bg-done-soft px-3 py-2 text-sm text-done-ink" x-show="activePin && activePin.resolution_note">
            <span class="font-medium">Agent:</span> <span x-text="activePin && activePin.resolution_note"></span>
        </p>
        @include('mcp.partials.before-after', ['pin' => 'activePin'])
        <div class="flex flex-wrap items-center gap-2" x-show="activePin && (activePin.comment_count > 0 || canManagePin(activePin))">
            <template x-if="activePin && activePin.comment_count > 0">
                <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover" @click="openComments()"
                    x-text="'View ' + activePin.comment_count + (activePin.comment_count === 1 ? ' comment' : ' comments')"></button>
            </template>
            <div class="ml-auto flex flex-wrap gap-2" x-show="activePin && canManagePin(activePin)">
                <button type="button" class="inline-flex h-8 items-center rounded-full bg-done-soft px-3 text-xs font-medium text-done-ink transition-colors hover:bg-emerald-200 disabled:opacity-50"
                    x-show="activePin && activePin.status === 'resolved'" :disabled="busy" @click="verifyMark(activePin, 'verify')">Verify</button>
                <button type="button" class="inline-flex h-8 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover disabled:opacity-50"
                    x-show="activePin && activePin.status !== 'open'" :disabled="busy" @click="verifyMark(activePin, 'reopen')">Reopen</button>
            </div>
        </div>
    </div>
</div>
