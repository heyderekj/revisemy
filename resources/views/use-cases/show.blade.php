@php
    $hasInputs = ! empty($page['inputs']['items'] ?? null);
    $hasChecklist = ! empty($page['checklist']);
    $hasPrompts = ! empty($page['prompts']);
    $hasFeatures = ! empty($page['features']);
    $hasFaq = ! empty($page['faq']);
    $hasRelated = ! empty($related);
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
            @include('use-cases.partials.sticky-cta', ['fathomEvent' => 'Try token use case sticky'])

            <x-home-section first>
                @include('use-cases.partials.header')
                @include('use-cases.partials.hero')
            </x-home-section>

            @include('guides.partials.problem-loop')

            @if ($hasInputs)
                @include('use-cases.partials.inputs')
            @endif

            @if ($hasFeatures)
                @include('use-cases.partials.features')
            @endif

            @if ($hasChecklist)
                @include('use-cases.partials.checklist')
            @endif

            @if ($hasPrompts)
                @include('use-cases.partials.prompts')
            @endif

            @if ($hasFaq)
                @include('use-cases.partials.faq')
            @endif

            @if (! empty($isHost) && ! empty($page['connector_anchor']))
                <x-home-section>
                    <p class="text-sm leading-relaxed text-zinc-600">
                        Full paste-ready setup for {{ $page['label'] }}:
                        <a
                            href="{{ url('/connectors#'.$page['connector_anchor']) }}"
                            class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                        >Connectors · {{ $page['label'] }}</a>
                        ·
                        <a
                            href="/mcp-apps"
                            class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                        >MCP Apps vs review_url</a>
                    </p>
                </x-home-section>
            @endif

            @if ($hasRelated)
                @include('use-cases.partials.related')
            @endif

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
                fathom-event="Try token use case mobile"
                class="w-full justify-center"
            />
        </div>
    </x-page-frame>
</x-layouts.app>
