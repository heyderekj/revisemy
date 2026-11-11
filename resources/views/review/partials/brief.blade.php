@if ($hasBrief)
    <div
        class="relative min-w-0 justify-self-start"
        data-brief
        x-data="{
            open: false,
            more: false,
            clamped: false,
            measure() {
                const text = this.$refs.text;
                this.clamped = !! text && text.scrollHeight > text.clientHeight + 1;
            },
        }"
        x-init="$nextTick(() => measure())"
        x-on:resize.window.debounce.100ms="measure()"
    >
        <button
            type="button"
            data-brief-fold
            class="inline-flex h-9 items-center gap-1.5 rounded-full bg-raised px-3 text-sm transition-colors hover:bg-chip md:h-8"
            x-ref="trigger"
            x-on:click="open = ! open; if (open) $nextTick(() => measure())"
            x-bind:aria-expanded="open.toString()"
            aria-controls="review-brief"
        >
            <flux:icon.document-text variant="micro" class="size-4 shrink-0 text-zinc-500" />
            <span class="shrink-0 font-medium text-zinc-900">Brief</span>
        </button>
        <div id="review-brief" x-show="open" x-cloak
            x-on:keydown.escape="if (! $wire.editingContext) { $event.stopPropagation(); open = false; $refs.trigger.focus(); }"
            class="absolute left-0 top-full mt-2 w-[min(20rem,calc(100vw-2.5rem))] rounded-2xl bg-lift p-3 shadow-lg ring-1 ring-black/[0.07]">
            <div class="mb-1.5 flex items-center gap-3">
                <h2 class="min-w-0 flex-1 text-base font-semibold tracking-tight text-zinc-900">What to look at</h2>
                @if ($review->context && ! $editingContext)
                    <button
                        type="button"
                        class="text-xs font-medium text-muted-foreground transition-colors hover:text-zinc-900"
                        x-show="clamped || more"
                        x-cloak
                        x-on:click="more = ! more; $nextTick(() => measure())"
                        x-text="more ? 'Show less' : 'Show all'"
                    ></button>
                @endif
                <button type="button" class="text-xs font-medium text-muted-foreground transition-colors hover:text-zinc-900" x-on:click="open = false; $refs.trigger.focus()">Close</button>
            </div>

            <div>
                @if ($editingContext && $this->isOwner() && $review->isOpenForFeedback())
                    <textarea
                        wire:key="context-editor"
                        wire:model="contextDraft"
                        rows="5"
                        maxlength="5000"
                        placeholder="What should they look at on this pass?"
                        class="block w-full resize-y rounded-lg border-0 bg-well px-2.5 py-2 text-base leading-relaxed sm:text-sm text-pretty text-zinc-800 outline-none ring-1 ring-black/[0.06] placeholder:text-zinc-400 focus:ring-2 focus:ring-key/50"
                        x-data
                        x-init="$el.focus(); $el.setSelectionRange($el.value.length, $el.value.length)"
                        x-on:keydown.meta.enter.prevent="$wire.saveContext()"
                        x-on:keydown.ctrl.enter.prevent="$wire.saveContext()"
                        x-on:keydown.escape.prevent="$wire.cancelEditContext()"
                        x-on:blur="$wire.blurSaveContext()"
                    ></textarea>
                    <div class="mt-2 flex items-center justify-end gap-1.5">
                        <button
                            type="button"
                            wire:click="cancelEditContext"
                            x-on:mousedown.prevent
                            class="inline-flex h-7 items-center gap-1 rounded-full px-2.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-chip hover:text-zinc-900"
                            title="Cancel · Esc"
                        >
                            <flux:icon.x-mark variant="micro" class="size-3.5" /> Cancel
                        </button>
                        <button
                            type="button"
                            wire:click="saveContext"
                            x-on:mousedown.prevent
                            class="inline-flex h-7 items-center gap-1 rounded-full bg-foreground px-2.5 text-xs font-medium text-background transition-opacity hover:opacity-90"
                            title="Save · ⌘Enter"
                        >
                            <flux:icon.check variant="micro" class="size-3.5" /> Save
                        </button>
                    </div>
                @elseif ($this->isOwner() && $review->isOpenForFeedback())
                    <button
                        type="button"
                        wire:click="startEditContext"
                        class="w-full text-left transition hover:text-zinc-800"
                        title="Click to edit"
                    >
                        @if ($review->context)
                            <p
                                x-ref="text"
                                x-bind:class="more ? 'max-h-[50svh] overflow-y-auto' : 'line-clamp-4'"
                                class="text-sm leading-relaxed text-pretty text-zinc-700"
                            >{{ $review->context }}</p>
                        @else
                            <p class="text-sm text-zinc-400">
                                Add what to look at on this pass…
                            </p>
                        @endif
                    </button>
                @elseif ($review->context)
                    <p
                        x-ref="text"
                        x-on:click="more = ! more"
                        x-bind:class="more ? 'max-h-[50svh] overflow-y-auto' : 'line-clamp-4'"
                        class="cursor-pointer text-sm leading-relaxed text-pretty text-zinc-700"
                    >{{ $review->context }}</p>
                @endif

            </div>
        </div>
    </div>
@endif
