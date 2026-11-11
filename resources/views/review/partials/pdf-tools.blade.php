<div class="flex shrink-0 flex-wrap items-center gap-2 px-3 py-2 md:px-6" x-show="!comparing">
    <form class="flex items-center gap-1 rounded-full bg-raised px-1 text-xs" x-on:submit.prevent="goToPdfPage(pdfJump)">
        <button type="button" aria-label="Previous page" class="grid size-8 place-items-center rounded-full hover:bg-chip disabled:opacity-30"
            @disabled($activeScreenshotIndex === 0) x-on:click="goToPdfPage({{ $activeScreenshotIndex }})"><flux:icon.chevron-left variant="micro" /></button>
        <input type="number" min="1" max="{{ $review->screenshots->count() }}" aria-label="PDF page number"
            class="w-12 rounded-md bg-transparent text-center" x-model="pdfJump">
        <button type="submit" class="rounded-md px-1 py-2 text-muted-foreground" title="Jump to page">of {{ $review->screenshots->count() }}</button>
        <button type="button" aria-label="Next page" class="grid size-8 place-items-center rounded-full hover:bg-chip disabled:opacity-30"
            @disabled($activeScreenshotIndex === $review->screenshots->count() - 1) x-on:click="goToPdfPage({{ $activeScreenshotIndex + 2 }})"><flux:icon.chevron-right variant="micro" /></button>
    </form>
    <div class="relative min-w-36 max-w-64 flex-1" x-on:keydown.escape.stop="pdfSearchOpen = false" x-on:click.outside="pdfSearchOpen = false">
        <form class="flex items-center gap-1 rounded-full bg-raised px-2" x-on:submit.prevent="findPdfText()">
            <input type="search" aria-label="Find in PDF" placeholder="Find in PDF" maxlength="200" x-model="pdfQuery"
                x-on:focus="if (pdfResults.length) pdfSearchOpen = true"
                class="min-w-0 flex-1 bg-transparent px-1 py-2 text-xs">
            <button type="submit" class="grid size-7 place-items-center rounded-full hover:bg-chip" aria-label="Search PDF"><flux:icon.magnifying-glass variant="micro" /></button>
        </form>
        <div x-show="pdfSearchOpen" x-cloak class="absolute top-full left-0 z-40 mt-1 max-h-64 w-64 max-w-[75vw] overflow-auto rounded-xl bg-raised p-1 shadow-lg ring-1 ring-black/10">
            <p class="px-2 py-1.5 text-xs text-muted-foreground" role="status" x-show="pdfSearchStatus" x-text="pdfSearchStatus"></p>
            <template x-for="match in pdfResults" :key="match.page">
                <button type="button" class="block w-full rounded-lg px-2 py-1.5 text-left text-xs hover:bg-chip"
                    x-on:click="goToPdfPage(match.page)">
                    <span class="block font-semibold" x-text="'Page ' + match.page"></span>
                    <span class="line-clamp-2 text-muted-foreground" x-text="match.excerpt"></span>
                </button>
            </template>
        </div>
    </div>
    @if ($review->isOpenForFeedback())
        <button type="button" x-show="pdfReady" x-cloak class="rounded-full bg-raised px-3 py-2 text-xs font-medium hover:bg-chip"
            x-on:click="pdfSelectText = !pdfSelectText; pdfSelection = null; marking = false"
            x-text="pdfSelectText ? 'Mark an area' : 'Select text'"></button>
    @endif
    <flux:dropdown position="bottom" align="end">
        <button type="button" aria-label="Download PDF" class="grid size-8 place-items-center rounded-full bg-raised hover:bg-chip"><flux:icon.arrow-down-tray variant="micro" /></button>
        <flux:menu>
            <flux:menu.item href="{{ route('reviews.pdf.original', $token) }}">Original PDF</flux:menu.item>
            @if ($this->isOwner())
                <flux:menu.item href="{{ route('reviews.pdf.annotated', $token) }}">PDF with marks</flux:menu.item>
            @endif
        </flux:menu>
    </flux:dropdown>
    @if ($review->isOpenForFeedback())
        <div x-show="pdfSelection && pdfSelectText" x-cloak class="flex w-full flex-wrap items-center gap-1 text-xs" aria-label="Mark selected text">
            <button type="button" class="rounded-full bg-raised px-3 py-2 font-medium hover:bg-chip" x-on:mousedown.prevent x-on:click="markPdfSelection('comment')">Comment</button>
            <button type="button" class="rounded-full bg-raised px-3 py-2 font-medium hover:bg-chip" x-on:mousedown.prevent x-on:click="markPdfSelection('highlight')">Highlight</button>
            <button type="button" class="rounded-full bg-raised px-3 py-2 font-medium hover:bg-chip" x-on:mousedown.prevent x-on:click="markPdfSelection('strikeout')">Strike out</button>
            @if ($this->isOwner())
                <button type="button" class="rounded-full bg-raised px-3 py-2 font-medium hover:bg-chip" x-on:mousedown.prevent x-on:click="markPdfSelection('replace')">Suggest replacement</button>
            @endif
        </div>
    @endif
</div>
