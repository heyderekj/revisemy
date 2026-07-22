@props([
    'type' => 'm',
])

@php
$type = strtolower($type);

$styles = [
    'm' => [
        'wrap' => 'bg-rose-50 ring-rose-100',
        'box' => 'rounded border-2 border-rose-500/80 bg-rose-500/10',
        'badge' => 'rounded-full bg-rose-500 font-semibold text-accent-contrast',
        'label' => 'M',
    ],
    's' => [
        'wrap' => 'bg-sky-50 ring-sky-100',
        'box' => 'rounded border border-dashed border-sky-400/80 bg-sky-400/10',
        'badge' => 'rounded-full border border-dashed border-sky-500 bg-white font-semibold text-sky-700',
        'label' => 'S',
    ],
    'g' => [
        'wrap' => 'bg-zinc-50 ring-zinc-200',
        'box' => 'rounded border border-dashed border-zinc-400/80 bg-zinc-400/10',
        'badge' => 'rounded-full border border-dashed border-zinc-400 bg-white font-semibold text-zinc-700',
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
