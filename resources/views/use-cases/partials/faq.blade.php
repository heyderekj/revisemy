<section class="mt-16 scroll-mt-8 border-t border-zinc-900/8 pt-14 sm:mt-20 sm:pt-16">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">FAQ</h2>
    <div class="mt-6 space-y-6">
        @foreach ($page['faq'] as $item)
            <article>
                <h3 class="text-sm font-semibold text-zinc-900">{{ $item['q'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $item['a'] }}</p>
            </article>
        @endforeach
    </div>
</section>
