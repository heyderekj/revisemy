@props([
    'step',
    'label' => null,
])

@php
    $stepNumber = (int) $step;
@endphp

<div {{ $attributes->class('flex gap-3 sm:gap-4') }}>
    <span class="mt-3.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-[11px] font-semibold tabular-nums text-zinc-600" aria-hidden="true">
        {{ $stepNumber }}
    </span>

    <div class="min-w-0 flex-1 space-y-3 rounded-xl border border-zinc-200 bg-white p-4">
        @if ($label)
            <p class="text-sm font-medium text-zinc-800">
                <span class="sr-only">Step {{ $stepNumber }}. </span>
                {{ $label }}
            </p>
        @endif
        {{ $slot }}
    </div>
</div>
