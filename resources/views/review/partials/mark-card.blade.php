{{-- One mark, on this pass or the one before ($previous). Included, not a
     component, because it reads the review page's own state ($this). --}}
@php($previous = $previous ?? false)
@php($canManage = $mode === 'owner' && $pin->severity !== \App\Models\Annotation::SEVERITY_KEEP && $this->canManageMarks())
<li
    id="fb-mark-{{ $pin->id }}"
    @class([
        'rounded-xl p-3 transition-shadow',
        'bg-raised shadow-xs ring-1 ring-black/[0.04]' => ! $previous,
        'bg-well' => $previous,
    ])
    x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} && '!ring-2 !ring-key'"
    x-on:mouseenter="$store.rmFocus && ($store.rmFocus.hover = {{ $pin->id }})"
    x-on:mouseleave="$store.rmFocus && $store.rmFocus.hover === {{ $pin->id }} && ($store.rmFocus.hover = null)"
>
    <div class="mb-1 flex items-center justify-between gap-2">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
            <span class="text-xs text-muted-foreground">{{ $pin->label() }}</span>
            <x-signal-tag :tone="$pin->statusTone()">{{ $pin->statusLabel() }}</x-signal-tag>
            @if ($pin->source && $pin->source !== \App\Models\Annotation::SOURCE_HUMAN)
                <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium text-zinc-600">{{ $pin->sourceLabel() }}</span>
            @endif
        </div>
        @if (! $previous && $review->isOpenForFeedback() && $mode === 'owner')
            <button type="button" class="shrink-0 text-xs text-zinc-400 transition-colors hover:text-zinc-900" wire:click="deletePin({{ $pin->id }})">Remove</button>
        @endif
    </div>

    <p class="text-sm leading-relaxed text-zinc-700">{{ $pin->body }}</p>
    @if ($elementLabel = $pin->elementLabel())
        <p class="mt-1 truncate text-xs text-muted-foreground" title="{{ $pin->element['selector'] ?? '' }}">On {{ $elementLabel }}</p>
    @endif
    @if ($pin->missingInNextPass())
        <p class="mt-1 text-xs text-attention-ink">Not on the page any more</p>
    @endif

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
    @if ($pin->resolution_note)
        <p class="mt-2 rounded-lg bg-done-soft px-2.5 py-1.5 text-xs leading-relaxed text-done-ink">
            <span class="font-medium">Agent:</span> {{ $pin->resolution_note }}
        </p>
    @endif
    <x-mark-before-after :mark="$pin" />

    @if ($pin->comments->isNotEmpty())
        <div class="mt-2 space-y-1.5">
            @foreach ($pin->comments as $comment)
                <div wire:key="pin-comment-{{ $comment->id }}" class="rounded-lg bg-well px-2.5 py-1.5">
                    <p class="mb-0.5 flex flex-wrap items-baseline justify-between gap-x-2 text-xs">
                        <span class="font-medium text-zinc-700">{{ $comment->author }}</span>
                        <time class="text-zinc-400" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time>
                    </p>
                    <p class="text-xs leading-relaxed text-zinc-600">{{ $comment->body }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if (! $previous && $activeCommentMarkId === $pin->id)
        <div class="mt-2 space-y-2">
            @if ($mode === 'guest')
                <flux:input
                    wire:model="guestName"
                    placeholder="Your name"
                    maxlength="40"
                    size="sm"
                    x-data
                    x-init="if (! $wire.guestName) { $wire.guestName = localStorage.getItem('revisemy_guest_name') || '' }"
                    x-on:change="if ($event.target.value) { localStorage.setItem('revisemy_guest_name', $event.target.value) }"
                />
                <flux:error name="guestName" />
            @endif
            <flux:textarea wire:model="markCommentBody" rows="2" placeholder="Add a comment…" x-on:keydown.meta.enter.prevent="$wire.addMarkComment({{ $pin->id }})" x-on:keydown.ctrl.enter.prevent="$wire.addMarkComment({{ $pin->id }})" />
            <flux:error name="markCommentBody" />
            <div class="flex gap-2">
                <flux:button size="sm" variant="primary" wire:click="addMarkComment({{ $pin->id }})">Post</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancelMarkComment">Cancel</flux:button>
            </div>
        </div>
    @elseif (! $previous && $mode === 'owner' && $answeringMarkId === $pin->id)
        <div class="mt-2 space-y-2">
            <flux:textarea wire:model="questionAnswerDraft" rows="2" placeholder="Answer for the agent…" />
            <flux:error name="questionAnswerDraft" />
            <div class="flex gap-2">
                <flux:button size="sm" variant="primary" wire:click="answerQuestion({{ $pin->id }})">Save answer</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancelAnswerQuestion">Cancel</flux:button>
            </div>
        </div>
    @else
        {{-- Quiet controls: the only filled button on the page is Approve. --}}
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            @if ($canManage && $pin->awaitsVerification() && $pin->looksLive())
                <span class="inline-flex h-7 items-center rounded-full px-1.5 text-xs font-medium text-done-ink" title="The suggested copy now shows on the page. Check it, then verify.">Looks live</span>
            @endif
            @if ($canManage && $pin->awaitsVerification())
                <button type="button" class="inline-flex h-7 items-center gap-1 rounded-full bg-done-soft px-2.5 text-xs font-medium text-done-ink transition-colors hover:bg-emerald-200" wire:click="verifyMark({{ $pin->id }})">
                    <flux:icon.check variant="micro" class="size-3.5" /> Verify
                </button>
            @endif
            @if ($canManage && $pin->status !== \App\Models\Annotation::STATUS_OPEN)
                <button type="button" class="inline-flex h-7 items-center rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover" wire:click="reopenMark({{ $pin->id }})">Reopen</button>
            @endif
            @if (! $previous && $mode === 'owner' && $pin->severity === \App\Models\Annotation::SEVERITY_QUESTION && $review->isOpenForFeedback())
                <button type="button" class="inline-flex h-7 items-center rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover" wire:click="startAnswerQuestion({{ $pin->id }})">{{ $pin->question_answer ? 'Edit answer' : 'Answer' }}</button>
            @endif
            @if (! $previous && $review->allowsComments())
                <button type="button" class="inline-flex h-7 items-center gap-1 rounded-full px-2 text-xs font-medium text-zinc-500 transition-colors hover:bg-chip hover:text-zinc-900" wire:click="startMarkComment({{ $pin->id }})">
                    Comment
                    @if ($pin->comments->isNotEmpty())
                        <span class="tabular-nums text-zinc-400">{{ $pin->comments->count() }}</span>
                    @endif
                </button>
            @endif
        </div>
    @endif
</li>
