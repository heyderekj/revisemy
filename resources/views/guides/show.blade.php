@php
    $hasHosts = ! empty($page['hosts']);
    $hasSupportedAgents = ! empty($page['supported_agents']);
    $hasFeatures = ! empty($page['features']);
    $hasChecklist = ! empty($page['checklist']);
    $hasSources = ! empty($page['sources']);
    $hasChangelog = ! empty($page['changelog']);
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
            @include('use-cases.partials.sticky-cta', ['fathomEvent' => 'Try token guide sticky'])

            @php
                $productShots = $page['product_shots'] ?? null;
                $hasProductShotUi = is_array($productShots)
                    && (($productShots['stylized'] ?? null) === 'board' || ! empty($productShots['dir']));
            @endphp
            <x-home-section first :flush-bottom="$hasProductShotUi">
                @include('use-cases.partials.header')
                @include('guides.partials.hero')
                @if ($hasProductShotUi)
                    @include('guides.partials.product-shots')
                @endif
            </x-home-section>

            @unless ($hasChangelog)
                @include('guides.partials.problem-loop')
            @endunless

            @if ($hasSupportedAgents)
                @include('guides.partials.supported-agents')
            @endif

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

            @if ($hasChangelog)
                @include('guides.partials.changelog-entries')
            @endif

            @if ($hasFaq)
                @include('use-cases.partials.faq')
            @endif

            @if ($hasChangelog)
                @include('guides.partials.changelog-cta')
            @else
                @include('use-cases.partials.cta')
            @endif
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
                class="w-full justify-center"
            />
        </div>
    </x-page-frame>
</x-layouts.app>
