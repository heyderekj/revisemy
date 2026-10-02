<x-layouts.marketing :title="$page['title']" :description="$page['description']" :keywords="$page['keywords']" fathom="Connect alternatives">
    <x-marketing-hero :eyebrow="$page['competitor'].' alternatives'" :icon="$page['icon']" :headline="$page['headline']" :subheadline="$page['subheadline']">
        @if (! empty($page['competitor_url']))
            <a href="{{ $page['competitor_url'] }}" class="link mt-4 inline-block text-sm" @if (str_starts_with($page['competitor_url'], 'http')) target="_blank" rel="noreferrer" @endif>Visit {{ $page['competitor'] }} ↗</a>
        @endif
    </x-marketing-hero>

    <x-list-section :heading="'Why people look for a '.$page['competitor'].' alternative'" :items="$page['why_look']" />
    <x-list-section heading="What to look for" :items="$page['what_to_look_for']" />

    <x-home-section>
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Options</h2>
        <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-zinc-600">Not interchangeable: each solves a different version of the problem.</p>
        <div class="mt-6 space-y-3">
            @foreach (array_merge([config('alternatives.revisemy')], $page['recommended']) as $option)
                <article class="rounded-2xl bg-card px-4 py-4 sm:px-5 sm:py-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-semibold text-zinc-900"><a href="{{ $option['href'] }}" class="hover:underline">{{ $option['label'] }}</a></h3>
                        @if (! empty($option['badge']))
                            <span class="rounded-md bg-accent px-1.5 py-0.5 text-xs font-medium text-accent-foreground">{{ $option['badge'] }}</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $option['summary'] }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-500"><span class="font-medium text-zinc-700">Best for:</span> {{ $option['best_for'] }}</p>
                    @if (! empty($option['bullets']))
                        <ul class="mt-3 space-y-1 text-sm leading-relaxed text-zinc-500">
                            @foreach ($option['bullets'] as $bullet)
                                <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-zinc-300" aria-hidden="true"></span>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>
    </x-home-section>

    <x-list-section :heading="'When to keep '.$page['competitor']" :items="$page['keep_theirs']" />

    @if (! empty($page['faq']))
        @include('use-cases.partials.faq')
    @endif

    <x-link-list
        heading="Keep exploring"
        :links="collect($related)->map(fn ($r) => ['href' => url('/alternatives/'.$r['slug']), 'label' => $r['label'], 'line' => $r['teaser'], 'icon' => $r['icon']])->concat(\App\Support\MarketingPages::more('/alternatives'))->take(6)->all()"
    />
</x-layouts.marketing>
