{{-- One mark in the list, this pass or the one before ($previous, paler).
     Same shape as the web sidebar's mark card. --}}
@php($previous = $previous ?? false)
<div @class([
        'rounded-xl p-3 text-left transition-shadow',
        'bg-raised shadow-xs ring-1 ring-black/[0.04]' => ! $previous,
        'bg-well' => $previous,
    ])
    :class="activePin && activePin.id === pin.id && '!ring-2 !ring-key'">
    <button type="button" class="w-full text-left" @click="showPin(pin)">
        <div class="mb-1 flex flex-wrap items-center gap-2">
            <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold" :class="markerBg(pin.severity)" x-text="'M' + pin.number"></span>
            <span class="text-xs text-muted-foreground" x-text="severityLabel(pin.severity)"></span>
            <span class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-4" :class="statusBadge(pin.status)"><span class="size-1.5 shrink-0 rounded-full" :class="statusDot(pin.status)" aria-hidden="true"></span><span x-text="statusLabel(pin.status)"></span></span>
            <span class="text-xs tabular-nums text-zinc-400" x-show="pin.comment_count > 0" x-text="pin.comment_count + (pin.comment_count === 1 ? ' comment' : ' comments')"></span>
        </div>
        <p class="text-sm leading-relaxed text-zinc-700" x-text="pin.body"></p>
    </button>
    <p class="mt-2 rounded-lg bg-done-soft px-2.5 py-1.5 text-xs leading-relaxed text-done-ink" x-show="pin.resolution_note">
        <span class="font-medium">Agent:</span> <span x-text="pin.resolution_note"></span>
    </p>
    @include('mcp.partials.before-after', ['pin' => 'pin'])
    <div class="mt-2 flex flex-wrap items-center gap-1.5" x-show="canManagePin(pin)">
        <button type="button" class="inline-flex h-7 items-center rounded-full bg-done-soft px-2.5 text-xs font-medium text-done-ink transition-colors hover:bg-emerald-200 disabled:opacity-50"
            x-show="pin.status === 'resolved'" :disabled="busy" @click.stop="verifyMark(pin, 'verify')">Verify</button>
        <button type="button" class="inline-flex h-7 items-center rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover disabled:opacity-50"
            x-show="pin.status !== 'open'" :disabled="busy" @click.stop="verifyMark(pin, 'reopen')">Reopen</button>
    </div>
</div>
