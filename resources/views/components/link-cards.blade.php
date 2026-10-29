{{-- Link cards in a two-column grid, with an optional dashed card for what's
     on the way. --}}
@props(['links' => [], 'soon' => null])

<ul {{ $attributes->class('grid grid-cols-1 gap-2 min-[30rem]:grid-cols-2') }}>
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
    @if ($soon)
        <li class="flex h-full items-start gap-3 rounded-xl border border-dashed border-border-strong px-3 py-3">
            <x-use-case-icon name="sparkles" size="sm" class="mt-0.5 shrink-0" />
            <span class="min-w-0">
                <span class="block text-sm font-medium text-zinc-500">More on the way</span>
                <span class="mt-0.5 block text-sm leading-relaxed text-zinc-500">{{ $soon }}</span>
            </span>
        </li>
    @endif
</ul>
