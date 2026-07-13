<section class="mt-16 scroll-mt-8 sm:mt-20">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Other review types</h2>
    <ul class="mt-6 space-y-3">
        @foreach ($related as $entry)
            <li>
                <a
                    href="{{ url('/for/'.$entry['slug']) }}"
                    class="group flex items-start gap-3 rounded-lg border border-transparent px-3 py-3 transition hover:border-zinc-900/8 hover:bg-white/60"
                >
                    <x-use-case-icon :name="$entry['icon']" size="sm" class="mt-0.5" />
                    <span>
                        <span class="block text-sm font-medium text-rose-600 transition group-hover:text-rose-700">
                            {{ $entry['label'] }}
                        </span>
                        <span class="mt-0.5 block text-sm leading-relaxed text-zinc-500">
                            {{ $entry['headline'] }}
                        </span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
