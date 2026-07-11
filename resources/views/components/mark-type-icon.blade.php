@props([
    'type' => 'm',
])

@php
$type = strtolower($type);

$styles = [
    'm' => [
        'wrap' => 'bg-rose-50 ring-rose-100',
        'box' => 'rounded border-2 border-rose-500/80 bg-rose-500/10',
        'badge' => 'rounded-full bg-rose-500 font-semibold text-white',
        'label' => 'M',
    ],
    's' => [
        'wrap' => 'bg-sky-50 ring-sky-100',
        'box' => 'rounded border border-dashed border-sky-400/80 bg-sky-400/10',
        'badge' => 'rounded-full border border-dashed border-sky-500 bg-white font-semibold text-sky-700',
        'label' => 'S',
    ],
    'g' => [
        'wrap' => 'bg-amber-50 ring-amber-100',
        'box' => 'rounded border border-dashed border-amber-400/80 bg-amber-400/10',
        'badge' => 'rounded-full border border-dashed border-amber-500 bg-white font-semibold text-amber-700',
        'label' => 'G',
    ],
];

$style = $styles[$type] ?? $styles['m'];
@endphp

<div {{ $attributes->class(['flex size-9 items-center justify-center rounded-lg ring-1', $style['wrap']]) }}>
    <span class="relative h-5 w-9 shrink-0 {{ $style['box'] }}" aria-hidden="true">
        <span class="absolute -left-2 -top-2 flex h-5 min-w-5 items-center justify-center text-[9px] ring-2 ring-white {{ $style['badge'] }}">{{ $style['label'] }}</span>
    </span>
</div>
