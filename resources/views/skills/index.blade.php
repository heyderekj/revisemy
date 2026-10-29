<x-layouts.marketing
    title="Skills for your agent — packs for marketing sites, industries and reviews"
    description="Free skill packs that teach your agent to review and fix your marketing site, with industry packs on top. They work with or without ReviseMy."
    :keywords="['agent skills', 'Claude Code skills', 'landing page feedback', 'homepage review', 'design review']"
    fathom="Skills hub"
>
    <x-marketing-hero eyebrow="Skills" icon="squares-2x2" headline="Skills for your agent" subheadline="Packs that teach your agent what good looks like, so it can review your work and fix it. Pick a pack, or install them all at once." />

    @foreach ($categories as $category)
        <x-link-list :heading="$category['label']" :intro="$category['line']" :links="$category['links']" />
    @endforeach

    <x-list-section
        heading="Install them"
        intro="Every pack ships in one install. They work on their own; ReviseMy adds a review you can mark up."
        :items="[
            'Claude Code: run /plugin marketplace add heyderekj/revisemy, then /plugin install revisemy@revisemy.',
            'Cursor, Codex and other agents: run npx skills add heyderekj/revisemy.',
        ]"
    />

    <x-link-list heading="Keep going" :links="\App\Support\MarketingPages::more('/skills')" />
</x-layouts.marketing>
