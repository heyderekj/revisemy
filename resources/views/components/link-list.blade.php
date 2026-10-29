{{-- Where to go next: each link with an icon, its name and a line. --}}
@props(['heading', 'links' => [], 'intro' => null, 'soon' => null])

<x-home-section {{ $attributes }}>
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">{{ $heading }}</h2>
    @if ($intro)
        <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-zinc-600">{{ $intro }}</p>
    @endif
    <x-link-cards class="mt-6" :links="$links" :soon="$soon" />
</x-home-section>
