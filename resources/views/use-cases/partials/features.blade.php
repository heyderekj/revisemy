<x-home-section>
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">
        {{ $page['features_heading'] ?? ('Features for '.strtolower($page['label']).(! empty($page['review_type']) ? ' review' : '')) }}
    </h2>
    <div class="mt-8 grid grid-cols-1 gap-x-8 gap-y-9 min-[30rem]:grid-cols-2">
        @foreach ($page['features'] as $feature)
            <article>
                <x-use-case-icon :name="$feature['icon']" />
                <h3 class="mt-3 text-sm font-semibold text-zinc-900">{{ $feature['title'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $feature['body'] }}</p>
            </article>
        @endforeach
    </div>
</x-home-section>
