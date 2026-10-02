{{-- Every secondary marketing page (guides, /for, alternatives): the page
     column, a Connect button that follows you down on desktop, and one that
     docks to the bottom on a phone once the header's has scrolled away. --}}
@props([
    'title',
    'description',
    'keywords' => [],
    'fathom' => 'Connect',
    'wide' => false,
])

<x-layouts.app :title="$title" :description="$description" :keywords="$keywords" schema="page">
    <x-page-frame
        :wide="$wide"
        x-data="{
            pastHero: false,
            atCta: false,
            init() {
                const hero = document.getElementById('rm-use-case-hero-cta');
                const cta = document.getElementById('rm-use-case-footer-cta');
                if (hero) new IntersectionObserver(([e]) => { this.pastHero = ! e.isIntersecting }).observe(hero);
                if (cta) new IntersectionObserver(([e]) => { this.atCta = e.isIntersecting }, { rootMargin: '80px 0px 0px 0px' }).observe(cta);
            },
        }"
    >
        <div class="relative">
            @include('use-cases.partials.sticky-cta', ['fathomEvent' => $fathom.' sticky'])
            {{ $slot }}
            @include('use-cases.partials.cta')
        </div>

        <div
            class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] sm:hidden"
            x-show="pastHero && ! atCta"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-3 opacity-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="translate-y-3 opacity-0"
        >
            <x-try-token-cta :fathom-event="$fathom.' mobile'" class="w-full justify-center" />
        </div>
    </x-page-frame>
</x-layouts.app>
