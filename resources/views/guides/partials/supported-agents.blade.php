<section class="mt-14 scroll-mt-8 sm:mt-16">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">
        {{ $page['supported_agents_heading'] ?? "Where it's available" }}
    </h2>
    <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-zinc-600">
        {{ $page['supported_agents_intro'] ?? 'Hosts that can open the review inline in chat via MCP Apps.' }}
    </p>
    <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-3">
        @foreach ($page['supported_agents'] as $agent)
            <li>
                <a href="{{ url('/for/'.$agent['id']) }}" class="group inline-flex items-center gap-2">
                    <span class="inline-flex size-7 items-center justify-center rounded-md bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80">
                        <x-host-icon :name="$agent['id']" />
                    </span>
                    <span class="text-sm font-medium text-zinc-700 transition group-hover:text-rose-600">
                        {{ $agent['label'] }}
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
    <p class="mt-4 text-sm leading-relaxed text-zinc-500">
        Cursor, Claude Code, and Grok still run the same loop via
        <code class="font-mono text-[13px] text-rose-600">review_url</code>
        —
        <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">setup guides</a>
        for every host.
    </p>
</section>
