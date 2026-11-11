<x-layouts.marketing
    title="Skills for your agent — packs for websites, apps, email and slides"
    description="ReviseMy review packs and curated community skills for motion, interface copy, typography, accessibility, and capture."
    :keywords="['agent skills', 'Claude Code skills', 'landing page feedback', 'homepage review', 'design review']"
    fathom="Skills hub"
>
    <x-marketing-hero eyebrow="Skills" icon="squares-2x2" headline="Skills for your agent" subheadline="Pick a ReviseMy review pack, or bring a specialist skill into your next design review." />

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

    <x-home-section id="example">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">{{ $example['title'] }}</h2>
        <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-zinc-600">{{ $example['brief'] }}</p>
        <x-copy-prompt class="mt-5" label="Ask your agent" :text="$example['request']" />
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-semibold text-zinc-900">A useful mark</dt>
                <dd class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $example['mark'] }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-zinc-900">What to check on the next pass</dt>
                <dd class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $example['verify'] }}</dd>
            </div>
        </dl>
    </x-home-section>

    <x-home-section id="community">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Community skills</h2>
        <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-zinc-600">Describe what you want reviewed. A connected agent can choose relevant guidance from the skills available through ReviseMy MCP. You can also ask for a skill by name.</p>

        <ul class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($community as $skill)
                <li class="flex min-w-0 flex-col rounded-xl bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">{{ $skill['focus'] }}</p>
                    <h3 class="mt-1 text-base font-semibold text-zinc-900">{{ $skill['name'] }}</h3>
                    <p class="mt-0.5 text-sm text-zinc-600">By {{ $skill['author'] }}</p>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-600">{{ $skill['line'] }}</p>
                    <a href="{{ $skill['source'] }}" class="mt-3 w-fit text-sm font-medium text-zinc-900 underline underline-offset-4" aria-label="Original source for {{ $skill['name'] }}">{{ $skill['get'] ?? 'Original source' }}</a>
                    @isset($skill['mcp'])
                        <p class="mt-1 text-xs text-zinc-600">Available through ReviseMy MCP</p>
                    @endisset
                    @isset($skill['setup'])
                        <p class="mt-1 text-xs text-zinc-600">{{ $skill['setup'] }}</p>
                    @endisset
                    @isset($skill['install'])
                        <details class="mt-3">
                            <summary class="w-fit cursor-pointer text-sm text-zinc-700">Install command</summary>
                            <x-copy-field class="mt-2" :value="$skill['install']" :connect="false" />
                        </details>
                    @endisset
                    <details class="mt-3">
                        <summary class="w-fit cursor-pointer text-sm text-zinc-700">Example prompt</summary>
                        <x-copy-prompt class="mt-2" label="Ask your agent" :text="$skill['prompt']" />
                    </details>
                </li>
            @endforeach
        </ul>
    </x-home-section>

    <x-list-section
        heading="Install the ReviseMy packs"
        intro="The ReviseMy packs ship together. They work on their own, and connecting ReviseMy gives you a review to mark up."
        :items="[
            'Claude Code: run /plugin marketplace add heyderekj/revisemy, then /plugin install revisemy@revisemy.',
            'Cursor, Codex and other agents: run npx skills add heyderekj/revisemy.',
        ]"
    />

    <x-link-list data-no-toc heading="Keep going" :links="\App\Support\MarketingPages::more('/skills')" />
</x-layouts.marketing>
