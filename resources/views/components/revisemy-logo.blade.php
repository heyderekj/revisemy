@props([
    // icon: app mark only. wordmark: mark + ReviseMy (use on pages without a sidebar brand).
    'variant' => 'icon',
    'size' => 'md',
])

@php
    // App icon is the brand mark everywhere — square yellow squircle.
    [$classes, $px, $text] = match ($size) {
        'lg' => ['h-10 w-10', 40, 'text-lg'],
        'sm' => ['h-7 w-7', 28, 'text-sm'],
        default => ['h-8 w-8', 32, 'text-base'],
    };
@endphp

@if ($variant === 'wordmark')
    <span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
        <img
            src="/images/app-icon.png"
            alt=""
            width="{{ $px }}"
            height="{{ $px }}"
            class="block shrink-0 {{ $classes }}"
            decoding="async"
        />
        <span class="{{ $text }} font-semibold tracking-tight text-zinc-900">ReviseMy</span>
    </span>
@else
    <img
        src="/images/app-icon.png"
        alt="ReviseMy"
        width="{{ $px }}"
        height="{{ $px }}"
        {{ $attributes->class(["block shrink-0 $classes"]) }}
        decoding="async"
    />
@endif
