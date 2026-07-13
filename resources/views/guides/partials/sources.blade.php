<section class="mt-16 scroll-mt-8 sm:mt-20" id="craft-sources">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Where the craft lenses come from</h2>
    <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-zinc-600">
        {{ $page['sources_intro'] ?? 'Type-aware second opinion draws on published craft principles. Hints are ReviseMy distillations — not quotes, reviews, or endorsements from the people behind these works.' }}
    </p>

    <div class="mt-10 divide-y divide-zinc-200/80 border-y border-zinc-200/80">
        @foreach ($sources as $group)
            @php
                $icon = match ($group['type'] ?? '') {
                    'ui' => 'cursor-arrow-rays',
                    'website' => 'globe-alt',
                    'presentation' => 'presentation-chart-bar',
                    'email' => 'envelope',
                    default => 'swatch',
                };
            @endphp
            <div class="grid gap-6 py-8 first:pt-8 last:pb-8 sm:grid-cols-[9.5rem_minmax(0,1fr)] sm:gap-8">
                <div class="sm:pt-0.5">
                    <div class="flex items-center gap-2.5 sm:flex-col sm:items-start sm:gap-2">
                        <x-use-case-icon :name="$icon" size="sm" />
                        <div>
                            <p class="text-sm font-semibold tracking-tight text-zinc-900">{{ $group['label'] }}</p>
                            <p class="mt-0.5 text-[11px] font-medium tabular-nums text-zinc-400">
                                {{ count($group['lenses']) }} {{ count($group['lenses']) === 1 ? 'lens' : 'lenses' }}
                            </p>
                        </div>
                    </div>
                </div>

                <ul class="grid gap-6 min-[28rem]:grid-cols-2 min-[28rem]:gap-x-6 min-[28rem]:gap-y-7">
                    @foreach ($group['lenses'] as $lens)
                        <li class="min-w-0">
                            @if (! empty($lens['source_url']))
                                <a
                                    href="{{ $lens['source_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group inline-flex max-w-full items-baseline gap-1.5 text-[15px] font-semibold tracking-tight text-zinc-900 transition hover:text-rose-600"
                                >
                                    <span class="truncate">{{ $lens['name'] }}</span>
                                    <span class="shrink-0 text-zinc-300 transition group-hover:text-rose-400" aria-hidden="true">↗</span>
                                </a>
                                @if (! empty($lens['source_label']))
                                    <p class="mt-0.5 truncate text-[11px] text-zinc-400">{{ $lens['source_label'] }}</p>
                                @endif
                            @else
                                <p class="text-[15px] font-semibold tracking-tight text-zinc-900">{{ $lens['name'] }}</p>
                            @endif
                            <p class="mt-1.5 text-sm leading-relaxed text-pretty text-zinc-500">{{ $lens['blurb'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <p class="mt-6 text-sm leading-relaxed text-zinc-500">
        {{ $disclaimer }}
        On a review, open the craft chip next to Second opinion for the lenses that apply to that type.
    </p>
</section>
