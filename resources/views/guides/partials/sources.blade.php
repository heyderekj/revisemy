<section class="mt-16 scroll-mt-8 sm:mt-20" id="craft-sources">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Where the craft lenses come from</h2>
    <p class="mt-3 text-[15px] leading-relaxed text-zinc-600">
        {{ $page['sources_intro'] ?? 'Type-aware second opinion draws on published craft principles. Hints are ReviseMy distillations — not quotes, reviews, or endorsements from the people behind these works.' }}
    </p>

    <div class="mt-8 space-y-8">
        @foreach ($sources as $group)
            <div>
                <p class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-[0.08em] text-sky-800">
                    {{ $group['label'] }}
                </p>
                <ul class="mt-3 space-y-4">
                    @foreach ($group['lenses'] as $lens)
                        <li class="border-l-2 border-sky-100 pl-4">
                            <p class="text-[15px] font-semibold text-zinc-900">{{ $lens['name'] }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $lens['blurb'] }}</p>
                            @if (! empty($lens['source_url']))
                                <a
                                    href="{{ $lens['source_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-1.5 inline-block text-sm font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                                >{{ $lens['source_label'] ?: $lens['source_url'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <p class="mt-8 text-sm leading-relaxed text-zinc-500">
        {{ $disclaimer }}
        On a review, open the craft chip next to Second opinion for the lenses that apply to that type.
    </p>
</section>
