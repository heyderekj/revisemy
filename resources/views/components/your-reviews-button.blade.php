{{-- For a browser that has connected before: straight to its reviews. Renders
     nothing for a first visit, so the page stays about connecting. --}}
@if (auth('web')->user()?->workspace_id)
    <flux:button
        variant="filled"
        size="sm"
        icon="queue-list"
        href="/reviews"
        onclick="if(window.fathom)fathom.trackEvent('Your reviews')"
        {{ $attributes }}
    >
        Your reviews
    </flux:button>
@endif
