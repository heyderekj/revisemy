        <aside class="relative flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto border-t border-zinc-200 px-0.5 pt-4 pb-28 [scroll-padding-top:1rem] sm:pt-5 md:min-h-0 md:border-l md:border-t-0 md:border-zinc-200 md:overflow-y-auto md:pb-6 md:pl-5 md:pt-6 md:[scroll-padding-top:1.5rem] lg:pl-6">
            <x-cross-mark left="0" top="0" visibility="hidden md:block" />
            <x-cross-mark left="0" top="100%" visibility="hidden md:block" />
            @php($pins = $this->activeMarks)
            @php($secondOpinionFindings = $this->openSecondOpinion)
            @php($guestSuggestions = $this->openGuestSuggestions)
            @php($parent = $review->parent)
            @php($previousMarks = ($mode === 'owner' && $parent)
                ? $parent->screenshots->flatMap->annotations->sortBy('number')->values()
                : collect())
            @php($awaitingVerify = $mode === 'owner' ? $this->awaitingVerificationMarks() : collect())
            @php($passLedger = $mode === 'owner' ? $this->passLedgerEntries() : [])
            <div
                class="space-y-4 pb-4 md:pb-6"
                x-data="{
                    panels: { marks: true, previous: false, second: false, guest: false, ledger: {{ count($passLedger) > 1 ? 'true' : 'false' }} },
                    init() {
                        this.$watch(() => this.$store.rmFocus.mark, (id) => {
                            if (! id) return;
                            this.panels.marks = true;
                            this.$nextTick(() => document.getElementById('fb-mark-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                        });
                        this.$watch(() => this.$store.rmFocus.finding, (id) => {
                            if (! id) return;
                            const el = document.getElementById('fb-finding-' + id);
                            if (! el) return;
                            if (el.closest('[data-panel=guest]')) this.panels.guest = true;
                            else this.panels.second = true;
                            this.$nextTick(() => el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                        });
                    }
                }"
            >
            @if ($awaitingVerify->isNotEmpty() && $this->canManageMarks())
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 sm:px-4" data-panel="verify">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-amber-950">Awaiting your verify</p>
                            <p class="mt-0.5 text-xs leading-snug text-amber-900/80">
                                Agent resolved {{ $awaitingVerify->count() }} {{ $awaitingVerify->count() === 1 ? 'mark' : 'marks' }}. Check before/after, then verify or reopen.
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="verifyAllResolved"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500"
                        >
                            Verify all {{ $awaitingVerify->count() }}
                        </button>
                    </div>
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($awaitingVerify->take(5) as $mark)
                            <li>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded-lg bg-white/70 px-2 py-1.5 text-left text-xs transition hover:bg-white"
                                    x-on:click="$store.rmFocus.mark = {{ $mark->id }}; panels.marks = true"
                                >
                                    <span class="flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-semibold {{ $mark->markerClass() }}">M{{ $mark->number }}</span>
                                    <span class="min-w-0 flex-1 truncate text-zinc-700">{{ $mark->body }}</span>
                                    <span class="shrink-0 font-medium text-emerald-700" wire:click.stop="verifyMark({{ $mark->id }})">Verify</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($review->boardUrl())
                        <a href="{{ $review->boardUrl() }}" class="mt-2 inline-flex text-xs font-medium text-amber-900/80 underline decoration-amber-900/20 underline-offset-2 hover:text-amber-950">Open board</a>
                    @endif
                </div>
            @endif

            @if (count($passLedger) > 1)
                <div class="border border-zinc-200 bg-white" data-panel="ledger">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-3 text-left sm:px-4"
                        x-on:click="panels.ledger = ! panels.ledger"
                        x-bind:aria-expanded="panels.ledger.toString()"
                    >
                        <flux:heading size="sm" class="min-w-0 flex-1">Pass ledger</flux:heading>
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600">{{ count($passLedger) }}</span>
                        <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-zinc-400 transition" x-bind:class="panels.ledger && 'rotate-180'" />
                    </button>
                    <div class="border-t border-zinc-100 px-3 pb-3 pt-3 sm:px-4 sm:pb-4" x-show="panels.ledger" x-cloak>
                        <ol class="space-y-2">
                            @foreach ($passLedger as $entry)
                                <li @class([
                                    'rounded-lg border px-2.5 py-2',
                                    'border-rose-200 bg-rose-50/40' => $entry['is_current'],
                                    'border-zinc-100 bg-zinc-50/50' => ! $entry['is_current'],
                                ])>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-semibold text-zinc-800">Pass {{ $entry['pass'] }}</span>
                                        @if ($entry['is_current'])
                                            <span class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-medium text-rose-800">Current</span>
                                        @endif
                                        <span class="text-[10px] text-zinc-500">{{ $entry['status_label'] }}</span>
                                    </div>
                                    <p class="mt-1 text-[11px] tabular-nums text-zinc-500">
                                        {{ $entry['mark_count'] }} marks
                                        · {{ $entry['verified_count'] }} verified
                                        · {{ $entry['resolved_count'] }} awaiting
                                        @if ($entry['after_evidence_count'] > 0)
                                            · {{ $entry['after_evidence_count'] }} after
                                        @endif
                                    </p>
                                    @if ($entry['decision_note'])
                                        <p class="mt-1 text-xs leading-relaxed text-zinc-600">{{ $entry['decision_note'] }}</p>
                                    @endif
                                    @if (! $entry['is_current'])
                                        <a href="{{ $entry['review_url'] }}" class="mt-1 inline-block text-[11px] font-medium text-zinc-500 underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800">Open pass {{ $entry['pass'] }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            @endif

            <div class="border border-zinc-200 bg-white" data-panel="marks">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-3 text-left sm:px-4"
                    x-on:click="panels.marks = ! panels.marks"
                    x-bind:aria-expanded="panels.marks.toString()"
                >
                    <flux:heading size="sm" class="min-w-0 flex-1">{{ $mode === 'owner' ? 'My marks' : 'Owner marks' }}</flux:heading>
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600">{{ $pins->count() }}</span>
                    <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-zinc-400 transition" x-bind:class="panels.marks && 'rotate-180'" />
                </button>

                <div class="border-t border-zinc-100 px-3 pb-3 pt-3 sm:px-4 sm:pb-4" x-show="panels.marks">

                @if ($pins->isEmpty())
                    <p class="text-sm text-zinc-500">
                        {{ $mode === 'owner' ? 'No marks yet. Drag to outline a region, or click for a point.' : 'The owner has not marked anything yet.' }}
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($pins as $pin)
                            <li
                                id="fb-mark-{{ $pin->id }}"
                                class="rounded-xl border border-zinc-100 p-3 transition"
                                x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} ? 'border-rose-300 ring-2 ring-rose-200/70' : ''"
                            >
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
                                        <span class="text-xs text-zinc-500">{{ $pin->label() }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $pin->statusBadgeClass() }}">{{ $pin->statusLabel() }}</span>
                                        @if ($pin->source && $pin->source !== \App\Models\Annotation::SOURCE_HUMAN)
                                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-600">{{ $pin->sourceLabel() }}</span>
                                        @endif
                                    </div>
                                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                                        <button type="button" class="text-xs text-zinc-400 hover:text-rose-600" wire:click="deletePin({{ $pin->id }})" wire:confirm="Remove this mark?">Remove</button>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700">{{ $pin->body }}</p>
                                @if ($pin->suggested_copy)
                                    <div class="mt-2 rounded-lg border border-dashed border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs leading-relaxed text-zinc-700">
                                        <span class="font-medium text-zinc-500">Suggested copy:</span>
                                        <span class="font-mono">{{ $pin->suggested_copy }}</span>
                                    </div>
                                @endif
                                @if ($pin->question_answer)
                                    <div class="mt-2 rounded-lg bg-sky-50/80 px-2.5 py-1.5 text-xs leading-relaxed text-sky-950">
                                        <span class="font-medium">Answer:</span> {{ $pin->question_answer }}
                                    </div>
                                @endif
                                @if ($pin->resolution_note)
                                    <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900">
                                        <span class="font-medium">Agent:</span> {{ $pin->resolution_note }}
                                    </div>
                                @endif
                                <x-mark-before-after :mark="$pin" />
                                @if ($pin->comments->isNotEmpty())
                                    <div class="mt-2 space-y-1.5 border-t border-zinc-100 pt-2">
                                        @foreach ($pin->comments as $comment)
                                            <div wire:key="pin-comment-{{ $comment->id }}" class="rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                <div class="mb-0.5 flex flex-wrap items-baseline justify-between gap-x-2">
                                                    <span class="text-[11px] font-medium text-zinc-700">{{ $comment->author }}</span>
                                                    <time
                                                        class="text-[10px] text-zinc-400"
                                                        datetime="{{ $comment->created_at->toIso8601String() }}"
                                                        title="{{ $comment->created_at->timezone(config('app.timezone'))->toDayDateTimeString() }}"
                                                    >{{ $comment->created_at->diffForHumans() }}</time>
                                                </div>
                                                <p class="text-xs leading-relaxed text-zinc-600">{{ $comment->body }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="mt-2">
                                    @if ($review->allowsComments())
                                        @if ($activeCommentMarkId === $pin->id)
                                            <div class="space-y-2 rounded-lg border border-zinc-200 bg-zinc-50/80 p-2">
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
                                                <flux:textarea wire:model="markCommentBody" rows="2" placeholder="Add a comment…" />
                                                <flux:error name="markCommentBody" />
                                                <div class="flex gap-2">
                                                    <flux:button size="sm" variant="primary" wire:click="addMarkComment({{ $pin->id }})">Post</flux:button>
                                                    <flux:button size="sm" variant="ghost" wire:click="cancelMarkComment">Cancel</flux:button>
                                                </div>
                                            </div>
                                        @else
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-500 transition hover:text-zinc-800"
                                                wire:click="startMarkComment({{ $pin->id }})"
                                            >
                                                Comment
                                                @if ($pin->comments->isNotEmpty())
                                                    <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-zinc-100 px-1.5 text-[10px] font-medium tabular-nums text-zinc-600">{{ $pin->comments->count() }}</span>
                                                @endif
                                            </button>
                                        @endif
                                    @elseif ($pin->comments->isNotEmpty())
                                        <p class="inline-flex items-center gap-1.5 text-[11px] text-zinc-400">
                                            <span>Comments</span>
                                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-zinc-100 px-1.5 text-[10px] font-medium tabular-nums text-zinc-600">{{ $pin->comments->count() }}</span>
                                            <span>· commenting off</span>
                                        </p>
                                    @endif
                                </div>
                                @if ($mode === 'owner' && $pin->severity === \App\Models\Annotation::SEVERITY_QUESTION && $review->isOpenForFeedback())
                                    <div class="mt-2">
                                        @if ($answeringMarkId === $pin->id)
                                            <div class="space-y-2 rounded-lg border border-sky-200 bg-sky-50/60 p-2">
                                                <flux:textarea wire:model="questionAnswerDraft" rows="2" placeholder="Answer for the agent…" />
                                                <flux:error name="questionAnswerDraft" />
                                                <div class="flex gap-2">
                                                    <flux:button size="sm" variant="primary" wire:click="answerQuestion({{ $pin->id }})">Save answer</flux:button>
                                                    <flux:button size="sm" variant="ghost" wire:click="cancelAnswerQuestion">Cancel</flux:button>
                                                </div>
                                            </div>
                                        @else
                                            <button
                                                type="button"
                                                class="text-xs font-medium text-sky-700 transition hover:text-sky-900"
                                                wire:click="startAnswerQuestion({{ $pin->id }})"
                                            >{{ $pin->question_answer ? 'Edit answer' : 'Answer for agent' }}</button>
                                        @endif
                                    </div>
                                @endif
                                @if ($mode === 'owner' && $pin->severity !== \App\Models\Annotation::SEVERITY_KEEP && $this->canManageMarks())
                                    <div class="mt-2 flex items-center gap-2">
                                        @if ($pin->awaitsVerification())
                                            <button type="button" class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white transition hover:bg-emerald-500" wire:click="verifyMark({{ $pin->id }})">Verify</button>
                                        @endif
                                        @if ($pin->status !== \App\Models\Annotation::STATUS_OPEN)
                                            <button type="button" class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-200" wire:click="reopenMark({{ $pin->id }})">Reopen</button>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                </div>
            </div>

            @if ($previousMarks->isNotEmpty())
                    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm" data-panel="previous">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-3 text-left sm:px-4"
                            x-on:click="panels.previous = ! panels.previous"
                            x-bind:aria-expanded="panels.previous.toString()"
                        >
                            <flux:heading size="sm" class="min-w-0 flex-1">Previous pass marks</flux:heading>
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600">{{ $previousMarks->count() }}</span>
                            <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-zinc-400 transition" x-bind:class="panels.previous && 'rotate-180'" />
                        </button>
                        <div class="border-t border-zinc-100 px-3 pb-3 pt-3 sm:px-4 sm:pb-4" x-show="panels.previous" x-cloak>
                        <p class="mb-3 text-xs leading-snug text-zinc-500">From pass {{ $parent->pass }}. Verify what the agent fixed, or reopen anything still off.</p>
                        <ul class="space-y-3">
                            @foreach ($previousMarks as $pin)
                                <li class="rounded-xl border border-zinc-100 p-3">
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
                                        <span class="text-xs text-zinc-500">{{ $pin->label() }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $pin->statusBadgeClass() }}">{{ $pin->statusLabel() }}</span>
                                    </div>
                                    <p class="text-sm leading-relaxed text-zinc-700">{{ $pin->body }}</p>
                                    @if ($pin->resolution_note)
                                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900">
                                            <span class="font-medium">Agent:</span> {{ $pin->resolution_note }}
                                        </div>
                                    @endif
                                    <x-mark-before-after :mark="$pin" />
                                    @if ($pin->comments->isNotEmpty())
                                        <div class="mt-2 space-y-1.5 border-t border-zinc-100 pt-2">
                                            @foreach ($pin->comments as $comment)
                                                <div wire:key="prev-pin-comment-{{ $comment->id }}" class="rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                    <div class="mb-0.5 flex flex-wrap items-baseline justify-between gap-x-2">
                                                        <span class="text-[11px] font-medium text-zinc-700">{{ $comment->author }}</span>
                                                        <time
                                                            class="text-[10px] text-zinc-400"
                                                            datetime="{{ $comment->created_at->toIso8601String() }}"
                                                            title="{{ $comment->created_at->timezone(config('app.timezone'))->toDayDateTimeString() }}"
                                                        >{{ $comment->created_at->diffForHumans() }}</time>
                                                    </div>
                                                    <p class="text-xs leading-relaxed text-zinc-600">{{ $comment->body }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($pin->severity !== \App\Models\Annotation::SEVERITY_KEEP && $this->canManageMarks())
                                        <div class="mt-2 flex items-center gap-2">
                                            @if ($pin->awaitsVerification())
                                                <button type="button" class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white transition hover:bg-emerald-500" wire:click="verifyMark({{ $pin->id }})">Verify</button>
                                            @endif
                                            @if ($pin->status !== \App\Models\Annotation::STATUS_OPEN)
                                                <button type="button" class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-200" wire:click="reopenMark({{ $pin->id }})">Reopen</button>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        </div>
                    </div>
            @endif

            @if ($mode === 'owner')
            @php($taste = \App\Support\TasteLenses::forType($review->type))
            <div class="border border-sky-200/80 bg-sky-50/50" data-panel="second">
                <div class="flex items-center gap-1.5 px-3 py-3 sm:px-4">
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 items-center gap-2 text-left"
                        x-on:click="panels.second = ! panels.second"
                        x-bind:aria-expanded="panels.second.toString()"
                    >
                        <flux:heading size="sm" class="min-w-0 flex-1 truncate">Second opinion</flux:heading>
                    </button>
                    @if ($review->isOpenForFeedback())
                        <button
                            type="button"
                            wire:click="refreshSecondOpinion"
                            wire:loading.attr="disabled"
                            class="inline-flex size-6 shrink-0 items-center justify-center rounded-full text-sky-700 transition hover:bg-sky-100/80 hover:text-sky-900 disabled:opacity-50"
                            title="Refresh second opinion"
                            aria-label="Refresh second opinion"
                        >
                            <flux:icon.arrow-path variant="micro" class="size-3.5" wire:loading.class="animate-spin" wire:target="refreshSecondOpinion" />
                        </button>
                    @endif
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-1.5"
                        x-on:click="panels.second = ! panels.second"
                        x-bind:aria-expanded="panels.second.toString()"
                        aria-label="Toggle second opinion"
                    >
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-sky-800">{{ $secondOpinionFindings->count() }}</span>
                        <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-sky-700/60 transition" x-bind:class="panels.second && 'rotate-180'" />
                    </button>
                </div>

                <div class="border-t border-sky-200/60 px-3 pb-3 pt-3 sm:px-4 sm:pb-4" x-show="panels.second" x-cloak>
                <div class="relative mb-3 flex items-start justify-between gap-2">
                    <p class="min-w-0 text-xs leading-snug text-zinc-500">Hints until you accept — then they become your marks</p>
                    <x-taste-craft-chip :taste="$taste" />
                </div>
                @if ($review->isOpenForFeedback() && $secondOpinionFindings->isNotEmpty())
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="acceptOpenFindings('second')"
                            wire:confirm="Accept all open second-opinion hints on this shot as marks?"
                            class="rounded-md bg-sky-600 px-2 py-1 text-[11px] font-medium text-white transition hover:bg-sky-500"
                        >Accept all</button>
                        <button
                            type="button"
                            wire:click="dismissOpenFindings('second')"
                            wire:confirm="Dismiss all open second-opinion hints on this shot?"
                            class="rounded-md bg-zinc-100 px-2 py-1 text-[11px] font-medium text-zinc-600 transition hover:bg-zinc-200"
                        >Dismiss all</button>
                    </div>
                @endif

                @php($findings = $secondOpinionFindings)
                @php($status = $shot?->second_opinion_status ?? 'idle')
                @php($findingNumbers = $suggestionNumbers['s'])
                @php($visionEnabled = $this->visionEnabled())
                @php($sourceTabLabels = [
                    'all' => 'All',
                    'checklist' => 'Checklist',
                    'vision' => 'Vision',
                ])
                @php($sourceTabCounts = [
                    'all' => $findings->count(),
                    'checklist' => $findings->filter(fn ($f) => $f->isChecklistSource())->count(),
                    'vision' => $findings->filter(fn ($f) => $f->isVisionSource())->count(),
                ])
                @php($sourceFilteredFindings = match ($secondOpinionSourceTab) {
                    'checklist' => $findings->filter(fn ($f) => $f->isChecklistSource())->values(),
                    'vision' => $findings->filter(fn ($f) => $f->isVisionSource())->values(),
                    default => $findings,
                })
                @php($tabCounts = [
                    'all' => $sourceFilteredFindings->count(),
                    \App\Models\Finding::SEVERITY_SUGGESTION => $sourceFilteredFindings->where('severity', \App\Models\Finding::SEVERITY_SUGGESTION)->count(),
                    \App\Models\Finding::SEVERITY_A11Y => $sourceFilteredFindings->where('severity', \App\Models\Finding::SEVERITY_A11Y)->count(),
                    \App\Models\Finding::SEVERITY_POLISH => $sourceFilteredFindings->where('severity', \App\Models\Finding::SEVERITY_POLISH)->count(),
                ])
                @php($tabLabels = [
                    'all' => 'All',
                    \App\Models\Finding::SEVERITY_SUGGESTION => 'Suggestion',
                    \App\Models\Finding::SEVERITY_A11Y => 'A11y',
                    \App\Models\Finding::SEVERITY_POLISH => 'Polish',
                ])
                @php($visibleFindings = $secondOpinionTab === 'all'
                    ? $sourceFilteredFindings
                    : $sourceFilteredFindings->where('severity', $secondOpinionTab)->values())
                @php($showVisionSetup = $secondOpinionSourceTab === 'vision' && ! $visionEnabled && $sourceTabCounts['vision'] === 0)
                @php($showVisionEmpty = $secondOpinionSourceTab === 'vision' && $visionEnabled && $sourceTabCounts['vision'] === 0 && $status !== 'queued')

                @if ($status === 'queued')
                    <p class="mb-3 text-sm text-sky-700">{{ $findings->isEmpty() ? 'Generating hints…' : 'Adding vision hints…' }}</p>
                @elseif ($status === 'failed')
                    {{-- Provider error text stays in the log; a review link is not a debug console. --}}
                    <p class="mb-3 text-sm text-rose-600">Second opinion couldn’t run on this shot. Your marks are unaffected — try Refresh.</p>
                @endif

                <div class="mb-3 grid grid-cols-3 gap-1 rounded-xl border border-sky-200/80 bg-white/70 p-1">
                    @foreach ($sourceTabLabels as $sourceTabId => $sourceTabLabel)
                        <button
                            type="button"
                            wire:click="setSecondOpinionSourceTab('{{ $sourceTabId }}')"
                            @class([
                                'inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-medium transition',
                                'bg-sky-600 text-white shadow-sm' => $secondOpinionSourceTab === $sourceTabId,
                                'text-sky-900/70 hover:bg-sky-50 hover:text-sky-900' => $secondOpinionSourceTab !== $sourceTabId,
                            ])
                        >
                            <span>{{ $sourceTabLabel }}</span>
                            <span @class([
                                'rounded-full px-1.5 py-px text-[10px] tabular-nums',
                                'bg-white/20 text-white' => $secondOpinionSourceTab === $sourceTabId,
                                'bg-sky-100 text-sky-700' => $secondOpinionSourceTab !== $sourceTabId,
                            ])>{{ $sourceTabCounts[$sourceTabId] }}</span>
                        </button>
                    @endforeach
                </div>

                @if ($showVisionSetup)
                    <div class="rounded-xl border border-dashed border-sky-200 bg-white/80 px-3 py-3">
                        <p class="text-sm font-medium text-sky-950">Vision marks regions on the capture</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-sky-900/80">
                            Add <span class="font-mono text-[10px]">ANTHROPIC_API_KEY</span> or <span class="font-mono text-[10px]">OPENAI_API_KEY</span> on the server, then refresh this second opinion.
                        </p>
                        @if ($review->isOpenForFeedback())
                            <button
                                type="button"
                                wire:click="refreshSecondOpinion"
                                wire:loading.attr="disabled"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-sky-500 disabled:opacity-50"
                            >
                                <flux:icon.arrow-path variant="micro" class="size-3.5" wire:loading.class="animate-spin" wire:target="refreshSecondOpinion" />
                                Refresh second opinion
                            </button>
                        @endif
                    </div>
                @elseif ($showVisionEmpty)
                    <div class="rounded-xl border border-dashed border-sky-200 bg-white/80 px-3 py-3">
                        <p class="text-sm font-medium text-sky-950">No vision hints yet</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-sky-900/80">
                            Vision is configured. Refresh to critique this shot and draw regions on the capture.
                        </p>
                        @if ($review->isOpenForFeedback())
                            <button
                                type="button"
                                wire:click="refreshSecondOpinion"
                                wire:loading.attr="disabled"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-sky-500 disabled:opacity-50"
                            >
                                <flux:icon.arrow-path variant="micro" class="size-3.5" wire:loading.class="animate-spin" wire:target="refreshSecondOpinion" />
                                Refresh second opinion
                            </button>
                        @endif
                    </div>
                @elseif ($findings->isEmpty() && $status !== 'queued')
                    <p class="text-sm text-zinc-500">No open suggestions. Accept ones you want as marks, dismiss the rest, or refresh for a new pass.</p>
                @else
                    @if ($sourceFilteredFindings->isNotEmpty() && collect($tabCounts)->except('all')->sum() > 0)
                        <div class="mb-3 flex flex-wrap items-center gap-1">
                            @foreach ($tabLabels as $tabId => $tabLabel)
                                @if ($tabId === 'all' || $tabCounts[$tabId] > 0)
                                    <button
                                        type="button"
                                        wire:click="setSecondOpinionTab('{{ $tabId }}')"
                                        @class([
                                            'inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium whitespace-nowrap transition',
                                            'bg-sky-100 text-sky-800' => $secondOpinionTab === $tabId,
                                            'text-zinc-500 hover:bg-sky-50/80 hover:text-sky-800' => $secondOpinionTab !== $tabId,
                                        ])
                                    >
                                        <span>{{ $tabLabel }}</span>
                                        <span class="tabular-nums opacity-70">{{ $tabCounts[$tabId] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-2 flex items-center justify-between gap-2" x-show="$store.rmFocus?.finding" x-cloak>
                        <p class="text-xs text-sky-700">Focused on one hint</p>
                        <button
                            type="button"
                            class="text-xs font-medium text-sky-700 hover:text-sky-900"
                            x-on:click="$store.rmFocus.finding = null"
                        >Show all</button>
                    </div>

                    @if ($visibleFindings->isEmpty())
                        <p class="text-sm text-zinc-500">
                            @if ($secondOpinionSourceTab === 'vision')
                                No open vision hints{{ $secondOpinionTab !== 'all' ? ' in this category' : '' }}.
                            @elseif ($secondOpinionSourceTab === 'checklist')
                                No open checklist hints{{ $secondOpinionTab !== 'all' ? ' in this category' : '' }}.
                            @else
                                No open {{ strtolower($tabLabels[$secondOpinionTab] ?? $secondOpinionTab) }} hints.
                            @endif
                        </p>
                    @else
                        <ul class="space-y-3">
                            @foreach ($visibleFindings as $finding)
                                @php($findingHasRegion = $finding->hasRegion())
                                <li
                                    id="fb-finding-{{ $finding->id }}"
                                    class="cursor-pointer rounded-xl border bg-white/80 p-3 transition"
                                    x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                    x-on:click="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                    x-bind:class="$store.rmFocus?.finding === {{ $finding->id }}
                                        ? 'border-sky-400 ring-2 ring-sky-300/60'
                                        : 'border-sky-100'"
                                >
                                    <div class="mb-1 flex items-start gap-2">
                                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-sky-500 text-[10px] font-semibold text-sky-700">S{{ $findingNumbers[$finding->id] ?? '' }}</span>
                                            <span class="text-xs text-zinc-500">{{ \App\Models\Annotation::allSeverityLabels()[$finding->severity] ?? $finding->severity }}</span>
                                            @if (! $findingHasRegion)
                                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500">Text hint</span>
                                            @endif
                                            <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-medium text-sky-800">{{ $finding->sourceLabel() }}</span>
                                        </div>
                                        @if ($review->isOpenForFeedback() && $mode === 'owner')
                                            <div class="flex shrink-0 items-center gap-1" x-on:click.stop x-data="{ open: false }">
                                                <div class="relative">
                                                    <button
                                                        type="button"
                                                        x-on:click="open = ! open"
                                                        title="Accept as…"
                                                        aria-label="Accept as mark"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-sky-600 text-white transition hover:bg-sky-500"
                                                    >
                                                        <flux:icon.check variant="micro" class="size-3.5" />
                                                    </button>
                                                    <div
                                                        x-show="open"
                                                        x-cloak
                                                        x-on:click.outside="open = false"
                                                        class="absolute right-0 z-20 mt-1 w-36 overflow-hidden rounded-lg border border-zinc-200 bg-white py-1 shadow-lg"
                                                    >
                                                        <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-50" wire:click="acceptFinding({{ $finding->id }})" x-on:click="open = false">Default ({{ \App\Models\Annotation::allSeverityLabels()[$finding->pinSeverity()] ?? $finding->pinSeverity() }})</button>
                                                        @foreach (\App\Models\Annotation::severityLabels() as $sev => $sevLabel)
                                                            @if ($sev !== \App\Models\Annotation::SEVERITY_KEEP)
                                                                <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-50" wire:click="acceptFinding({{ $finding->id }}, '{{ $sev }}')" x-on:click="open = false">As {{ $sevLabel }}</button>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="dismissFinding({{ $finding->id }})"
                                                    title="Dismiss"
                                                    aria-label="Dismiss"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700"
                                                >
                                                    <flux:icon.x-mark variant="micro" class="size-3.5" />
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-sm leading-relaxed text-zinc-700">{{ $finding->body }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
                </div>
            </div>
            @endif

            <div class="border border-zinc-200 bg-zinc-50/60" data-panel="guest">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-3 text-left sm:px-4"
                    x-on:click="panels.guest = ! panels.guest"
                    x-bind:aria-expanded="panels.guest.toString()"
                >
                    <flux:heading size="sm" class="min-w-0 flex-1">Guest feedback</flux:heading>
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium tabular-nums text-zinc-800">{{ $guestSuggestions->count() }}</span>
                    <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-zinc-700/60 transition" x-bind:class="panels.guest && 'rotate-180'" />
                </button>

                <div class="border-t border-zinc-200/60 px-3 pb-3 pt-3 sm:px-4 sm:pb-4" x-show="panels.guest" x-cloak>
                <p class="mb-3 text-xs leading-snug text-zinc-500">
                    {{ $mode === 'owner' ? 'Teammate suggestions — accept to make them your marks' : 'Suggestions from you and other guests' }}
                </p>
                @if ($review->isOpenForFeedback() && $mode === 'owner' && $guestSuggestions->isNotEmpty())
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="acceptOpenFindings('guest')"
                            wire:confirm="Accept all guest suggestions on this shot as marks?"
                            class="rounded-md bg-zinc-700 px-2 py-1 text-[11px] font-medium text-white transition hover:bg-zinc-600"
                        >Accept all</button>
                        <button
                            type="button"
                            wire:click="dismissOpenFindings('guest')"
                            wire:confirm="Dismiss all guest suggestions on this shot?"
                            class="rounded-md bg-zinc-100 px-2 py-1 text-[11px] font-medium text-zinc-600 transition hover:bg-zinc-200"
                        >Dismiss all</button>
                    </div>
                @endif

                @if ($guestSuggestions->isEmpty())
                    <p class="text-sm text-zinc-500">
                        {{ $mode === 'owner' ? 'No guest suggestions yet. Use Share above to copy or regenerate the guest link.' : 'No suggestions yet. Drag or click on the screenshot to add one.' }}
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($guestSuggestions as $guestIndex => $finding)
                            <li
                                id="fb-finding-{{ $finding->id }}"
                                class="cursor-pointer rounded-xl border bg-white/80 p-3 transition"
                                x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                x-on:click="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                x-bind:class="$store.rmFocus?.finding === {{ $finding->id }}
                                    ? 'border-zinc-400 ring-2 ring-zinc-300/60'
                                    : 'border-zinc-100'"
                            >
                                <div class="mb-1 flex items-start gap-2">
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-zinc-500 text-[10px] font-semibold text-zinc-700">G{{ $suggestionNumbers['g'][$finding->id] ?? ($guestIndex + 1) }}</span>
                                        <span class="text-xs text-zinc-500">{{ \App\Models\Annotation::allSeverityLabels()[$finding->severity] ?? $finding->severity }}</span>
                                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-800">{{ $finding->sourceLabel() }}</span>
                                    </div>
                                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                                        <div class="flex shrink-0 items-center gap-1" x-on:click.stop x-data="{ open: false }">
                                            <div class="relative">
                                                <button
                                                    type="button"
                                                    x-on:click="open = ! open"
                                                    title="Accept as…"
                                                    aria-label="Accept as mark"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-zinc-600 text-white transition hover:bg-zinc-500"
                                                >
                                                    <flux:icon.check variant="micro" class="size-3.5" />
                                                </button>
                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    x-on:click.outside="open = false"
                                                    class="absolute right-0 z-20 mt-1 w-36 overflow-hidden rounded-lg border border-zinc-200 bg-white py-1 shadow-lg"
                                                >
                                                    <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-50" wire:click="acceptFinding({{ $finding->id }})" x-on:click="open = false">Default</button>
                                                    @foreach (\App\Models\Annotation::severityLabels() as $sev => $sevLabel)
                                                        @if ($sev !== \App\Models\Annotation::SEVERITY_KEEP)
                                                            <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-50" wire:click="acceptFinding({{ $finding->id }}, '{{ $sev }}')" x-on:click="open = false">As {{ $sevLabel }}</button>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="dismissFinding({{ $finding->id }})"
                                                title="Dismiss"
                                                aria-label="Dismiss"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700"
                                            >
                                                <flux:icon.x-mark variant="micro" class="size-3.5" />
                                            </button>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700">{{ $finding->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
                </div>
            </div>
            </div>

            @if ($this->showDecisionNote() || $this->showDecisionCallout() || $this->showStatusCallout())
                <div class="mt-auto space-y-3 border-t border-zinc-200 pt-4 pb-4 md:pb-6">
                    @if ($this->showDecisionNote())
                        <div class="hidden rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4 md:block">
                            <flux:heading size="sm" class="mb-3">Overall note (optional)</flux:heading>
                            <flux:textarea wire:model="decisionNote" rows="2" placeholder="Anything else before you approve or request changes?" />
                        </div>
                    @elseif ($this->showDecisionCallout())
                        <flux:callout>
                            <strong class="font-medium">Note to the agent:</strong> {{ $review->decision_note }}
                        </flux:callout>
                    @endif

                    @if ($review->effectiveStatus() === 'changes_requested')
                        <flux:callout>
                            <strong class="font-medium">What’s next:</strong>
                            The agent should apply your marks, then open a new checkup pass with fresh screenshots (linked to this review). You’ll get another link to approve.
                        </flux:callout>
                    @elseif ($review->effectiveStatus() === 'approved')
                        <flux:callout>
                            <strong class="font-medium">Loop complete for this pass.</strong>
                            Ask the agent for another checkup anytime if the UI changes again.
                        </flux:callout>
                    @endif
                </div>
            @endif
        </aside>
