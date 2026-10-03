{{-- A value to copy: an address, a command, a prompt. Copying it tells the
     connect hub to start listening for the assistant's first call. --}}
@props([
    'value',
    'label' => null,
    'mono' => true,
])

<div {{ $attributes->class('min-w-0') }} x-data="{ copied: false }">
    @if ($label)
        <p class="mb-1.5 text-sm font-medium text-zinc-700">{{ $label }}</p>
    @endif
    <div class="flex items-start gap-2 rounded-xl bg-well py-2 pl-3 pr-2">
        <pre @class([
            'min-w-0 flex-1 overflow-x-auto whitespace-pre-wrap [overflow-wrap:anywhere] py-1 text-[13px] leading-relaxed text-zinc-800',
            'font-mono' => $mono,
            'font-sans' => ! $mono,
        ]) x-ref="value">{{ $value }}</pre>
        <button
            type="button"
            class="inline-flex h-7 shrink-0 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover"
            x-on:click="navigator.clipboard.writeText($refs.value.textContent); copied = true; setTimeout(() => copied = false, 1600); $dispatch('rm-connect-started')"
            x-text="copied ? 'Copied' : 'Copy'"
        >Copy</button>
    </div>
</div>
