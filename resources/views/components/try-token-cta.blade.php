@props([
    'href' => '/connect',
    'fathomEvent' => 'Try token',
    'label' => 'Connect your agent',
    'variant' => 'primary',
])

<flux:button
    :variant="$variant"
    size="sm"
    icon="cursor-arrow-rays"
    href="{{ $href }}"
    onclick="if(window.fathom)fathom.trackEvent({{ \Illuminate\Support\Js::from($fathomEvent) }})"
    {{ $attributes }}
>
    {{ $label }}
</flux:button>
