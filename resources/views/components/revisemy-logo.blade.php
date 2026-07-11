@props([
    'variant' => 'wordmark',
    'size' => 'md',
])

@php
    [$classes, $src, $width, $height] = match ($variant) {
        'mark' => match ($size) {
            'lg' => ['h-8 w-8', '/images/app-icon.png', 32, 32],
            'sm' => ['h-4 w-4', '/images/app-icon.png', 16, 16],
            default => ['h-6 w-6', '/images/app-icon.png', 24, 24],
        },
        default => match ($size) {
            'lg' => ['h-10 w-auto', '/images/logo.svg', 272, 60],
            'sm' => ['h-6 w-auto', '/images/logo.svg', 272, 60],
            default => ['h-8 w-auto', '/images/logo.svg', 272, 60],
        },
    };
@endphp

<img
    src="{{ $src }}"
    alt="ReviseMy"
    width="{{ $width }}"
    height="{{ $height }}"
    {{ $attributes->class(["block shrink-0 $classes"]) }}
    decoding="async"
/>
