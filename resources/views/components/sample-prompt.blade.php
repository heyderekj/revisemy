@props([
    'text',
])

{{-- Highlighted sample agent prompt with one-click copy. --}}
<div {{ $attributes->class('group inline-flex max-w-full items-center gap-1') }}>
    <span class="rm-note box-decoration-clone text-[15px] leading-relaxed text-zinc-700">
        “{{ $text }}”
    </span>
    <button
        type="button"
        class="-mr-1 inline-flex size-6 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-400"
        x-data="{ copied: false }"
        x-on:click="
            navigator.clipboard.writeText(@js($text));
            copied = true;
            setTimeout(() => copied = false, 1600);
        "
        x-bind:aria-label="copied ? 'Copied' : 'Copy prompt'"
        title="Copy prompt"
    >
        <span x-show="! copied">
            <flux:icon.clipboard-document variant="micro" class="size-3.5" />
        </span>
        <span x-show="copied" x-cloak>
            <flux:icon.check variant="micro" class="size-3.5 text-emerald-600" />
        </span>
    </button>
</div>
