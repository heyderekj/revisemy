@php
    $hasHosts = ! empty($page['hosts']);
    $hasFeatures = ! empty($page['features']);
    $hasChecklist = ! empty($page['checklist']);
    $hasSources = ! empty($page['sources']);
    $hasFaq = ! empty($page['faq']);
    $tasteSources = $hasSources ? \App\Support\TasteLenses::allTypes() : [];
    $tasteDisclaimer = \App\Support\TasteLenses::disclaimer();
@endphp

<x-layouts.app
    :title="$page['title']"
    :description="$page['description']"
    :keywords="$page['keywords']"
    schema="page"
>
    <div
        class="rm-wash relative min-h-screen"
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
        <div class="rm-grid pointer-events-none absolute inset-0"></div>

        <div class="relative z-10 mx-auto max-w-[720px] px-5 pb-20 pt-8 sm:px-8 sm:pb-24 sm:pt-10">
            <div class="relative">
                <div class="pointer-events-none sticky top-8 z-30 hidden h-0 sm:block">
                    <div
                        class="flex justify-end"
                        x-show="! atCta"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    >
                        <div class="pointer-events-auto">
                            <x-try-token-cta fathom-event="Try token guide sticky" />
                        </div>
                    </div>
                </div>

                @include('use-cases.partials.header')

                @include('guides.partials.hero')

                @include('guides.partials.problem-loop')

                @if ($hasHosts)
                    @include('guides.partials.hosts')
                @endif

                @if ($hasFeatures)
                    @include('use-cases.partials.features')
                @endif

                @if ($hasChecklist)
                    @include('guides.partials.checklist')
                @endif

                @if ($hasSources)
                    @include('guides.partials.sources', [
                        'sources' => $tasteSources,
                        'disclaimer' => $tasteDisclaimer,
                    ])
                @endif

                @if ($hasFaq)
                    @include('use-cases.partials.faq')
                @endif
            </div>

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
                fathom-event="Try token guide mobile"
                class="w-full justify-center !py-3 shadow-[0_16px_40px_-12px_rgba(225,29,72,0.6)]"
            />
        </div>
    </div>
</x-layouts.app>
