@props([
    'number' => null,
    'label' => '',
])

<p {{ $attributes->class('mb-3 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.07em] text-muted-foreground') }}>
    @if ($number)
        <span class="text-zinc-400 tabular-nums">{{ $number }}</span>
        <span class="text-border-strong" aria-hidden="true">/</span>
    @endif
    <span>{{ $label }}</span>
</p>
