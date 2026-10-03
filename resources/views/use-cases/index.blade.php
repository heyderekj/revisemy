<x-layouts.marketing
    title="What ReviseMy is built for — review types and who uses it"
    description="Visual feedback for your agent across UI, websites, email and slides — for reviewers, agencies, designers, product, engineers and founders."
    :keywords="['design review', 'UI review', 'website review', 'email review', 'slide review', 'AI agents', 'MCP', 'designers', 'agencies']"
    fathom="Connect for hub"
>
    <x-marketing-hero eyebrow="Built for" headline="Anything visual, anyone in the loop" subheadline="Pick what you’re reviewing, or who you are." />

    <x-link-list heading="Review types" :links="collect($pages)->map(fn ($p) => ['href' => url('/for/'.$p['slug']), 'label' => $p['label'], 'line' => $p['headline'], 'icon' => $p['icon']])->all()" />

    <x-home-section>
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Who it’s for</h2>
        <ul class="mt-6 grid grid-cols-1 gap-2 min-[30rem]:grid-cols-2">
            @foreach ($audiences as $entry)
                <li>
                    <a href="{{ url('/for/'.$entry['slug']) }}" class="group flex h-full items-start gap-3 rounded-xl bg-card px-3 py-3 transition-colors hover:bg-chip">
                        <x-use-case-icon :name="$entry['icon']" size="sm" class="mt-0.5 shrink-0" />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-zinc-900">{{ $entry['label'] }}</span>
                            <span class="mt-0.5 block text-sm leading-relaxed text-zinc-500">{{ $entry['headline'] }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-6 grid grid-cols-1 gap-x-8 gap-y-5 min-[30rem]:grid-cols-2">
            @foreach ($notes as $slug => $note)
                <div id="{{ $slug }}" class="scroll-mt-8">
                    <p class="flex items-center gap-2 text-sm font-medium text-zinc-900">
                        <x-use-case-icon :name="$note['icon']" size="sm" bare /> {{ $note['label'] }}
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $note['line'] }}</p>
                </div>
            @endforeach
        </div>
    </x-home-section>

    <x-link-list heading="Keep going" :links="\App\Support\MarketingPages::more()" />
</x-layouts.marketing>
