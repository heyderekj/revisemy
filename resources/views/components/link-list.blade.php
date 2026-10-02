{{-- Where to go next: each link with an icon, its name and a line. --}}
@props(['heading', 'links' => []])

<x-home-section {{ $attributes }}>
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">{{ $heading }}</h2>
    <ul class="mt-6 grid grid-cols-1 gap-2 min-[30rem]:grid-cols-2">
        @foreach ($links as $link)
            <li>
                <a href="{{ $link['href'] }}" class="group flex h-full items-start gap-3 rounded-xl bg-card px-3 py-3 transition-colors hover:bg-chip">
                    @if (! empty($link['icon']))
                        <x-use-case-icon :name="$link['icon']" size="sm" class="mt-0.5 shrink-0" />
                    @endif
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-zinc-900">{{ $link['label'] }}</span>
                        @if (! empty($link['line']))
                            <span class="mt-0.5 block text-sm leading-relaxed text-zinc-500">{{ $link['line'] }}</span>
                        @endif
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</x-home-section>
