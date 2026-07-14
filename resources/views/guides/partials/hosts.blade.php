<section class="mt-16 scroll-mt-8 sm:mt-20">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Hosts</h2>
    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
        Same MCP endpoint and Bearer try token for every host — only the install UI changes. Generate a free try token on the
        <a href="/#setup" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">homepage</a>
        to copy ready-made config, then jump to your host below for the exact steps and a checkup prompt.
    </p>

    <nav
        class="sticky top-8 z-20 mt-6 w-full sm:w-[calc(100%-12rem)]"
        aria-label="Jump to host"
        x-data="{
            active: @js($page['hosts'][0]['id'] ?? 'chatgpt'),
            init() {
                const ids = @js(collect($page['hosts'])->pluck('id')->values()->all());
                const fromHash = window.location.hash.replace(/^#/, '');
                if (ids.includes(fromHash)) {
                    this.active = fromHash;
                }

                const articles = ids
                    .map((id) => document.getElementById(id))
                    .filter(Boolean);

                if (! articles.length || ! ('IntersectionObserver' in window)) {
                    return;
                }

                const observer = new IntersectionObserver(
                    (entries) => {
                        const visible = entries
                            .filter((entry) => entry.isIntersecting)
                            .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

                        if (visible[0]?.target?.id) {
                            this.active = visible[0].target.id;
                        }
                    },
                    {
                        rootMargin: '-18% 0px -62% 0px',
                        threshold: [0, 0.2, 0.45, 0.7],
                    }
                );

                articles.forEach((article) => observer.observe(article));
            },
        }"
    >
        <div class="overflow-x-auto overscroll-x-contain rounded-xl border border-zinc-200 bg-zinc-50/95 p-1 shadow-sm backdrop-blur [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <div class="flex w-max min-w-full gap-1">
                @foreach ($page['hosts'] as $host)
                    <a
                        href="#{{ $host['id'] }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="active === '{{ $host['id'] }}' ? 'bg-white text-zinc-900' : 'text-zinc-500 hover:text-zinc-800'"
                        x-on:click="active = '{{ $host['id'] }}'"
                    >
                        <x-host-icon :name="$host['id']" class="opacity-90" />
                        {{ $host['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </nav>

    <div class="mt-8 space-y-10">
        @foreach ($page['hosts'] as $host)
            <article id="{{ $host['id'] }}" class="scroll-mt-32">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex size-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-600">
                        <x-host-icon :name="$host['id']" />
                    </span>
                    <h3 class="text-sm font-semibold text-zinc-900">{{ $host['label'] }}</h3>
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600">
                        {{ $host['mode'] }}
                    </span>
                </div>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-500">{{ $host['body'] }}</p>

                <x-setup-journey class="mt-4">
                    @foreach ($host['steps'] as $step)
                        <x-setup-step :step="$loop->iteration" :label="$step['label']">
                            @if (! empty($step['body']))
                                <p class="text-[15px] leading-relaxed text-zinc-600">{{ $step['body'] }}</p>
                            @endif
                        </x-setup-step>
                    @endforeach

                    @if (! empty($host['prompt']))
                        <x-copy-prompt
                            :step="count($host['steps']) + 1"
                            label="Then run a checkup"
                            :text="$host['prompt']"
                        />
                    @endif
                </x-setup-journey>

                @if (! empty($host['prompt']))
                    <p class="mt-4 text-[13px] leading-relaxed text-zinc-500">
                        Prefer <span class="font-medium text-zinc-700">Ask agent</span> on the
                        <a href="/#setup" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 hover:text-rose-500">homepage</a>
                        for a prompt with your try-token config already filled in.
                    </p>
                @endif
            </article>
        @endforeach
    </div>

    <p class="mt-8 text-[15px] leading-relaxed text-zinc-600">
        <a href="/#setup" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
            Get a try token and copy config
        </a>
        on the homepage — then come back here if you need the host overview.
    </p>
</section>
