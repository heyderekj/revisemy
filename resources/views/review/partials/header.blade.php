    @if ($mode === 'guest' && ! $review->allowsGuestAccess())
        <header class="shrink-0 bg-well px-3 py-2">
            <a href="/" class="inline-flex" aria-label="ReviseMy home"><x-revisemy-logo size="sm" /></a>
        </header>
    @else
    <header class="relative z-40 shrink-0 bg-well">
        <div class="flex items-center gap-2 px-2 py-2 sm:gap-3 sm:px-3">
            <div class="flex shrink-0 items-center gap-1.5">
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo size="sm" />
                </a>
                @if ($this->canListReviews())
                    <a href="/reviews" class="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-chip hover:text-zinc-900" aria-label="Your reviews">
                        <flux:icon.queue-list variant="micro" class="size-4" />
                        <span class="sr-only">Your reviews</span>
                    </a>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                @if ($editingTitle && $this->isOwner() && $review->isOpenForFeedback())
                    <div class="flex min-w-0 items-center gap-1" wire:key="title-editor">
                        <h1 class="sr-only">{{ $review->title }}</h1>
                        <input
                            type="text"
                            wire:model="titleDraft"
                            maxlength="200"
                            class="min-w-0 flex-1 rounded-md bg-raised px-1.5 text-base font-semibold text-zinc-900 outline-none focus-visible:ring-2 focus-visible:ring-key/60"
                            x-data
                            x-init="$el.focus(); $el.select()"
                            x-on:keydown.enter.prevent="$wire.saveTitle()"
                            x-on:keydown.escape.prevent="$wire.cancelEditTitle()"
                            x-on:blur="$wire.blurSaveTitle()"
                            aria-label="Review title"
                        />
                        <button type="button" wire:click="saveTitle" x-on:mousedown.prevent
                            class="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-zinc-900 hover:bg-chip" aria-label="Save title">
                            <flux:icon.check variant="micro" class="size-3.5" />
                        </button>
                        <button type="button" wire:click="cancelEditTitle" x-on:mousedown.prevent
                            class="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-zinc-500 hover:bg-chip" aria-label="Cancel">
                            <flux:icon.x-mark variant="micro" class="size-3.5" />
                        </button>
                    </div>
                @else
                    <h1 class="min-w-0 truncate text-base leading-5 font-semibold text-zinc-900" wire:key="review-title">
                        @if ($this->isOwner() && $review->isOpenForFeedback())
                            <button type="button" wire:click="startEditTitle"
                                class="block max-w-full truncate rounded text-left hover:text-zinc-600 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-key"
                                title="Edit title: {{ $review->title }}">{{ $review->title }}</button>
                        @else
                            {{ $review->title }}
                        @endif
                    </h1>
                @endif

                @php
                    $openUntil = $this->isOwner() ? $review->openUntil() : $review->guestAccessUntil();
                    $closingSoon = $openUntil?->lt(now()->addHours(48));
                    $domain = $review->sourceKind() === \App\Models\Review::SOURCE_URL ? $review->sourceDomain() : null;
                    $source = $domain ?: $review->sourceKindLabel();
                    $capture = $this->activeScreenshot;
                    $captureStatus = $capture?->captureQuality()['status'] ?? null;
                    $captureState = $review->screenshots->count() === 1 ? ($capture?->meta['state'] ?? null) : null;
                    $captureLabel = match ($captureStatus) {
                        'failed' => 'Capture failed',
                        'partial' => 'Partial capture',
                        'unverified' => 'Capture unverified',
                        'ready' => 'Capture ready',
                        default => null,
                    };
                    $captureIcon = match ($captureStatus) {
                        'failed' => 'x-circle',
                        'partial' => 'exclamation-triangle',
                        'unverified' => 'question-mark-circle',
                        default => 'check',
                    };
                @endphp
                <div class="mt-0.5 flex min-w-0 items-center gap-2 whitespace-nowrap text-xs text-muted-foreground">
                    @if ($openUntil)
                        @php($localDeadline = $openUntil->timezone(config('app.timezone')))
                        <time
                            datetime="{{ $openUntil->toIso8601String() }}"
                            title="{{ $localDeadline->toDayDateTimeString() }}"
                            @class([
                                'inline-flex shrink-0 items-center gap-1 font-semibold tabular-nums',
                                'text-attention-ink' => $closingSoon,
                                'text-zinc-800' => ! $closingSoon,
                            ])
                        >
                            <flux:icon.clock variant="micro" class="size-3.5" />
                            <span>{{ $this->isOwner() ? 'Open until' : 'Guest access until' }} {{ $localDeadline->format('M j') }}</span>
                        </time>
                    @endif
                    @if ($this->isOwner())
                        @include('review.partials.status-menu', ['undoable' => true, 'quiet' => true])
                    @elseif (! $review->isOpenForFeedback())
                        <span class="inline-flex shrink-0" title="{{ $review->statusTag()['label'] }}">
                            @include('review.partials.status-icon')
                            <span class="sr-only">{{ $review->statusTag()['label'] }}</span>
                        </span>
                    @endif
                    @if ($review->pass > 1)
                        <span class="hidden shrink-0 sm:inline">Pass {{ $review->pass }}</span>
                    @endif
                    <span class="hidden min-w-0 truncate sm:inline" title="{{ $domain ? $review->page_url : $source }}">{{ $source }}</span>
                    @if ($captureState)
                        <span class="min-w-0 truncate" title="{{ $captureState }}">{{ $captureState }}</span>
                    @endif
                    @if ($captureLabel)
                        <span data-capture-status role="status" title="{{ $captureLabel }}" @class([
                            'inline-flex shrink-0',
                            'text-problem-ink' => $captureStatus === 'failed',
                            'text-attention-ink' => in_array($captureStatus, ['partial', 'unverified'], true),
                        ])>
                            <span class="sr-only">{{ $captureLabel }}</span>
                            <span wire:key="capture-status-icon-{{ $captureStatus }}" class="rm-status-icon inline-flex" aria-hidden="true">
                                <flux:icon :name="$captureIcon" variant="micro" class="size-4" />
                            </span>
                        </span>
                    @endif
                </div>
            </div>

            @include('review.partials.panel-toggles')

            @if ($mode === 'guest')
                <div class="flex shrink-0 items-center gap-3">
                    @if ($review->allowsGuestAccess())
                        <x-signal-tag>Guest</x-signal-tag>
                    @endif
                    {{-- A guest's way to a review of their own. Quiet, and no account. --}}
                    <a
                        href="{{ route('reviews.new') }}"
                        class="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-zinc-900"
                        aria-label="Start your own review"
                        onclick="if(window.fathom)fathom.trackEvent('Guest start review')"
                    >
                        <flux:icon.plus variant="micro" class="size-4 sm:hidden" />
                        <span class="hidden sm:inline">Start your own review</span>
                    </a>
                </div>
            @elseif ($this->isOwner())
                <div class="flex shrink-0 flex-nowrap items-center justify-end gap-1.5">
                    @include('review.partials.share')
                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" class="!size-8 !rounded-full !bg-chip !px-0 hover:!bg-chip-hover" aria-label="More review actions" />
                        <flux:menu>
                            <flux:menu.item icon="view-columns" href="{{ $review->boardUrl() }}">Open board</flux:menu.item>
                            @if ($review->isOpenForFeedback())
                                <flux:menu.item icon="arrow-uturn-left" x-on:click="$dispatch('rm-decide', { kind: 'changes' })">Request changes</flux:menu.item>
                                <flux:menu.item icon="pencil-square" class="hidden md:flex" x-on:click="$dispatch('modal-show', { name: 'review-decision-note' })">Add a note</flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                    @if ($review->isOpenForFeedback())
                        <flux:modal name="review-decision-note" class="w-full max-w-md">
                            <div class="space-y-3">
                                <flux:heading size="lg">Note for your agent</flux:heading>
                                <flux:textarea wire:model="decisionNote" rows="3" label="Decision note" />
                                <flux:modal.close><flux:button variant="primary">Done</flux:button></flux:modal.close>
                            </div>
                        </flux:modal>
                        <div class="hidden items-center gap-2 md:flex">
                            <flux:button size="sm" variant="primary" icon="check" x-on:click="$dispatch('rm-decide', { kind: 'approve' })">Approve</flux:button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </header>
    @endif
