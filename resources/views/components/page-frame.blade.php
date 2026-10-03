{{-- The column secondary pages sit in. --rm-pad is the column's side padding,
     which <x-home-section> reads. --}}
@props([
    'wide' => false,
    /** Extra-wide frame for split checkout (left summary / right payment). */
    'checkout' => false,
    /** Site footer under the rails. Checkout/upgrade omit it. */
    'footer' => true,
])

<div {{ $attributes->class('relative min-h-screen') }}>
    <div @class([
        'relative z-10 mx-auto px-4 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pt-16',
        'max-w-[720px]' => ! $wide && ! $checkout,
        'max-w-[900px]' => $wide && ! $checkout,
        'max-w-[1200px]' => $checkout,
    ])>
        <div class="relative min-h-screen [--rm-pad:1.25rem] sm:[--rm-pad:2rem]">

            {{ $slot }}

            @if ($footer)
                <x-site-footer />
            @endif
        </div>
    </div>
</div>
