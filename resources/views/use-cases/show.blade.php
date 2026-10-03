<x-layouts.marketing :title="$page['title']" :description="$page['description']" :keywords="$page['keywords']" fathom="Connect use case">
    <x-marketing-hero :eyebrow="'Built for '.$page['label']" :icon="$page['icon']" :headline="$page['headline']" :subheadline="$page['subheadline']">
        <x-review-mock :sample="$page['sample'] ?? $page['review_type'] ?? null" class="mt-10 sm:mt-12 lg:-mx-32" />
    </x-marketing-hero>

    @include('guides.partials.problem-loop')

    @if (! empty($page['inputs']['items'] ?? null))
        @include('use-cases.partials.inputs')
    @endif

    @if (! empty($page['features']))
        @include('use-cases.partials.features')
    @endif

    @if (! empty($page['checklist']))
        <x-list-section
            :heading="$page['checklist_heading'] ?? ($page['label'].' checklist')"
            :intro="$page['checklist_intro'] ?? (! empty($page['inputs']) ? 'ReviseMy runs these checks as second-opinion hints. Your marks stay the brief.' : null)"
            :items="$page['checklist']"
        />
    @endif

    @if (! empty($page['prompts']))
        @include('use-cases.partials.prompts')
    @endif

    @if (! empty($page['faq']))
        @include('use-cases.partials.faq')
    @endif

    @if (! empty($related))
        <x-link-list heading="Other review types" :links="collect($related)->map(fn ($r) => ['href' => url('/for/'.$r['slug']), 'label' => $r['label'], 'line' => $r['headline'], 'icon' => $r['icon']])->all()" />
    @endif
</x-layouts.marketing>
