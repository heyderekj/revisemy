<section class="mt-16 scroll-mt-8 sm:mt-20">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Hosts</h2>
    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
        Same MCP endpoint for every host. Generate a try token on the homepage to copy ready-made config.
    </p>

    <div class="mt-8 space-y-8">
        @foreach ($page['hosts'] as $host)
            <article id="{{ $host['id'] }}" class="scroll-mt-24">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex size-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-600">
                        <x-host-icon :name="$host['id']" />
                    </span>
                    <h3 class="text-sm font-semibold text-zinc-900">{{ $host['label'] }}</h3>
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600">
                        {{ $host['mode'] }}
                    </span>
                </div>
                <p class="mt-2 text-sm leading-relaxed text-zinc-500">{{ $host['body'] }}</p>
                <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm leading-relaxed text-zinc-600">
                    @foreach ($host['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
                @if (! empty($host['prompt']))
                    <div class="mt-4 space-y-3">
                        <x-copy-prompt label="Checkup prompt" :text="$host['prompt']" />
                        <p class="text-[13px] leading-relaxed text-zinc-500">
                            Prefer <span class="font-medium text-zinc-700">Ask agent</span> on the
                            <a href="/#setup" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 hover:text-rose-500">homepage</a>
                            for a prompt with your try-token config already filled in.
                        </p>
                    </div>
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
