@props([
    'href' => '/#setup',
    'fathomEvent' => 'Try token',
    'label' => 'Try with your agent',
])

<a
    href="{{ $href }}"
    onclick="if(window.fathom)fathom.trackEvent(@js($fathomEvent))"
    {{ $attributes->class('group inline-flex shrink-0 items-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-medium text-white shadow-[0_10px_24px_-12px_rgba(225,29,72,0.85)] transition hover:bg-rose-500') }}
>
    <flux:icon.cursor-arrow-rays variant="micro" class="size-4 transition group-hover:scale-110" />
    {{ $label }}
</a>
