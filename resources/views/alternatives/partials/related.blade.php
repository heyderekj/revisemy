<section class="mt-14 scroll-mt-8 sm:mt-16">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Keep exploring</h2>

    @if (! empty($related))
        <ul class="mt-6 divide-y divide-zinc-900/8 border-y border-zinc-900/8">
            @foreach ($related as $entry)
                <li>
                    <a
                        href="{{ url('/alternatives/'.$entry['slug']) }}"
                        class="group flex items-start gap-3 py-4 transition"
                    >
                        <x-use-case-icon
                            :name="$entry['icon']"
                            size="sm"
                            class="mt-0.5 shrink-0 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-zinc-900 transition group-hover:text-rose-600">
                                {{ $entry['label'] }}
                            </span>
                            <span class="mt-0.5 block text-sm leading-relaxed text-zinc-500">
                                {{ $entry['teaser'] }}
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <ul class="mt-6 space-y-2 text-[15px]">
        <li>
            <a href="/alternatives" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">All alternatives</a>
            <span class="text-zinc-400"> — </span>
            <span class="text-zinc-500">comparison hub</span>
        </li>
        <li>
            <a href="/board" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Board</a>
            <span class="text-zinc-400"> — </span>
            <span class="text-zinc-500">open → verified mark lifecycle</span>
        </li>
        <li>
            <a href="/second-opinion" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Second opinion</a>
            <span class="text-zinc-400"> — </span>
            <span class="text-zinc-500">AI hints that never override your marks</span>
        </li>
        <li>
            <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Connectors</a>
            <span class="text-zinc-400"> — </span>
            <span class="text-zinc-500">ChatGPT, Claude, Copilot, Cursor, Grok</span>
        </li>
    </ul>
</section>
