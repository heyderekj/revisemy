{{-- What sits over the shot: the brief, and the marks and hints drawer. --}}
@php($markCount = $this->activeMarks->count())
@php($hintCount = ($mode === 'owner' ? $this->openSecondOpinion->count() : 0) + $this->openGuestSuggestions->count())
<div class="flex shrink-0 items-center gap-1.5">
    @if ($review->context || ($this->isOwner() && $review->isOpenForFeedback()))
        <button
            type="button"
            class="h-8 items-center rounded-full px-3 text-sm font-medium transition-colors md:inline-flex"
            {{-- On a phone it shows only once the brief is hidden, as the way back. --}}
            x-bind:class="[($store.rmPanel?.brief ?? true) ? 'hidden bg-chip text-zinc-900 hover:bg-chip-hover' : 'inline-flex text-zinc-500 hover:bg-chip hover:text-zinc-900']"
            x-on:click="$store.rmPanel.brief = ! $store.rmPanel.brief"
            x-bind:aria-pressed="($store.rmPanel?.brief ?? true).toString()"
            title="What to look at"
        >Brief</button>
    @endif
    <button
        type="button"
        class="inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-sm font-medium transition-colors"
        x-bind:class="$store.rmPanel?.open ? 'bg-foreground text-background' : 'bg-chip text-zinc-900 hover:bg-chip-hover'"
        x-on:click="$store.rmPanel.toggle()"
        x-bind:aria-expanded="(!! $store.rmPanel?.open).toString()"
        aria-controls="rm-drawer"
    >
        <flux:icon.chat-bubble-left-right variant="micro" class="size-4 xl:hidden" />
        <span class="tabular-nums xl:hidden" aria-hidden="true">{{ $markCount + $hintCount }}</span>
        <span class="sr-only xl:not-sr-only">Marks {{ $markCount }} · {{ $mode === 'owner' ? 'Hints' : 'Suggestions' }} {{ $hintCount }}</span>
    </button>
</div>
