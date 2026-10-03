{{-- The site around every public page: the sidebar nav on desktop, a top bar
     and drawer on a phone, and the footer. Mark a page's own Connect button
     with data-rm-hero-cta and its closing one with data-rm-end-cta: the shell's
     Connect follows you down in between. Without a hero button it shows from
     the start. --}}
@props([
    /** Connect buttons in the chrome. Off for pages that are already past it. */
    'cta' => true,
    'ctaHref' => '/connect',
    'fathom' => 'Connect',
    'stickyEvent' => null,
    'headerEvent' => null,
])

<div
    {{ $attributes->class('relative min-h-screen') }}
    x-data="{
        mobileNav: false,
        pastHero: false,
        atEnd: false,
        get showHeaderCta() { return {{ $cta ? 'true' : 'false' }} && this.pastHero && ! this.atEnd },
        init() {
            const hero = document.querySelector('[data-rm-hero-cta]');
            const end = document.querySelector('[data-rm-end-cta]');
            if (hero) {
                // Flips only once the page's own button has fully left the viewport.
                new IntersectionObserver(([e]) => { this.pastHero = ! e.isIntersecting }).observe(hero);
            } else {
                this.pastHero = true;
            }
            if (end) {
                new IntersectionObserver(
                    ([e]) => { this.atEnd = e.isIntersecting },
                    { rootMargin: '0px 0px -20% 0px' }
                ).observe(end);
            }
            this.$watch('mobileNav', (open) => document.documentElement.classList.toggle('overflow-hidden', open));
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.mobileNav) this.mobileNav = false;
            });
        },
    }"
>
    {{-- The page column, inset from the viewport. --}}
    <div class="relative z-10 mx-auto max-w-[1200px] px-4 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pt-16">
        <div class="relative flex min-h-screen gap-6">

        {{-- Sidebar --}}
        <aside class="hidden w-[220px] shrink-0 flex-col rounded-2xl bg-card px-6 pb-8 pt-8 lg:flex lg:sticky lg:top-4 lg:h-[calc(100vh-2rem)] lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto">
            <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                <x-revisemy-logo variant="wordmark" size="lg" />
            </a>

            <x-home-nav class="mt-12 flex-1" />

            <p class="mt-auto pt-8 font-mono text-[11px] text-zinc-400">v{{ config('revisemy.version') }}</p>
        </aside>

        {{-- Main --}}
        <main id="top" class="min-w-0 flex-1 lg:pt-10 [--rm-pad:1.25rem] sm:[--rm-pad:2rem] lg:[--rm-pad:3rem]">
            {{-- Phone top bar: logo, then Connect once the page's own has scrolled
                 away, then the menu. The wordmark slides under the icon to make room. --}}
            <div class="sticky top-0 z-40 flex items-center justify-between gap-3 bg-canvas/85 px-[var(--rm-pad)] py-3 backdrop-blur-md lg:hidden">
                <a href="/" class="inline-flex min-w-0 shrink items-center hover:opacity-90" aria-label="ReviseMy home">
                    <span class="inline-flex items-center">
                        <img
                            src="{{ \App\Support\BrandAssets::appIconUrl() }}"
                            alt=""
                            width="40"
                            height="40"
                            class="relative z-10 block size-10 shrink-0"
                            decoding="async"
                        />
                        <span
                            class="max-w-[7.5rem] overflow-hidden whitespace-nowrap pl-2.5 text-lg font-semibold tracking-tight text-zinc-900 opacity-100 transition-[max-width,opacity,transform,padding] duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                            x-bind:class="showHeaderCta && '!max-w-0 !-translate-x-2 !pl-0 !opacity-0'"
                            aria-hidden="true"
                        >ReviseMy</span>
                    </span>
                </a>
                <div class="flex shrink-0 items-center gap-2">
                    @if ($cta)
                        <div
                            x-show="showHeaderCta"
                            x-cloak
                            x-transition:enter="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                            x-transition:enter-start="translate-x-2 opacity-0"
                            x-transition:enter-end="translate-x-0 opacity-100"
                            x-transition:leave="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                            x-transition:leave-start="translate-x-0 opacity-100"
                            x-transition:leave-end="translate-x-2 opacity-0"
                        >
                            <x-try-token-cta :href="$ctaHref" :fathom-event="$headerEvent ?? $fathom.' header'" />
                        </div>
                    @endif
                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-md text-zinc-800 transition hover:bg-zinc-100 active:scale-[0.97]"
                        x-on:click="mobileNav = ! mobileNav"
                        x-bind:aria-expanded="mobileNav.toString()"
                        x-bind:aria-label="mobileNav ? 'Close menu' : 'Open menu'"
                        aria-controls="rm-mobile-nav"
                    >
                        <span class="relative flex h-[14px] w-[18px] flex-col justify-between" aria-hidden="true">
                            <span
                                class="block h-[1.5px] w-full origin-center rounded-full bg-current transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                                x-bind:class="mobileNav && 'translate-y-[6.25px] rotate-45'"
                            ></span>
                            <span
                                class="block h-[1.5px] w-full origin-center rounded-full bg-current transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                                x-bind:class="mobileNav && '-translate-y-[6.25px] -rotate-45'"
                            ></span>
                        </span>
                    </button>
                </div>
            </div>

            <div class="relative">
                {{-- Desktop: Connect follows you down until the closing call to action. --}}
                @if ($cta)
                    <div class="pointer-events-none sticky top-10 z-30 hidden h-0 lg:block">
                        <div
                            class="flex justify-end px-[var(--rm-pad)]"
                            x-show="! atEnd"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-end="opacity-0"
                        >
                            <div class="pointer-events-auto flex items-center gap-2">
                                <x-your-reviews-button />
                                <x-try-token-cta :href="$ctaHref" :fathom-event="$stickyEvent ?? $fathom.' sticky'" />
                            </div>
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </div>

            <x-site-footer />
        </main>
        </div>
    </div>

    {{-- Phone nav drawer --}}
    <div class="lg:hidden" x-cloak>
        <div
            class="fixed inset-0 z-50 bg-black/25 backdrop-blur-[2px]"
            x-show="mobileNav"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="mobileNav = false"
            aria-hidden="true"
        ></div>
        <aside
            id="rm-mobile-nav"
            class="fixed inset-y-0 right-0 z-50 flex w-[min(100%,20rem)] flex-col bg-card shadow-xl"
            role="dialog"
            aria-modal="true"
            aria-label="Site menu"
            x-show="mobileNav"
            x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <a href="/" class="inline-flex items-center hover:opacity-90" aria-label="ReviseMy home" x-on:click="mobileNav = false">
                    <x-revisemy-logo variant="wordmark" size="lg" />
                </a>
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-md text-zinc-800 transition hover:bg-zinc-100 active:scale-[0.97]"
                    x-on:click="mobileNav = false"
                    aria-label="Close menu"
                >
                    <span class="relative flex h-[14px] w-[18px]" aria-hidden="true">
                        <span class="absolute left-0 top-[6.25px] block h-[1.5px] w-full rotate-45 rounded-full bg-current"></span>
                        <span class="absolute left-0 top-[6.25px] block h-[1.5px] w-full -rotate-45 rounded-full bg-current"></span>
                    </span>
                </button>
            </div>

            <x-home-nav drawer class="flex-1 overflow-y-auto px-5 py-8" />

            <p class="px-5 py-5 font-mono text-[11px] text-zinc-400">v{{ config('revisemy.version') }}</p>
        </aside>
    </div>
</div>
