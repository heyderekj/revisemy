@props([
    'href' => '/#setup',
    'fathomEvent' => 'Try token',
    'label' => 'Try with your agent',
])

<flux:button
    variant="primary"
    size="sm"
    icon="cursor-arrow-rays"
    href="{{ $href }}"
    onclick="if(window.fathom)fathom.trackEvent(@js($fathomEvent))"
    {{ $attributes }}
>
    {{ $label }}
</flux:button>
