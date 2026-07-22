@props([
    'number' => null,
    'label' => '',
])

<p {{ $attributes->class('mb-3 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.14em] text-soft') }}>
    @if ($number)
        <span class="text-muted tabular-nums">{{ $number }}</span>
        <span class="text-border-strong" aria-hidden="true">/</span>
    @endif
    <span>{{ $label }}</span>
</p>
