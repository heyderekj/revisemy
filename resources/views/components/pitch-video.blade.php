{{-- The one-minute tour: a thumbnail that opens a lightbox and plays with
     sound. There's a light and a dark cut; the lightbox plays whichever
     matches the page (Flux puts .dark on <html>) at the moment it opens.
     Sources live in public/videos; the video project is marketing/video. --}}
@props([
    'duration' => '1:21',
    'fathomEvent' => 'Pitch video',
])

@php
    $videos = [
        'light' => asset('videos/revisemy-pitch-light.mp4'),
        'dark' => asset('videos/revisemy-pitch-dark.mp4'),
    ];
    $posters = [
        'light' => asset('images/pitch/poster-light.webp'),
        'dark' => asset('images/pitch/poster-dark.webp'),
    ];
@endphp

<div
    x-data="{
        open: false,
        src: '',
        poster: '',
        show() {
            const mode = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            this.src = {{ \Illuminate\Support\Js::from($videos) }}[mode];
            this.poster = {{ \Illuminate\Support\Js::from($posters) }}[mode];
            this.open = true;
            if (window.fathom) fathom.trackEvent({{ \Illuminate\Support\Js::from($fathomEvent) }});
            this.$nextTick(() => {
                const v = this.$refs.video;
                v.load();
                v.play().catch(() => {});
            });
        },
        hide() {
            const v = this.$refs.video;
            v.pause();
            this.open = false;
        },
    }"
    x-on:keydown.escape.window="open && hide()"
    {{ $attributes->class('inline-block') }}
>
    <button
        type="button"
        x-on:click="show()"
        class="group flex items-center gap-3 rounded-2xl bg-card p-1.5 pr-4 text-left transition hover:bg-chip active:scale-[0.98]"
        aria-haspopup="dialog"
    >
        <span class="relative block aspect-video w-28 shrink-0 overflow-hidden rounded-xl ring-1 ring-black/[0.06] sm:w-32">
            <img src="{{ $posters['light'] }}" alt="" width="1280" height="720" loading="lazy" decoding="async" class="size-full object-cover night:hidden">
            <img src="{{ $posters['dark'] }}" alt="" width="1280" height="720" loading="lazy" decoding="async" class="hidden size-full object-cover night:block">
            <span class="absolute inset-0 flex items-center justify-center bg-black/10 transition group-hover:bg-black/0">
                <span class="flex size-9 items-center justify-center rounded-full bg-accent text-accent-foreground shadow-md transition group-hover:scale-110">
                    <svg viewBox="0 0 16 16" class="ml-0.5 size-3.5" fill="currentColor" aria-hidden="true"><path d="M4 2.8v10.4a.8.8 0 0 0 1.2.7l8.4-5.2a.8.8 0 0 0 0-1.4L5.2 2.1A.8.8 0 0 0 4 2.8Z" /></svg>
                </span>
            </span>
        </span>
        <span class="min-w-0">
            <span class="block text-sm font-semibold text-zinc-900">Watch the tour</span>
            <span class="block text-sm text-zinc-500">See a review go from marks to verified · <span class="tabular-nums">{{ $duration }}</span></span>
        </span>
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-8"
            role="dialog"
            aria-modal="true"
            aria-label="ReviseMy tour video"
        >
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:click="hide()"
                class="absolute inset-0 bg-black/75 backdrop-blur-sm"
                aria-hidden="true"
            ></div>

            <div
                x-show="open"
                x-trap.noscroll="open"
                x-transition:enter="transition duration-300 ease-[cubic-bezier(0.23,1,0.32,1)]"
                x-transition:enter-start="opacity-0 scale-[0.96]"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition duration-150 ease-out"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-[0.96]"
                class="relative w-full max-w-6xl"
            >
                <button
                    type="button"
                    x-on:click="hide()"
                    class="absolute -top-11 right-0 inline-flex size-9 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 active:scale-[0.97]"
                    aria-label="Close video"
                >
                    <span class="relative flex h-[14px] w-[14px]" aria-hidden="true">
                        <span class="absolute left-0 top-[6.25px] block h-[1.5px] w-full rotate-45 rounded-full bg-current"></span>
                        <span class="absolute left-0 top-[6.25px] block h-[1.5px] w-full -rotate-45 rounded-full bg-current"></span>
                    </span>
                </button>
                <video
                    x-ref="video"
                    x-bind:src="src"
                    x-bind:poster="poster"
                    class="aspect-video w-full rounded-2xl bg-black shadow-2xl"
                    controls
                    playsinline
                    preload="none"
                ></video>
            </div>
        </div>
    </template>
</div>
