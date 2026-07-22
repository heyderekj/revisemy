@php
    $competitorLinks = $page['competitor_links'] ?? [];
    if ($competitorLinks === [] && ! empty($page['competitor_url']) && ! empty($page['competitor_link'])) {
        $competitorLinks = [[
            'label' => $page['competitor_link'],
            'url' => $page['competitor_url'],
            'external' => str_starts_with($page['competitor_url'], 'http'),
        ]];
    }

    $subheadlineHtml = e($page['subheadline']);
    $linkClass = 'font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700';

    foreach ($competitorLinks as $link) {
        $label = e($link['label']);
        $href = e($link['url']);
        $external = $link['external'] ?? str_starts_with($link['url'], 'http');
        $attrs = 'href="'.$href.'" class="'.$linkClass.'"';
        if ($external) {
            $attrs .= ' target="_blank" rel="noreferrer"';
        }
        $anchor = '<a '.$attrs.'>'.$label.'</a>';
        $pos = mb_strpos($subheadlineHtml, $label);
        if ($pos !== false) {
            $subheadlineHtml = mb_substr($subheadlineHtml, 0, $pos)
                .$anchor
                .mb_substr($subheadlineHtml, $pos + mb_strlen($label));
        }
    }
@endphp

<div class="rm-fade-up mt-10 sm:mt-12">
    <div class="flex items-center gap-3">
        <x-use-case-icon :name="$page['icon']" size="lg" />
        <div>
            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Competitor alternative</p>
            <p class="mt-0.5 text-sm font-medium text-zinc-600">{{ $page['competitor'] }}</p>
        </div>
    </div>
    <h1 class="mt-5 max-w-xl text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
        {{ $page['headline'] }}
    </h1>
    <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
        {!! $subheadlineHtml !!}
    </p>
</div>
