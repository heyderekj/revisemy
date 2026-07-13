@props([
    'label' => 'Starter prompt',
    'text',
])

@php
    $ref = 'prompt'.preg_replace('/\W/', '', uniqid('r', true));
@endphp

<div {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-4') }}>
    <div class="mb-2 flex items-center justify-between gap-2">
        <p class="text-sm font-medium text-zinc-700">{{ $label }}</p>
        <button
            type="button"
            class="shrink-0 text-sm text-rose-600 hover:text-rose-500"
            x-data
            x-on:click="navigator.clipboard.writeText($refs.{{ $ref }}.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
        >Copy</button>
    </div>
    <p x-ref="{{ $ref }}" class="text-[15px] leading-relaxed text-zinc-600">{{ $text }}</p>
</div>
