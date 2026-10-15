{{-- Which shot is on the canvas: Desktop, Mobile, Tablet, or a deck's pages. --}}
@if ($review->screenshots->count() > 1)
    <div role="tablist" aria-label="Screenshots" class="{{ $class }} shrink-0 items-center gap-0.5 overflow-x-auto rounded-xl bg-chip p-0.5 [scrollbar-width:none]">
        @foreach ($review->screenshots as $index => $shotOption)
            <button
                type="button"
                role="tab"
                wire:key="tab-{{ $shotOption->id }}-{{ md5($class) }}"
                wire:click="selectScreenshot({{ $index }})"
                x-on:click="$store.rmFocus && ($store.rmFocus.finding = null, $store.rmFocus.mark = null)"
                aria-selected="{{ $activeScreenshotIndex === $index ? 'true' : 'false' }}"
                @class([
                    'h-7 shrink-0 rounded-[0.6rem] px-2.5 text-xs font-medium whitespace-nowrap transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400',
                    'bg-raised text-zinc-900 shadow-xs ring-1 ring-black/[0.06]' => $activeScreenshotIndex === $index,
                    'text-zinc-500 hover:text-zinc-900' => $activeScreenshotIndex !== $index,
                ])
            >{{ $shotOption->railLabel($index) }}</button>
        @endforeach
    </div>
@endif
