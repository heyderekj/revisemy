<x-layouts.marketing
    title="ReviseMy alternatives — fair comparisons for visual feedback tools"
    description="When ReviseMy’s human-in-the-loop review fits, and when Figma comments, website annotation tools or an AI chat app are the better choice."
    :keywords="collect($pages)->pluck('label')->map(fn ($l) => $l.' alternative')->all()"
    fathom="Connect alternatives hub"
>
    <x-marketing-hero eyebrow="Alternatives" headline="Fair comparisons, not dunk contests" subheadline="When ReviseMy’s loop fits — and when Figma comments, a website annotation tool or just an AI chat app is the better choice." />

    <x-link-list heading="Compare" :links="collect($pages)->map(fn ($p) => ['href' => url('/alternatives/'.$p['slug']), 'label' => $p['label'], 'line' => $p['teaser'], 'icon' => $p['icon']])->all()" />

    <x-link-list heading="Keep going" :links="\App\Support\MarketingPages::more('/alternatives')" />
</x-layouts.marketing>
