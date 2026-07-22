@props([
    'fathomEvent' => 'Try token',
])

<flux:button
    type="button"
    variant="primary"
    size="sm"
    icon="cursor-arrow-rays"
    wire:click="getTryToken"
    onclick="if(window.fathom)fathom.trackEvent(@js($fathomEvent))"
    {{ $attributes }}
>
    Try with your agent
</flux:button>
