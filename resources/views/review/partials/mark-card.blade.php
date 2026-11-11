@php($previous = $previous ?? false)
<li id="fb-mark-{{ $pin->id }}"
    @class(['rounded-xl', 'bg-well p-3' => $previous, 'bg-raised ring-1 ring-black/[0.04]' => ! $previous])
    x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} && '!ring-2 !ring-key'">
    @if (! $previous)
        <button type="button"
            class="flex w-full items-start gap-2.5 rounded-xl p-3 text-left transition-colors hover:bg-chip focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-key"
            x-on:click.stop="
                if (window.innerWidth < 1024) $store.rmPanel.open = false;
                $store.rmFocus.open({{ $pin->id }}, 'list', false);
            "
            data-opens-mark>
            <span class="flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full px-1 text-xs font-semibold {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
            <span class="min-w-0 flex-1">
                <span class="line-clamp-2 text-sm leading-snug text-zinc-800">{{ $pin->body }}</span>
                <span class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                    <span>{{ $pin->statusLabel() }}</span>
                    @if ($pin->comments->isNotEmpty())
                        <span>{{ $pin->comments->count() }} {{ Str::plural('reply', $pin->comments->count()) }}</span>
                    @endif
                </span>
            </span>
            <flux:icon.chevron-right variant="micro" class="mt-1 size-3.5 shrink-0 text-zinc-400" />
        </button>
    @else
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
    @endif
</li>
