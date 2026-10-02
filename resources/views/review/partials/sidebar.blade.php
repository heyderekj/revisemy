        {{-- Three panels: My marks (what the agent works from), Hints (suggestions
             until accepted), and the decision. Weight over chrome: what needs you
             sorts first, what's waiting is paler. --}}
        <aside class="relative flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto px-0.5 pt-4 pb-28 [scroll-padding-top:1rem] sm:pt-5 md:min-h-0 md:overflow-y-auto md:pb-6 md:pl-5 md:pt-6 md:[scroll-padding-top:1.5rem] lg:pl-6">
            @php($pins = $this->activeMarks->sortBy(fn ($p) => [$p->awaitsVerification() ? 0 : 1, $p->number])->values())
            @php($parent = $mode === 'owner' ? $review->parent : null)
            @php($previousMarks = $parent ? $parent->screenshots->flatMap->annotations->sortBy(fn ($p) => [$p->awaitsVerification() ? 0 : 1, $p->number])->values() : collect())
            @php($awaitingCount = $mode === 'owner' && $this->canManageMarks() ? $this->awaitingVerificationMarks()->count() : 0)
            @php($earlierPasses = $mode === 'owner' ? collect($this->passLedgerEntries())->reject(fn ($e) => $e['is_current'])->values() : collect())
            @php($secondOpinionCount = $mode === 'owner' ? $this->openSecondOpinion->count() : 0)
            @php($guestCount = $this->openGuestSuggestions->count())
            @php($hints = $this->visibleHints())
            @php($status = $shot?->second_opinion_status ?? 'idle')
            <div
                class="space-y-4 pb-4 md:pb-6"
                x-data="{
                    panels: { marks: true, previous: false, hints: {{ $secondOpinionCount + $guestCount > 0 ? 'true' : 'false' }} },
                    init() {
                        this.$watch(() => this.$store.rmFocus.mark, (id) => {
                            if (! id) return;
                            this.panels.marks = true;
                            this.$nextTick(() => document.getElementById('fb-mark-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                        });
                        this.$watch(() => this.$store.rmFocus.finding, (id) => {
                            if (! id) return;
                            this.panels.hints = true;
                            this.$nextTick(() => document.getElementById('fb-finding-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                        });
                    }
                }"
            >
                {{-- My marks --}}
                <section class="rounded-2xl bg-card" data-panel="marks">
                    <div class="flex items-center gap-2 px-3 py-3 sm:px-4">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" x-on:click="panels.marks = ! panels.marks" x-bind:aria-expanded="panels.marks.toString()">
                            <flux:heading size="sm">{{ $mode === 'owner' ? 'My marks' : 'Owner marks' }}</flux:heading>
                            <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-600">{{ $pins->count() }}</span>
                        </button>
                        @if ($awaitingCount > 0)
                            <button type="button" wire:click="verifyAllResolved" class="inline-flex h-7 shrink-0 items-center gap-1 rounded-full bg-done-soft px-2.5 text-xs font-medium text-done-ink transition-colors hover:bg-emerald-200">
                                <flux:icon.check variant="micro" class="size-3.5" /> Verify all {{ $awaitingCount }}
                            </button>
                        @endif
                        <button type="button" class="shrink-0" x-on:click="panels.marks = ! panels.marks" aria-label="Show or hide marks">
                            <flux:icon.chevron-down variant="micro" class="size-4 text-zinc-400 transition" x-bind:class="panels.marks && 'rotate-180'" />
                        </button>
                    </div>

                    <div class="px-3 pb-3 sm:px-4 sm:pb-4" x-show="panels.marks">
                        @if ($awaitingCount > 0)
                            <p class="mb-3 text-xs text-muted-foreground">Your agent fixed {{ $awaitingCount }} {{ $awaitingCount === 1 ? 'mark' : 'marks' }}. Check each one, then verify or reopen.</p>
                        @endif

                        @if ($pins->isEmpty())
                            <p class="text-sm text-muted-foreground">
                                {{ $mode === 'owner' ? 'No marks yet. Drag to outline a region, or click for a point.' : 'Nothing marked yet.' }}
                            </p>
                        @else
                            <ul class="space-y-2.5">
                                @foreach ($pins as $pin)
                                    @include('review.partials.mark-card', ['pin' => $pin])
                                @endforeach
                            </ul>
                        @endif

                        {{-- The pass before, paler: waiting on you, not new. --}}
                        @if ($previousMarks->isNotEmpty())
                            <div class="mt-4">
                                <button type="button" class="flex w-full items-center gap-2 text-left text-sm text-zinc-600 hover:text-zinc-900" x-on:click="panels.previous = ! panels.previous" x-bind:aria-expanded="panels.previous.toString()" data-panel="previous">
                                    <span class="min-w-0 flex-1">From pass {{ $parent->pass }}</span>
                                    <span class="tabular-nums text-zinc-400">{{ $previousMarks->count() }}</span>
                                    <flux:icon.chevron-down variant="micro" class="size-4 text-zinc-400 transition" x-bind:class="panels.previous && 'rotate-180'" />
                                </button>
                                <ul class="mt-2.5 space-y-2.5" x-show="panels.previous" x-cloak>
                                    @foreach ($previousMarks as $pin)
                                        @include('review.partials.mark-card', ['pin' => $pin, 'previous' => true])
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($earlierPasses->isNotEmpty())
                            <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span>Earlier passes</span>
                                @foreach ($earlierPasses as $entry)
                                    <a href="{{ $entry['review_url'] }}" class="link text-xs" title="{{ $entry['status_label'] }}">Pass {{ $entry['pass'] }}</a>
                                @endforeach
                            </p>
                        @endif
                    </div>
                </section>

                {{-- Hints: second opinion (owner) and guest suggestions, one list. --}}
                <section class="rounded-2xl bg-card" data-panel="hints">
                    <div class="flex items-center gap-2 px-3 py-3 sm:px-4">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" x-on:click="panels.hints = ! panels.hints" x-bind:aria-expanded="panels.hints.toString()">
                            <flux:heading size="sm">{{ $mode === 'owner' ? 'Hints' : 'Suggestions' }}</flux:heading>
                            <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-600">{{ $secondOpinionCount + $guestCount }}</span>
                        </button>
                        @if ($mode === 'owner' && $review->isOpenForFeedback())
                            <button
                                type="button"
                                wire:click="refreshSecondOpinion"
                                wire:loading.attr="disabled"
                                class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-zinc-500 transition-colors hover:bg-chip hover:text-zinc-900 disabled:opacity-50"
                                title="Refresh the second opinion"
                                aria-label="Refresh the second opinion"
                            >
                                <flux:icon.arrow-path variant="micro" class="size-4" wire:loading.class="animate-spin" wire:target="refreshSecondOpinion" />
                            </button>
                            @if ($secondOpinionCount + $guestCount > 0)
                                <div class="relative shrink-0" x-data="{ open: false }">
                                    <button type="button" class="inline-flex size-7 items-center justify-center rounded-full text-zinc-500 transition-colors hover:bg-chip hover:text-zinc-900" x-on:click="open = ! open" aria-label="All hints">
                                        <flux:icon.ellipsis-horizontal variant="micro" class="size-4" />
                                    </button>
                                    <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl bg-lift py-1 shadow-lg ring-1 ring-black/[0.07]">
                                        <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-chip" wire:click="acceptOpenFindings('all')" x-on:click="open = false">Accept all as marks</button>
                                        <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-chip" wire:click="dismissOpenFindings('all')" x-on:click="open = false">Dismiss all</button>
                                    </div>
                                </div>
                            @endif
                        @endif
                        <button type="button" class="shrink-0" x-on:click="panels.hints = ! panels.hints" aria-label="Show or hide hints">
                            <flux:icon.chevron-down variant="micro" class="size-4 text-zinc-400 transition" x-bind:class="panels.hints && 'rotate-180'" />
                        </button>
                    </div>

                    <div class="px-3 pb-3 sm:px-4 sm:pb-4" x-show="panels.hints" x-cloak>
                        <div class="mb-3 flex items-start justify-between gap-2">
                            <p class="min-w-0 text-xs text-muted-foreground">
                                {{ $mode === 'owner' ? 'Suggestions until you accept them.' : 'Suggestions from you and other guests. The owner decides what becomes a mark.' }}
                            </p>
                            @if ($mode === 'owner')
                                <x-taste-craft-chip :taste="\App\Support\TasteLenses::forType($review->type)" />
                            @endif
                        </div>

                        @if ($mode === 'owner' && $status === 'queued')
                            <p class="mb-3 text-sm text-sky-700">{{ $secondOpinionCount === 0 ? 'Getting hints…' : 'Adding vision hints…' }}</p>
                        @elseif ($mode === 'owner' && $status === 'failed')
                            {{-- Provider error text stays in the log; a review link is not a debug console. --}}
                            <p class="mb-3 text-sm text-problem-ink">The second opinion couldn’t run on this shot. Your marks are fine — try Refresh.</p>
                        @endif

                        @php($kinds = collect([
                            \App\Models\Finding::SEVERITY_SUGGESTION => 'Suggestion',
                            \App\Models\Finding::SEVERITY_A11Y => 'A11y',
                            \App\Models\Finding::SEVERITY_POLISH => 'Polish',
                        ])->filter(fn ($label, $kind) => $mode === 'owner' && $this->openSecondOpinion->contains('severity', $kind)))
                        @if ($kinds->count() + ($guestCount > 0 ? 1 : 0) > 1)
                            <div class="mb-3 flex flex-wrap items-center gap-1">
                                @foreach (['all' => 'All'] + $kinds->all() + ($guestCount > 0 ? ['guest' => 'Guests'] : []) as $kind => $label)
                                    <button
                                        type="button"
                                        wire:click="setHintFilter('{{ $kind }}')"
                                        @class([
                                            'inline-flex h-7 items-center rounded-full px-2.5 text-xs font-medium transition-colors',
                                            'bg-raised text-zinc-900 shadow-xs ring-1 ring-black/[0.06]' => $hintFilter === $kind,
                                            'text-zinc-500 hover:bg-chip hover:text-zinc-900' => $hintFilter !== $kind,
                                        ])
                                    >{{ $label }}</button>
                                @endforeach
                            </div>
                        @endif

                        <div class="mb-2 flex items-center justify-between gap-2" x-show="$store.rmFocus?.finding" x-cloak>
                            <p class="text-xs text-muted-foreground">Showing one hint</p>
                            <button type="button" class="link text-xs" x-on:click="$store.rmFocus.finding = null">Show all</button>
                        </div>

                        @if ($hints->isEmpty())
                            <p class="text-sm text-muted-foreground">
                                @if ($mode === 'owner')
                                    No hints open. Share a guest link for another set of eyes.
                                @else
                                    Nothing yet. Drag or click on the screenshot to suggest something.
                                @endif
                            </p>
                        @else
                            <ul class="space-y-2.5">
                                @foreach ($hints as $finding)
                                    @include('review.partials.hint', ['finding' => $finding])
                                @endforeach
                            </ul>
                        @endif

                        @if ($mode === 'owner' && ! $this->visionEnabled())
                            <p class="mt-3 text-xs text-muted-foreground">Vision hints that mark regions need an <span class="font-mono">ANTHROPIC_API_KEY</span> or <span class="font-mono">OPENAI_API_KEY</span> on the server.</p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- The decision. --}}
            @if ($this->showDecisionNote() || $this->showDecisionCallout() || $this->showStatusCallout())
                <div class="mt-auto space-y-3 pb-4 md:pb-6">
                    @if ($this->showDecisionNote())
                        <div class="hidden rounded-2xl bg-card p-3 sm:p-4 md:block">
                            <flux:heading size="sm" class="mb-3">Note for your agent <span class="font-normal text-muted-foreground">(optional)</span></flux:heading>
                            <flux:textarea wire:model="decisionNote" rows="2" placeholder="Anything else before you decide?" />
                        </div>
                    @elseif ($this->showDecisionCallout())
                        <div class="rounded-2xl bg-card p-3 text-sm text-zinc-700 sm:p-4">
                            <span class="font-medium text-zinc-900">Your note:</span> {{ $review->decision_note }}
                        </div>
                    @endif

                    @if ($this->showStatusCallout())
                        <div class="rounded-2xl bg-well p-3 text-sm text-zinc-700 sm:p-4">
                            @if ($review->effectiveStatus() === 'changes_requested')
                                Your agent fixes the marks and sends the next pass. You’ll get a new link.
                            @else
                                Done for this pass. Ask your agent for another checkup when it changes again.
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </aside>
