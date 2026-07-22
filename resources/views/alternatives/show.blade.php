<x-layouts.app
    :title="$page['title']"
    :description="$page['description']"
    :keywords="$page['keywords']"
    schema="page"
>
    <x-page-frame
        x-data="{
            pastHero: false,
            atCta: false,
            initStickyCta() {
                const hero = document.getElementById('rm-use-case-hero-cta');
                const cta = document.getElementById('rm-use-case-footer-cta');
                if (hero) {
                    new IntersectionObserver(
                        ([e]) => { this.pastHero = ! e.isIntersecting }
                    ).observe(hero);
                }
                if (cta) {
                    new IntersectionObserver(
                        ([e]) => { this.atCta = e.isIntersecting },
                        { rootMargin: '80px 0px 0px 0px' }
                    ).observe(cta);
                }
            }
        }"
        x-init="initStickyCta()"
    >
        <div class="relative">
            @include('use-cases.partials.sticky-cta', ['fathomEvent' => 'Try token alternatives sticky'])

            <x-home-section first>
                @include('use-cases.partials.header')
                @include('alternatives.partials.hero')
            </x-home-section>

            @include('alternatives.partials.intro')
            @include('alternatives.partials.why-look')
            @include('alternatives.partials.what-to-look-for')
            @include('alternatives.partials.recommended')
            @include('alternatives.partials.keep-theirs')
            @include('alternatives.partials.verdict')

            @if (! empty($page['faq']))
                @include('use-cases.partials.faq')
            @endif

            @include('alternatives.partials.related')
            @include('use-cases.partials.cta')
        </div>

        <div
            class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] sm:hidden"
            x-show="pastHero && ! atCta"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-3 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-3 opacity-0"
        >
            <x-try-token-cta
                fathom-event="Try token alternatives mobile"
                class="w-full justify-center"
            />
        </div>
    </x-page-frame>
</x-layouts.app>
