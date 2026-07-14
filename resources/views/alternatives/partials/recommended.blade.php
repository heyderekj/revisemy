<section class="mt-14 scroll-mt-8 sm:mt-16">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Recommended options</h2>
    <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
        These are not interchangeable replacements. Each option solves a different version of the problem people usually mean when they search.
    </p>

    <div class="mt-8 space-y-6">
        @foreach ($page['recommended'] as $option)
            <article class="rounded-xl border border-zinc-900/8 bg-white/70 px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex flex-wrap items-center gap-2">
                    @if (! empty($option['badge']))
                        <span class="rounded-md bg-rose-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700 ring-1 ring-rose-100">
                            {{ $option['badge'] }}
                        </span>
                    @endif
                    <h3 class="text-base font-semibold text-zinc-900">
                        <a
                            href="{{ $option['href'] }}"
                            class="transition hover:text-rose-600"
                        >{{ $option['label'] }}</a>
                    </h3>
                </div>
                <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $option['summary'] }}</p>
                <p class="mt-3 text-sm leading-relaxed text-zinc-500">
                    <span class="font-medium text-zinc-700">Best for:</span> {{ $option['best_for'] }}
                </p>
                @if (! empty($option['bullets']))
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm leading-relaxed text-zinc-500">
                        @foreach ($option['bullets'] as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </div>
</section>
