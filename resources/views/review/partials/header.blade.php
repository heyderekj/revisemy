    <header class="relative z-40 shrink-0 border-b border-border bg-background">
        <div class="flex items-center gap-2 px-3 py-2 sm:gap-3 sm:px-4">
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo size="sm" />
                </a>
                @if ($this->canListReviews())
                    <a href="/reviews" class="inline-flex shrink-0 items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-zinc-900" aria-label="Your reviews">
                        <flux:icon.queue-list variant="micro" class="size-4 sm:hidden" />
                        <span class="hidden sm:inline">Your reviews</span>
                    </a>
                    <span class="hidden text-zinc-300 sm:inline" aria-hidden="true">/</span>
                @endif
                <h1 class="sr-only">Review</h1>
            </div>

            {{-- Title over one quiet line of facts: pass, page, when. --}}
            <div class="min-w-0 flex-1 xl:max-w-[28rem]">
                @if ($this->isOwner() && $review->isOpenForFeedback())
                    <div class="flex min-w-0 flex-1 items-center gap-1" wire:key="title-field">
                        @if ($editingTitle)
                            <input
                                type="text"
                                wire:model="titleDraft"
                                maxlength="200"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm font-semibold text-zinc-900 outline-none ring-0 placeholder:text-zinc-400"
                                placeholder="Review title"
                                x-data
                                x-init="$el.focus(); $el.select()"
                                x-on:keydown.enter.prevent="$wire.saveTitle()"
                                x-on:keydown.escape.prevent="$wire.cancelEditTitle()"
                                x-on:blur="$wire.blurSaveTitle()"
                                aria-label="Review title"
                            />
                        @else
                            <button
                                type="button"
                                wire:click="startEditTitle"
                                class="min-w-0 truncate text-left text-sm font-semibold text-zinc-900 transition hover:text-zinc-600"
                                title="Click to edit title"
                            >
                                {{ $review->title }}
                            </button>
                        @endif
                        <div class="flex w-14 shrink-0 items-center justify-end gap-0.5">
                            @if ($editingTitle)
                                <button
                                    type="button"
                                    wire:click="saveTitle"
                                    x-on:mousedown.prevent
                                    class="inline-flex size-6 items-center justify-center rounded-md text-zinc-900 transition hover:bg-chip"
                                    aria-label="Save title"
                                    title="Save"
                                >
                                    <flux:icon.check variant="micro" class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    wire:click="cancelEditTitle"
                                    x-on:mousedown.prevent
                                    class="inline-flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                                    aria-label="Cancel"
                                    title="Cancel"
                                >
                                    <flux:icon.x-mark variant="micro" class="size-3.5" />
                                </button>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="min-w-0 truncate text-sm font-semibold text-zinc-900" title="{{ $review->title }}">{{ $review->title }}</p>
                @endif
                @php($sourceKind = $review->sourceKind())
                <p class="flex min-w-0 items-center gap-1.5 truncate text-xs text-muted-foreground">
                    <span class="shrink-0 tabular-nums">Pass {{ $review->pass }}</span>
                    <span aria-hidden="true">·</span>
                    @if ($sourceKind === \App\Models\Review::SOURCE_URL && $review->sourceDomain())
                        <span
                            class="min-w-0 truncate"
                            title="Snapshot of {{ $review->page_url }}{{ $review->capturedAt() ? ' · '.$review->capturedAt()->timezone(config('app.timezone'))->toDayDateTimeString() : '' }}"
                        >{{ $review->sourceDomain() }}@if ($review->capturedAt())<span class="hidden lg:inline"> · captured {{ $review->capturedAt()->diffForHumans() }}</span>@endif</span>
                    @else
                        <span class="min-w-0 truncate">{{ $review->sourceKindLabel() }}</span>
                    @endif
                    @if ($this->isOwner())
                        @include('review.partials.status-menu', ['undoable' => true, 'class' => 'ms-1'])
                    @elseif ($review->effectiveStatus() === 'changes_requested')
                        <x-signal-tag tone="attention" class="ms-1 shrink-0">Changes requested</x-signal-tag>
                    @elseif ($review->effectiveStatus() === 'approved')
                        <x-signal-tag tone="done" class="ms-1 shrink-0">Approved</x-signal-tag>
                    @elseif ($review->effectiveStatus() === 'expired')
                        <x-signal-tag class="ms-1 shrink-0">Expired</x-signal-tag>
                    @endif
                </p>
            </div>

            @include('review.partials.viewport-tabs', ['class' => 'hidden md:flex'])

            <div class="hidden flex-1 xl:block"></div>

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
                    <div
                        class="relative"
                        x-data="{
                            copied: false,
                            shareUrl: {{ \Illuminate\Support\Js::from($review->shareUrl()) }},
                            pickingDate: false,
                            cursor: (() => {
                                const seed = {{ \Illuminate\Support\Js::from(optional($review->share_expires_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }};
                                const [y, m] = seed.split('-').map(Number);
                                return new Date(y, m - 1, 1);
                            })(),
                            selected: {{ \Illuminate\Support\Js::from(optional($review->share_expires_at)->format('Y-m-d')) }},
                            today: {{ \Illuminate\Support\Js::from(now()->format('Y-m-d')) }},
                            weekdays: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                            async copyLink() {
                                await navigator.clipboard.writeText(this.shareUrl);
                                this.copied = true;
                                setTimeout(() => this.copied = false, 2000);
                            },
                            monthLabel() {
                                return this.cursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                            },
                            shiftMonth(delta) {
                                this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + delta, 1);
                            },
                            days() {
                                const year = this.cursor.getFullYear();
                                const month = this.cursor.getMonth();
                                const firstDow = new Date(year, month, 1).getDay();
                                const daysInMonth = new Date(year, month + 1, 0).getDate();
                                const cells = [];
                                for (let i = 0; i < firstDow; i++) cells.push(null);
                                for (let d = 1; d <= daysInMonth; d++) {
                                    const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                                    cells.push({ day: d, iso, disabled: iso < this.today });
                                }
                                while (cells.length % 7 !== 0) cells.push(null);
                                return cells;
                            },
                            pick(iso) {
                                if (! iso) return;
                                this.selected = iso;
                                $wire.setShareExpiryDate(iso).then(() => {
                                    this.pickingDate = false;
                                });
                            }
                        }"
                        x-on:share-url-updated.window="shareUrl = $event.detail.url"
                        x-on:keydown.escape.window="pickingDate = false"
                    >
                        <flux:dropdown position="bottom" align="end">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="link"
                                class="!bg-chip hover:!bg-chip-hover max-xl:!w-8 max-xl:!gap-0 max-xl:!px-0"
                                aria-label="Share"
                            >
                                <span class="hidden xl:inline" x-show="! copied">Share</span>
                                <span class="hidden xl:inline" x-show="copied" x-cloak>Copied!</span>
                                <flux:icon.chevron-down variant="micro" class="hidden size-3.5 xl:inline" />
                            </flux:button>

                            <flux:menu class="min-w-60">
                                <flux:menu.item icon="clipboard-document" x-on:click="copyLink()">
                                    Copy guest link
                                </flux:menu.item>
                                <flux:menu.item
                                    icon="arrow-path"
                                    wire:click="regenerateShareToken"
                                    wire:confirm="Regenerate the guest link? Anyone with the old link loses access."
                                >
                                    Generate new link
                                </flux:menu.item>
                                <flux:menu.separator />
                                <div class="px-3 py-1.5 text-[11px] font-medium uppercase tracking-wide text-zinc-400">Guest link expires</div>
                                <flux:menu.item wire:click="setShareExpiry('7d')">
                                    In 7 days
                                    <span class="ms-auto text-xs font-medium text-zinc-400">Default</span>
                                </flux:menu.item>
                                <flux:menu.item wire:click="setShareExpiry('14d')">In 14 days</flux:menu.item>
                                <flux:menu.item wire:click="setShareExpiry('never')">Never</flux:menu.item>
                                <flux:menu.item icon="calendar-days" x-on:click="pickingDate = true">
                                    Custom date…
                                </flux:menu.item>
                                @if ($review->share_expires_at)
                                    <p class="px-3 py-1.5 text-[11px] leading-snug text-zinc-500">
                                        @if ($review->isShareLinkExpired())
                                            Expired {{ $review->share_expires_at->diffForHumans() }}
                                        @else
                                            Expires {{ $review->share_expires_at->timezone(config('app.timezone'))->toFormattedDateString() }}
                                            · {{ $review->share_expires_at->diffForHumans() }}
                                        @endif
                                    </p>
                                @endif
                                <flux:menu.separator />
                                <flux:menu.item
                                    icon="{{ $review->allowsComments() ? 'chat-bubble-left-right' : 'no-symbol' }}"
                                    wire:click="toggleComments"
                                >
                                    {{ $review->allowsComments() ? 'Disable comments' : 'Enable comments' }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        <div
                            x-show="pickingDate"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                            class="absolute right-0 top-[calc(100%+0.5rem)] z-[60] w-[17.5rem] overflow-hidden rounded-2xl bg-lift ring-1 ring-black/[0.07] p-3 shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]"
                            x-on:click.outside="pickingDate = false"
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800"
                                    x-on:click="shiftMonth(-1)"
                                    aria-label="Previous month"
                                >
                                    <flux:icon.chevron-left variant="micro" class="size-4" />
                                </button>
                                <p class="text-sm font-medium text-zinc-800" x-text="monthLabel()"></p>
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800"
                                    x-on:click="shiftMonth(1)"
                                    aria-label="Next month"
                                >
                                    <flux:icon.chevron-right variant="micro" class="size-4" />
                                </button>
                            </div>

                            <div class="mb-1 grid grid-cols-7 gap-0.5">
                                <template x-for="label in weekdays" :key="label">
                                    <div class="py-1 text-center text-xs font-medium uppercase tracking-wide text-zinc-400" x-text="label"></div>
                                </template>
                            </div>

                            <div class="grid grid-cols-7 gap-0.5">
                                <template x-for="(cell, index) in days()" :key="index">
                                    <div class="aspect-square">
                                        <button
                                            type="button"
                                            x-show="cell"
                                            x-bind:disabled="cell?.disabled"
                                            x-on:click="pick(cell.iso)"
                                            class="flex size-full items-center justify-center rounded-full text-sm transition disabled:cursor-not-allowed disabled:opacity-30"
                                            x-bind:class="cell && cell.iso === selected
                                                ? 'bg-key font-semibold text-accent-foreground shadow-sm'
                                                : (cell && cell.iso === today
                                                    ? 'font-semibold text-zinc-900 ring-1 ring-inset ring-key/60 hover:bg-chip'
                                                    : 'text-zinc-700 hover:bg-zinc-100')"
                                            x-text="cell?.day"
                                        ></button>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-100 pt-3">
                                <button
                                    type="button"
                                    class="text-xs font-medium text-zinc-500 transition hover:text-zinc-800"
                                    x-on:click="pickingDate = false"
                                >Cancel</button>
                                <button
                                    type="button"
                                    class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80"
                                    x-on:click="pick(today)"
                                >Today</button>
                            </div>
                            <flux:error name="shareExpiryDate" />
                        </div>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="view-columns" href="{{ $review->boardUrl() }}" class="!bg-chip hover:!bg-chip-hover max-xl:!w-8 max-xl:!gap-0 max-xl:!px-0" aria-label="Board"><span class="hidden xl:inline">Board</span></flux:button>
                    @if ($review->isOpenForFeedback())
                        <div class="hidden items-center gap-2 md:flex">
                            <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" x-on:click="$dispatch('rm-decide', { kind: 'changes' })" class="!bg-chip hover:!bg-chip-hover max-lg:!w-8 max-lg:!gap-0 max-lg:!px-0" aria-label="Changes"><span class="hidden lg:inline">Changes</span></flux:button>
                            <flux:button size="sm" variant="primary" icon="check" x-on:click="$dispatch('rm-decide', { kind: 'approve' })">Approve</flux:button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
        @include('review.partials.viewport-tabs', ['class' => 'mx-3 mb-2 flex w-fit md:hidden'])
    </header>
