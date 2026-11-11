@props(['trackId', 'label', 'itemName', 'edgeFade' => false])

<div
    {{ $attributes }}
    x-data="{
        atStart: true,
        atEnd: false,
        update() {
            const track = this.$refs.track;
            this.atStart = track.scrollLeft <= 1;
            this.atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
        },
        move(direction) {
            const [first, second] = this.$refs.track.children;
            this.$refs.track.scrollBy({ left: direction * (second.offsetLeft - first.offsetLeft) });
        },
    }"
    x-init="$nextTick(() => update())"
    x-on:resize.window.debounce="update()"
>
    <div class="flex items-center justify-between gap-3">
        {{ $heading }}
        <div class="flex shrink-0 gap-1" x-show="! (atStart && atEnd)" x-cloak>
            <button
                type="button"
                aria-label="Previous {{ $itemName }}"
                aria-controls="{{ $trackId }}"
                x-bind:aria-disabled="atStart.toString()"
                x-on:click="move(-1)"
                class="inline-flex size-9 items-center justify-center rounded-full bg-chip text-zinc-700 hover:bg-chip-hover aria-disabled:opacity-40"
            >
                <flux:icon.chevron-left variant="micro" class="size-4" />
            </button>
            <button
                type="button"
                aria-label="Next {{ $itemName }}"
                aria-controls="{{ $trackId }}"
                x-bind:aria-disabled="atEnd.toString()"
                x-on:click="move(1)"
                class="inline-flex size-9 items-center justify-center rounded-full bg-chip text-zinc-700 hover:bg-chip-hover aria-disabled:opacity-40"
            >
                <flux:icon.chevron-right variant="micro" class="size-4" />
            </button>
        </div>
    </div>
    <div
        id="{{ $trackId }}"
        role="region"
        aria-label="{{ $label }}"
        tabindex="0"
        x-ref="track"
        x-on:scroll.passive="update()"
        x-on:keydown.left.self.prevent="move(-1)"
        x-on:keydown.right.self.prevent="move(1)"
        @if ($edgeFade)
            x-bind:style="{ '--rm-carousel-start': atStart ? '0px' : '24px', '--rm-carousel-end': atEnd ? '0px' : '40px' }"
        @endif
        @class([
            'relative -mr-[var(--rm-pad)] mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pr-[var(--rm-pad)] motion-safe:scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
            'lg:[mask-image:linear-gradient(90deg,transparent,black_var(--rm-carousel-start),black_calc(100%_-_var(--rm-carousel-end)),transparent)]' => $edgeFade,
        ])
    >
        {{ $slot }}
    </div>
</div>
