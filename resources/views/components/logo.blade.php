@props([
    'size' => 'md', // sm | md | lg
])

@php
    $sizeClass = match ($size) {
        'sm' => 'text-[1.75rem]',
        'lg' => 'text-[2.75rem]',
        default => 'text-[2.35rem]',
    };
@endphp

<span {{ $attributes->class(["font-mark leading-none tracking-tight text-rose-500 inline-flex items-baseline $sizeClass"]) }}>
    <span>Rev</span>
    <span class="relative inline-block">
        <span>i</span>
        {{-- Hollow markup-pen tittle covering the font’s solid dot --}}
        <span
            aria-hidden="true"
            class="pointer-events-none absolute left-1/2 top-[0.08em] z-10 block h-[0.32em] w-[0.32em] -translate-x-1/2 rounded-full border-[1.75px] border-current bg-[var(--color-paper,#f7f7f8)]"
        ></span>
    </span>
    <span>se</span>
    <span class="relative inline-block">
        <span>M</span>
        {{-- Squiggle under M (sketch-style) --}}
        <svg
            aria-hidden="true"
            class="pointer-events-none absolute left-[-0.2em] top-[0.95em] h-[0.42em] w-[1.7em] overflow-visible"
            viewBox="0 0 72 16"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            {{-- Z-squiggle then long stroke under M, matching the sketch --}}
            <path
                d="M1.5 11.5 L8 3.5 L14.5 12.5 L21 4 L27.5 10.5 C38 13.2 52 12.4 70 9.2"
                stroke="currentColor"
                stroke-width="2.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
    </span>
    <span>y</span>
</span>
