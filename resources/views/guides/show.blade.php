@php
    $productShots = $page['product_shots'] ?? null;
    $hasProductShotUi = is_array($productShots)
        && (($productShots['stylized'] ?? null) === 'board' || ! empty($productShots['dir']));
    $isChangelog = ! empty($page['changelog']);
@endphp

<x-layouts.marketing :title="$page['title']" :description="$page['description']" :keywords="$page['keywords']" fathom="Connect guide" :wide="! empty($page['hosts'])">
    <x-marketing-hero :eyebrow="$page['label']" :icon="$page['icon'] ?? null" :mark-icon="$page['mark_icon'] ?? null" :headline="$page['headline']" :subheadline="$page['subheadline']">
        @if ($hasProductShotUi)
            @include('guides.partials.product-shots')
        @endif
    </x-marketing-hero>

    @unless ($isChangelog)
        @include('guides.partials.problem-loop')
    @endunless

    @if (! empty($page['hosts']))
        @include('guides.partials.hosts')
    @endif

    @foreach ($page['sections'] ?? [] as $section)
        <x-list-section :id="$section['id']" :heading="$section['heading']" :intro="$section['body']" :items="$section['items']" class="scroll-mt-8" />
    @endforeach

    @if (! empty($page['features']))
        @include('use-cases.partials.features')
    @endif

    @if (! empty($page['checklist']))
        <x-list-section :heading="$page['checklist_heading'] ?? 'How it stays straight'" :items="$page['checklist']" />
    @endif

    @if (! empty($page['sources']))
        @include('guides.partials.sources', [
            'sources' => \App\Support\TasteLenses::allTypes(),
            'disclaimer' => \App\Support\TasteLenses::disclaimer(),
        ])
    @endif

    @if ($isChangelog)
        @include('guides.partials.changelog-entries')
    @endif

    @if (! empty($page['faq']))
        @include('use-cases.partials.faq')
    @endif

    <x-link-list heading="Keep going" :links="\App\Support\MarketingPages::more($page['path'])" />
</x-layouts.marketing>
