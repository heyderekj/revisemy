<x-layouts.app
    title="What ReviseMy is built for — review types and audiences"
    description="Human-in-the-loop design review for AI agents across UI, websites, email, and slides — plus paths for reviewers, designers, product, engineers, founders, and agencies."
    :keywords="['design review', 'UI review', 'website review', 'email review', 'slide review', 'AI agents', 'MCP', 'designers', 'product managers', 'agencies']"
    schema="page"
>
    <x-page-frame
        x-data="{
            pastHero: false,
            atCta: false,
            initStickyCta() {
                const hero = document.getElementById('rm-use-case-hero-cta');
                const cta = document.getElementById('rm-use-case-footer-cta');
                if (hero) {
                    new IntersectionObserver(
                        ([e]) => { this.pastHero = ! e.isIntersecting }
                    ).observe(hero);
                }
                if (cta) {
                    new IntersectionObserver(
                        ([e]) => { this.atCta = e.isIntersecting },
                        { rootMargin: '80px 0px 0px 0px' }
                    ).observe(cta);
                }
            }
        }"
        x-init="initStickyCta()"
    >
        <div class="relative">
            @include('use-cases.partials.sticky-cta', ['fathomEvent' => 'Try token for hub sticky'])

            <x-home-section first>
                @include('use-cases.partials.header')
                <div class="rm-fade-up mt-10 sm:mt-12">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Built for</p>
                    <h1 class="mt-3 max-w-xl text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                        Review types for agents and humans
                    </h1>
                    <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                        Pick the artifact you are checking up on — or jump to a role path if you care more about who is in the loop than which file type.
                    </p>
                </div>
            </x-home-section>

            <x-home-section>
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Review types</h2>
                <ul class="mt-6 grid grid-cols-1 gap-3 min-[30rem]:grid-cols-2">
                    @foreach ($pages as $entry)
                        <li class="min-h-0">
                            <a
                                href="{{ url('/for/'.$entry['slug']) }}"
                                class="group flex h-full items-start gap-3 border border-zinc-200 bg-white/70 px-3 py-3 transition hover:border-zinc-300 hover:bg-white"
                            >
                                <x-use-case-icon
                                    :name="$entry['icon']"
                                    size="sm"
                                    class="mt-0.5 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80"
                                />
                                <span>
                                    <span class="block text-sm font-medium text-zinc-900 transition group-hover:text-rose-600">
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
            </x-home-section>

            @if (! empty($audiences))
                <x-home-section>
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Audiences</h2>
                    <ul class="mt-6 divide-y divide-zinc-200 border-y border-zinc-200">
                        @foreach ($audiences as $entry)
                            <li>
                                <a
                                    href="{{ url('/for/'.$entry['slug']) }}"
                                    class="group flex items-start gap-3 py-4 transition sm:items-center"
                                >
                                    <x-use-case-icon
                                        :name="$entry['icon']"
                                        size="sm"
                                        class="mt-0.5 shrink-0 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80 sm:mt-0"
                                    />
                                    <span class="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
                                        <span class="shrink-0 text-sm font-medium text-zinc-900 transition group-hover:text-rose-600">
                                            {{ $entry['label'] }}
                                        </span>
                                        <span class="text-sm leading-relaxed text-zinc-500 sm:text-right">
                                            {{ $entry['headline'] }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-home-section>
            @endif

            @if (! empty($hosts))
                <x-home-section>
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Agents</h2>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-500">
                        Thin host landings — full paste paths live on
                        <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Connectors</a>.
                    </p>
                    <ul class="mt-6 grid grid-cols-1 gap-3 min-[30rem]:grid-cols-2">
                        @foreach ($hosts as $entry)
                            <li class="min-h-0">
                                <a
                                    href="{{ url('/for/'.$entry['slug']) }}"
                                    class="group flex h-full items-start gap-3 border border-zinc-200 bg-white/70 px-3 py-3 transition hover:border-zinc-300 hover:bg-white"
                                >
                                    <x-use-case-icon
                                        :name="$entry['icon']"
                                        size="sm"
                                        class="mt-0.5 transition group-hover:bg-rose-50 group-hover:text-rose-600 group-hover:ring-rose-200/80"
                                    />
                                    <span>
                                        <span class="block text-sm font-medium text-zinc-900 transition group-hover:text-rose-600">
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
                </x-home-section>
            @endif

            <x-home-section>
                <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Also useful</h2>
                <ul class="mt-6 space-y-3 text-[15px]">
                    <li>
                        <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                            Connectors
                        </a>
                        <span class="text-zinc-400"> — </span>
                        <span class="text-zinc-500">ChatGPT, Claude, Copilot, Cursor, Grok</span>
                    </li>
                    <li>
                        <a href="/mcp-apps" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                            MCP Apps
                        </a>
                        <span class="text-zinc-400"> — </span>
                        <span class="text-zinc-500">Inline review vs review_url</span>
                    </li>
                    <li>
                        <a href="/second-opinion" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                            Second opinion
                        </a>
                        <span class="text-zinc-400"> — </span>
                        <span class="text-zinc-500">Checklist and vision hints that never override your marks</span>
                    </li>
                    <li>
                        <a href="/alternatives" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                            Alternatives
                        </a>
                        <span class="text-zinc-400"> — </span>
                        <span class="text-zinc-500">Figma comments, Marker, Pastel, Lucidly, MarkUp, Workflow, Simple Commenter, AI chat apps</span>
                    </li>
                </ul>
            </x-home-section>

            @include('use-cases.partials.cta')
        </div>

        <div
            class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] sm:hidden"
            x-show="pastHero && ! atCta"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-3 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-3 opacity-0"
        >
            <x-try-token-cta
                fathom-event="Try token for hub mobile"
                class="w-full justify-center"
            />
        </div>
    </x-page-frame>
</x-layouts.app>
