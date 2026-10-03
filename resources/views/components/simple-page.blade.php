{{-- A plain page: a heading and the words. Legal pages sit in the site shell;
     billing pages pass :shell="false" and keep the bare column, since they are
     a step in a checkout rather than somewhere to browse from. --}}
@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'heading',
    'updated' => null,
    'robots' => 'index, follow',
    'footer' => true,
    'shell' => true,
])

<x-layouts.app :title="$title" :description="$description ?? $heading" :robots="$robots" schema="page">
    @if ($shell)
        <x-site-shell>
            <x-home-section first>
                {{-- Desktop: room for the shell's sticky Connect button. --}}
                <div class="hidden h-8 lg:block" aria-hidden="true"></div>
                <div class="lg:mt-6">
                    @include('components.simple-page-body')
                </div>
            </x-home-section>
        </x-site-shell>
    @else
        <x-page-frame :footer="$footer">
            <x-home-section first>
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo variant="wordmark" size="lg" />
                </a>
                <div class="mt-10 sm:mt-12">
                    @include('components.simple-page-body')
                </div>
            </x-home-section>
        </x-page-frame>
    @endif
</x-layouts.app>
