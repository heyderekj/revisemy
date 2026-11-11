@php($markCount = $this->activeMarks->count())
@php($hintCount = ($mode === 'owner' ? $this->openSecondOpinion->count() : 0) + $this->openGuestSuggestions->count())
<button
    type="button"
    class="hidden h-8 shrink-0 items-center gap-1.5 rounded-full px-3 text-sm font-medium transition-colors md:inline-flex"
    x-bind:class="$store.rmPanel?.open ? 'bg-foreground text-background' : 'bg-chip text-zinc-900 hover:bg-chip-hover'"
    x-on:click="$store.rmPanel.toggle()"
    x-bind:aria-expanded="(!! $store.rmPanel?.open).toString()"
    aria-controls="rm-drawer"
    aria-label="Feedback"
>
    <flux:icon.chat-bubble-left-right variant="micro" class="size-4" />
    <span class="hidden lg:inline">Feedback</span>
    <span class="tabular-nums">{{ $markCount + $hintCount }}</span>
</button>
