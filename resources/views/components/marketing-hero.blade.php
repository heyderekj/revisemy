{{-- The first section of a marketing page: what the page is about. --}}
@props([
    'eyebrow' => null,
    'headline',
    'subheadline' => null,
    'icon' => null,
    'markIcon' => null,
])

<x-home-section first>
    {{-- Desktop: room for the shell's sticky Connect button. --}}
    <div class="hidden h-8 lg:block" aria-hidden="true"></div>

    <div class="rm-fade-up lg:mt-6">
        @if ($eyebrow || $icon || $markIcon)
            <div class="flex items-center gap-3">
                @if ($markIcon)
                    <x-mark-type-icon :type="$markIcon" />
                @elseif ($icon)
                    <x-use-case-icon :name="$icon" size="lg" />
                @endif
                @if ($eyebrow)
                    <p class="text-sm font-medium text-muted-foreground">{{ $eyebrow }}</p>
                @endif
            </div>
        @endif
        <h1 class="mt-5 max-w-xl text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">{{ $headline }}</h1>
        @if ($subheadline)
            <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">{{ $subheadline }}</p>
        @endif
        {{-- Phone: the page's own Connect. The top bar takes over once it scrolls away.
             Any `actions` (a secondary link) sit beside it, or alone on desktop. --}}
        @if (isset($actions) && $actions->hasActualContent())
            <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-3">
                <x-try-token-cta data-rm-hero-cta fathom-event="Connect hero" class="lg:hidden" />
                {{ $actions }}
            </div>
        @else
            <x-try-token-cta data-rm-hero-cta fathom-event="Connect hero" class="mt-6 lg:hidden" />
        @endif
        {{ $slot }}
    </div>
</x-home-section>
