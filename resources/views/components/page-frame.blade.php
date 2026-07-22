{{-- Framed secondary-page column: vertical rails, top rule, corner crosshairs.
     Set --rm-pad on the rails so <x-home-section> rules bleed edge-to-edge. --}}
@props([
    'wide' => false,
    /** Extra-wide frame for split checkout (left summary / right payment). */
    'checkout' => false,
    /** Site footer under the rails. Checkout/upgrade omit it. */
    'footer' => true,
])

<div {{ $attributes->class('rm-wash relative min-h-screen') }}>
    <div @class([
        'relative z-10 mx-auto px-4 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pt-16',
        'max-w-[720px]' => ! $wide && ! $checkout,
        'max-w-[900px]' => $wide && ! $checkout,
        'max-w-[1200px]' => $checkout,
    ])>
        <div class="rm-rails relative min-h-screen border-t border-zinc-200 bg-canvas/60 [--rm-pad:1.25rem] sm:[--rm-pad:2rem]">
            <x-cross-mark left="0" top="0" />
            <x-cross-mark left="100%" top="0" />

            {{ $slot }}

            @if ($footer)
                <x-site-footer />
            @endif
        </div>
    </div>
</div>
