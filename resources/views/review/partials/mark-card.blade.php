{{-- One mark, on this pass or the one before ($previous). Included, not a
     component, because it reads the review page's own state ($this). --}}
@php($previous = $previous ?? false)
<li
    id="fb-mark-{{ $pin->id }}"
    @class([
        'rounded-xl p-3 transition-shadow',
        'cursor-pointer' => ! $previous,
        'bg-raised shadow-xs ring-1 ring-black/[0.04]' => ! $previous,
        'bg-well' => $previous,
    ])
    x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} && '!ring-2 !ring-key'"
    @unless ($previous)
        {{-- Opens the mark on the shot; the card's own buttons and fields keep their clicks. --}}
        x-on:click="
            if ($event.target.closest('button, a, input, textarea, select, label, summary, [contenteditable]')) return;
            $event.stopPropagation();
            if (window.innerWidth < 1024) $store.rmPanel.open = false;
            $store.rmFocus.open({{ $pin->id }}, 'list', false);
        "
        data-opens-mark
    @endunless
>
    <div class="mb-1 flex items-center justify-between gap-2">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
            <span class="text-xs text-muted-foreground">{{ $pin->label() }}</span>
            <x-signal-tag :tone="$pin->statusTone()">{{ $pin->statusLabel() }}</x-signal-tag>
            @if ($pin->changedByAgent())
                <x-agent-tag />
            @endif
            @if ($pin->source && $pin->source !== \App\Models\Annotation::SOURCE_HUMAN)
                <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-600">{{ $pin->sourceLabel() }}</span>
            @endif
        </div>
        @if (! $previous && $review->isOpenForFeedback() && $mode === 'owner')
            <button type="button" class="shrink-0 text-xs text-zinc-400 transition-colors hover:text-zinc-900" wire:click="deletePin({{ $pin->id }})">Remove</button>
        @endif
    </div>

    @if ($pin->target)
        <x-element-chip :target="$pin->target" class="mb-1.5" />
    @endif
    <p class="text-sm leading-relaxed text-zinc-700">{{ $pin->body }}</p>

    @if ($pin->suggested_copy)
        <p class="mt-2 rounded-lg bg-well px-2.5 py-1.5 text-xs leading-relaxed text-zinc-700">
            <span class="text-muted-foreground">Suggested copy:</span> <span class="font-mono">{{ $pin->suggested_copy }}</span>
        </p>
    @endif
    @if ($pin->question_answer)
        <p class="mt-2 rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs leading-relaxed text-sky-900">
            <span class="font-medium">Answer:</span> {{ $pin->question_answer }}
        </p>
    @endif
    <x-mark-before-after :mark="$pin" />

    {{-- Who did what on this mark, in order: steps, notes and comments. --}}
    <x-mark-timeline :mark="$pin" :owner-view="$mode === 'owner'" class="mt-3" wire:key="pin-timeline-{{ $pin->id }}-{{ $pin->updated_at?->timestamp }}" />

    @include('review.partials.mark-actions')
</li>
