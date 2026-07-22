<x-home-section>
    <div class="flex items-baseline justify-between gap-4">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Releases</h2>
        <p class="font-mono text-[11px] text-zinc-400">v{{ config('revisemy.version') }}</p>
    </div>

    <ol class="mt-8 space-y-10">
        @foreach (config('changelog.entries', []) as $entry)
            <li class="border-t border-zinc-200 pt-8 first:border-t-0 first:pt-0">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h3 class="font-mono text-base font-semibold text-zinc-900">
                        v{{ $entry['version'] }}
                    </h3>
                    @if (! empty($entry['date']))
                        <time class="text-sm text-zinc-400" datetime="{{ $entry['date'] }}">
                            {{ $entry['date'] }}
                        </time>
                    @endif
                </div>
                @if (! empty($entry['title']))
                    <p class="mt-2 text-[15px] font-medium text-zinc-800">
                        {{ $entry['title'] }}
                    </p>
                @endif
                @if (! empty($entry['highlights']))
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
                        @foreach ($entry['highlights'] as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($entry['links']))
                    <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                        @foreach ($entry['links'] as $link)
                            <li>
                                <a
                                    href="{{ $link['href'] }}"
                                    class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700"
                                >{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ol>
</x-home-section>
