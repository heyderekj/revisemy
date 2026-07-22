<x-home-section>
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">FAQ</h2>
    <div class="mt-6 space-y-6">
        @foreach ($page['faq'] as $item)
            <article>
                <h3 class="text-sm font-semibold text-zinc-900">{{ $item['q'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $item['a'] }}</p>
            </article>
        @endforeach
    </div>
</x-home-section>
