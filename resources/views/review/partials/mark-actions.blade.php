{{-- A mark's buttons and its comment / answer box. Shared by the sidebar
     card and the mark popover; the box shows in only one of them at a time
     ($inPopover says which this is). --}}
@php($previous = $previous ?? false)
@php($inPopover = $inPopover ?? false)
@php($canManage = $mode === 'owner' && $pin->severity !== \App\Models\Annotation::SEVERITY_KEEP && $this->canManageMarks())
@php($boxHere = $inPopover === ($openMarkId === $pin->id))
@if (! $previous && $boxHere && $activeCommentMarkId === $pin->id)
    <div class="mt-2 space-y-2">
        @if ($mode === 'guest')
            <flux:input
                wire:model="guestName"
                placeholder="Your name"
                maxlength="40"
                size="sm"
                class="max-md:[&_input]:!text-base"
                x-data
                x-init="if (! $wire.guestName) { $wire.guestName = localStorage.getItem('revisemy_guest_name') || '' }"
                x-on:change="if ($event.target.value) { localStorage.setItem('revisemy_guest_name', $event.target.value) }"
            />
            <flux:error name="guestName" />
        @endif
        <x-element-reference-chips :references="$markCommentBodyReferences" action="toggleCommentElementReference" :mark-id="$pin->id" />
        <flux:textarea wire:model="markCommentBody" rows="2" placeholder="Add a comment…" x-on:keydown.meta.enter.prevent="$wire.addMarkComment({{ $pin->id }})" x-on:keydown.ctrl.enter.prevent="$wire.addMarkComment({{ $pin->id }})" />
        <flux:error name="markCommentBody" />
        <div class="flex gap-2">
            <flux:button size="sm" variant="primary" wire:click="addMarkComment({{ $pin->id }})">Post</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="cancelMarkComment">Cancel</flux:button>
        </div>
    </div>
@elseif (! $previous && $boxHere && $mode === 'owner' && $answeringMarkId === $pin->id)
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
        @if ($canManage && $pin->awaitsVerification())
            <button type="button" class="inline-flex h-9 items-center md:h-7 gap-1 rounded-full bg-done-soft px-2.5 text-xs font-medium text-done-ink transition-colors hover:bg-emerald-200" wire:click="verifyMark({{ $pin->id }})">
                <flux:icon.check variant="micro" class="size-3.5" /> Verify
            </button>
        @endif
        @if ($canManage && $pin->status !== \App\Models\Annotation::STATUS_OPEN)
            <button type="button" class="inline-flex h-9 items-center md:h-7 rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover" wire:click="reopenMark({{ $pin->id }})">Reopen</button>
        @endif
        @if (! $previous && $mode === 'owner' && $pin->severity === \App\Models\Annotation::SEVERITY_QUESTION && $review->isOpenForFeedback())
            <button type="button" class="inline-flex h-9 items-center md:h-7 rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover" wire:click="startAnswerQuestion({{ $pin->id }})">{{ $pin->question_answer ? 'Edit answer' : 'Answer' }}</button>
        @endif
        @if (! $previous && $mode === 'owner' && ! $review->isExpired() && $review->allowsComments())
            <button type="button" class="inline-flex h-9 items-center rounded-full px-2 text-xs font-medium text-zinc-500 transition-colors hover:bg-chip hover:text-zinc-900 md:h-7"
                wire:click="startFeedbackRequest({{ $pin->id }})">Request feedback</button>
        @endif
        @if (! $previous && $review->allowsComments())
            <button type="button" class="inline-flex h-9 items-center md:h-7 gap-1 rounded-full px-2 text-xs font-medium text-zinc-500 transition-colors hover:bg-chip hover:text-zinc-900" wire:click="startMarkComment({{ $pin->id }})">
                Comment
                @if ($pin->comments->isNotEmpty())
                    <span class="tabular-nums text-zinc-400">{{ $pin->comments->count() }}</span>
                @endif
            </button>
        @endif
    </div>
@endif
