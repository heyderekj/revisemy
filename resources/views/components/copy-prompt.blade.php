@props([
    'label' => 'Starter prompt',
    'text',
    'multiline' => false,
    'step' => null,
])

@php
    $ref = 'prompt'.preg_replace('/\W/', '', uniqid('r', true));
    $stepNumber = $step !== null && $step !== '' ? (int) $step : null;
@endphp

<div {{ $attributes->class($stepNumber ? 'flex gap-3 sm:gap-4' : '') }}>
    @if ($stepNumber)
        <span class="mt-3.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-[11px] font-semibold tabular-nums text-zinc-600" aria-hidden="true">
            {{ $stepNumber }}
        </span>
    @endif

    <div @class([
        'min-w-0 rounded-xl border border-zinc-200 bg-white p-4',
        'flex-1' => $stepNumber !== null,
    ])>
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-sm font-medium text-zinc-700">
                @if ($stepNumber)
                    <span class="sr-only">Step {{ $stepNumber }}. </span>
                @endif
                {{ $label }}
            </p>
            <button
                type="button"
                class="shrink-0 text-sm text-rose-600 hover:text-rose-500"
                x-data
                x-on:click="navigator.clipboard.writeText($refs.{{ $ref }}.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
            >Copy</button>
        </div>
        @if ($multiline)
            <pre x-ref="{{ $ref }}" class="max-h-72 overflow-auto whitespace-pre-wrap font-sans text-[14px] leading-relaxed text-zinc-600">{{ $text }}</pre>
        @else
            <p x-ref="{{ $ref }}" class="text-[15px] leading-relaxed text-zinc-600">{{ $text }}</p>
        @endif
    </div>
</div>
