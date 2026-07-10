@props([
    'size' => 'sm', // sm | md
])

@php
    [$box, $type] = match ($size) {
        'md' => ['h-6 w-6 rounded-md', 'text-[1.85rem] left-[-0.06em] top-[-0.42em]'],
        default => ['h-4 w-4 rounded-[3px]', 'text-[1.25rem] left-[-0.05em] top-[-0.38em]'],
    };
@endphp

<span
    {{ $attributes->class(["relative inline-flex shrink-0 overflow-hidden bg-rose-500 $box"]) }}
    aria-hidden="true"
    title="ReviseMy"
>
    {{-- Clipped square of the Caveat wordmark — R fills the frame --}}
    <span class="font-mark absolute leading-none text-white {{ $type }}">ReviseMy</span>
</span>
