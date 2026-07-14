<x-layouts.app
    title="ReviseMy alternatives — thoughtful comparisons for design feedback tools"
    description="Compare ReviseMy to Figma comments, Marker.io, Pastel, Lucidly, and just using an AI chat app. Fair guides for when human-in-the-loop agent checkups fit — and when to keep the other tool."
    :keywords="['ReviseMy alternatives', 'Figma comments alternative', 'Marker.io alternative', 'Pastel alternative', 'Lucidly alternative', 'AI chat app', 'MCP design review']"
    schema="page"
>
    <div
        class="rm-wash relative min-h-screen"
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
        <div class="rm-grid pointer-events-none absolute inset-0"></div>

        <div class="relative z-10 mx-auto max-w-[720px] px-5 pb-20 pt-8 sm:px-8 sm:pb-24 sm:pt-10">
            <div class="relative">
                <div class="pointer-events-none sticky top-8 z-30 hidden h-0 sm:block">
                    <div
                        class="flex justify-end"
                        x-show="! atCta"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    >
                        <div class="pointer-events-auto">
                            <x-try-token-cta fathom-event="Try token alternatives hub sticky" />
                        </div>
                    </div>
                </div>

                @include('use-cases.partials.header')

                <section class="rm-fade-up mt-10 sm:mt-12">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Alternatives</p>
                    <h1 class="mt-3 max-w-xl text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                        Thoughtful comparisons, not dunk contests
                    </h1>
                    <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                        ReviseMy is a human-in-the-loop design checkup for AI agents. These guides explain when that loop fits — and when Figma comments, website annotation tools, or just an AI chat app are the better choice.
                    </p>
                </section>

                <section class="mt-12">
                    <ul class="divide-y divide-zinc-900/8 border-y border-zinc-900/8">
                        @foreach ($pages as $entry)
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
                </section>

                <section class="mt-14">
                    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Also useful</h2>
                    <ul class="mt-6 space-y-3 text-[15px]">
                        <li>
                            <a href="/board" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                                Board
                            </a>
                            <span class="text-zinc-400"> — </span>
                            <span class="text-zinc-500">Mark lifecycle open → verified</span>
                        </li>
                        <li>
                            <a href="/second-opinion" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                                Second opinion
                            </a>
                            <span class="text-zinc-400"> — </span>
                            <span class="text-zinc-500">Checklist and vision hints that never override your marks</span>
                        </li>
                        <li>
                            <a href="/connectors" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">
                                Connectors
                            </a>
                            <span class="text-zinc-400"> — </span>
                            <span class="text-zinc-500">ChatGPT, Claude, Copilot, Cursor, Grok</span>
                        </li>
                    </ul>
                </section>
            </div>

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
                fathom-event="Try token alternatives hub mobile"
                class="w-full justify-center !py-3 shadow-[0_16px_40px_-12px_rgba(225,29,72,0.6)]"
            />
        </div>
    </div>
</x-layouts.app>
