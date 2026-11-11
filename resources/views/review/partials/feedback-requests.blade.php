<flux:modal name="feedback-requests" variant="flyout" position="right" :closable="false"
    class="!w-full !max-w-full !border-border !bg-well !p-0 sm:!max-w-[26rem] motion-reduce:!transition-none motion-reduce:backdrop:!transition-none"
    x-init="$el.querySelector('dialog').setAttribute('aria-labelledby', 'feedback-requests-title');
        if (new URLSearchParams(location.search).get('feedback') === '1') $nextTick(() => $dispatch('modal-show', { name: 'feedback-requests' }))">
    <header class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-border bg-well px-4 py-4 sm:px-5">
        <div class="min-w-0">
            <flux:heading size="lg" id="feedback-requests-title">Request feedback</flux:heading>
            <p class="mt-1 truncate text-xs text-muted-foreground">{{ $review->title }}</p>
        </div>
        <flux:modal.close>
            <flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Close feedback panel" class="!shrink-0 !rounded-full" />
        </flux:modal.close>
    </header>

    <div class="space-y-5 px-4 py-4 sm:px-5">
        @if (! $review->isExpired() && $review->allowsComments())
            <form wire:submit="createFeedbackRequest" class="space-y-3 rounded-xl border border-border bg-card p-4">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading>New request</flux:heading>
                    @if ($feedbackMarkId && ($target = $this->ownedAnnotation($feedbackMarkId)))
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $target->markerClass() }}">Mark M{{ $target->number }}</span>
                    @else
                        <span class="rounded-full bg-chip px-2 py-0.5 text-xs font-medium text-muted-foreground">Whole review</span>
                    @endif
                </div>
                <flux:radio.group wire:model.live="feedbackAudience" label="Ask" variant="segmented">
                    <flux:radio value="person" label="One person" />
                    <flux:radio value="group" label="A group" />
                </flux:radio.group>
                @if ($feedbackAudience === 'group')
                    <div wire:key="feedback-group-names">
                        <flux:textarea wire:model="feedbackGroupNames" label="Reviewer names" description="Enter one name per line, up to 12 people." rows="3" maxlength="1000" required />
                    </div>
                @else
                    <div wire:key="feedback-person-name">
                        <flux:input wire:model="feedbackName" label="Reviewer name" placeholder="e.g. Maya" maxlength="40" required />
                    </div>
                @endif
                <flux:textarea wire:model="feedbackQuestion" label="What should they look at?" placeholder="e.g. Does the button label make sense?" rows="3" maxlength="1000" required />
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="createFeedbackRequest">{{ $feedbackAudience === 'group' ? 'Create personal links' : 'Create personal link' }}</flux:button>
            </form>
        @elseif (! $review->isExpired())
            <p class="text-sm text-muted-foreground">Turn on comments in Share to ask for feedback.</p>
        @endif

        @if ($showFeedbackRequests && ($requests = $this->feedbackRequests)->isNotEmpty())
            <section aria-labelledby="feedback-list-title" class="space-y-2">
                <flux:heading id="feedback-list-title">Feedback requests</flux:heading>
                @foreach ($requests as $key => $members)
                    @if ($members->first()->group_id)
                        @php($newGroup = $members->contains('id', $createdFeedbackId))
                        <details wire:key="feedback-group-{{ $key }}-{{ $newGroup ? 'new' : 'existing' }}"
                            @if ($newGroup) open @endif class="rounded-xl border border-border bg-card">
                            <summary class="cursor-pointer rounded-xl px-3 py-2.5 text-sm hover:bg-chip">
                                <span class="font-medium">Group request</span>
                                <span class="ml-1 text-xs text-muted-foreground">{{ $members->whereNotNull('responded_at')->count() }} of {{ $members->count() }} replied</span>
                            </summary>
                            <div class="space-y-2 border-t border-border p-3">
                                <p class="whitespace-pre-wrap break-words text-sm text-zinc-800">{{ $members->first()->question }}</p>
                                @foreach ($members->reverse() as $feedback)
                                    @include('review.partials.feedback-request-row')
                                @endforeach
                            </div>
                        </details>
                    @else
                        @include('review.partials.feedback-request-row', ['feedback' => $members->first()])
                    @endif
                @endforeach
            </section>
        @endif
    </div>
</flux:modal>
