{{-- The one call to action: go to the connect list (#setup on the homepage). --}}
@props([
    'fathomEvent' => 'Connect',
    'href' => '#setup',
])

<flux:button
    variant="primary"
    size="sm"
    icon="cursor-arrow-rays"
    href="{{ $href }}"
    onclick="if(window.fathom)fathom.trackEvent({{ \Illuminate\Support\Js::from($fathomEvent) }})"
    {{ $attributes }}
>
    Connect your agent
</flux:button>
