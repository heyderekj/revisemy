<x-layouts.marketing
    title="Skills for your agent — packs for websites, apps, email and slides"
    description="Free skill packs that teach your agent to review and fix websites, apps, email and slides, with industry packs on top. They work with or without ReviseMy."
    :keywords="['agent skills', 'Claude Code skills', 'landing page feedback', 'homepage review', 'design review']"
    fathom="Skills hub"
>
    <x-marketing-hero eyebrow="Skills" icon="squares-2x2" headline="Skills for your agent" subheadline="Packs that teach your agent what good looks like, so it can review your work and fix it. Pick a pack, or install them all at once." />

    @php
        [$types, $sections] = collect($categories)->partition(fn (array $category) => $category['tab']);
    @endphp

    @if ($types->isNotEmpty())
        <x-home-section id="by-type" x-data="{ tab: '{{ $types->first()['key'] }}' }">
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Packs by type</h2>
            <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-zinc-600">Pick what you're reviewing.</p>

            <div role="tablist" aria-label="Review type" class="mt-6 inline-flex gap-1 rounded-lg bg-chip p-1">
                @foreach ($types as $type)
                    <button
                        type="button"
                        role="tab"
                        id="tab-{{ $type['key'] }}"
                        aria-controls="panel-{{ $type['key'] }}"
                        x-on:click="tab = '{{ $type['key'] }}'"
                        x-bind:aria-selected="tab === '{{ $type['key'] }}'"
                        x-bind:class="tab === '{{ $type['key'] }}' ? 'bg-background text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-900'"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    >{{ $type['label'] }}</button>
                @endforeach
            </div>

            @foreach ($types as $type)
                <div role="tabpanel" id="panel-{{ $type['key'] }}" aria-labelledby="tab-{{ $type['key'] }}" x-show="tab === '{{ $type['key'] }}'" @unless ($loop->first) x-cloak @endunless class="mt-6">
                    <p class="max-w-xl text-sm leading-relaxed text-zinc-500">{{ $type['line'] }}</p>
                    <x-link-cards class="mt-4" :links="$type['links']" :soon="$type['soon']" />
                </div>
            @endforeach
        </x-home-section>
    @endif

    @foreach ($sections as $category)
        <x-link-list :id="$category['key']" :heading="$category['label']" :intro="$category['line']" :soon="$category['soon']" :links="$category['links']" />
    @endforeach

    <x-list-section
        heading="Install them"
        intro="Every pack ships in one install. They work on their own; ReviseMy adds a review you can mark up."
        :items="[
            'Claude Code: run /plugin marketplace add heyderekj/revisemy, then /plugin install revisemy@revisemy.',
            'Cursor, Codex and other agents: run npx skills add heyderekj/revisemy.',
        ]"
    />

    <x-link-list data-no-toc heading="Keep going" :links="\App\Support\MarketingPages::more('/skills')" />
</x-layouts.marketing>
