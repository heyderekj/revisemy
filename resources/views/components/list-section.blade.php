{{-- A heading, an optional line, and a list. --}}
@props(['heading', 'items' => [], 'intro' => null])

<x-home-section {{ $attributes }}>
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">{{ $heading }}</h2>
    @if ($intro)
        <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">{{ $intro }}</p>
    @endif
    <ul class="mt-6 max-w-2xl space-y-2.5 text-[15px] leading-relaxed text-zinc-600">
        @foreach ($items as $item)
            <li class="flex gap-3"><span class="mt-2.5 size-1.5 shrink-0 rounded-full bg-zinc-300" aria-hidden="true"></span><span>{{ $item }}</span></li>
        @endforeach
    </ul>
</x-home-section>
