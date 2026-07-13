<section class="mt-16 scroll-mt-8 sm:mt-20">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">How to get pixels in</h2>
    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
        {{ $page['inputs']['intro'] }}
    </p>

    <div class="mt-8 space-y-5">
        @foreach ($page['inputs']['items'] as $input)
            <article class="flex items-start gap-3">
                <x-use-case-icon :name="$input['icon']" size="sm" class="mt-0.5" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-zinc-900">{{ $input['label'] }}</h3>
                        <code class="font-mono text-[12px] text-rose-600">{{ $input['key'] }}</code>
                        @if (! empty($input['primary']))
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600">
                                Recommended
                            </span>
                        @endif
                    </div>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $input['body'] }}</p>
                </div>
            </article>
        @endforeach
    </div>
</section>
