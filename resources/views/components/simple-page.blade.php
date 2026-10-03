{{-- A plain page: logo, a heading, and the words. Legal and billing. --}}
@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'heading',
    'updated' => null,
    'robots' => 'index, follow',
    'footer' => true,
])

<x-layouts.app :title="$title" :description="$description ?? $heading" :robots="$robots" schema="page">
    <x-page-frame :footer="$footer">
        <x-home-section first>
            <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                <x-revisemy-logo variant="wordmark" size="lg" />
            </a>
            <div class="mt-10 sm:mt-12">
                @if ($eyebrow)
                    <p class="text-sm font-medium text-muted-foreground">{{ $eyebrow }}</p>
                @endif
                <h1 class="mt-2 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">{{ $heading }}</h1>
                @if ($updated)
                    <p class="mt-3 text-sm text-muted-foreground">Last updated {{ $updated }}</p>
                @endif
                <div class="mt-6 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    {{ $slot }}
                </div>
            </div>
        </x-home-section>
    </x-page-frame>
</x-layouts.app>
