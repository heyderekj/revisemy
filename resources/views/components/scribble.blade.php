{{--
    A hand-drawn note: a few words in a marker hand (Caveat) and an arrow drawn
    the way a person would, pointing at the part of the page it's about. Koati's
    way of pointing things out, carried over. Decorative: it sits on top of the
    UI and says nothing the page doesn't already say.
--}}
@props([
    'text',
    /** Which way the arrow points, and so which side of the words it sits on. */
    'arrow' => 'down-left',
    /** Tilt, in degrees. A note is never quite straight. */
    'tilt' => -3,
])

@php
    // Each arrow is a shaft with a little wobble and a two-stroke head, drawn
    // once so they all share one hand.
    $arrows = [
        'down-left' => ['0 0 64 48', 'M58 5 C 44 7, 24 16, 13 38 M13 38 L 10 27 M13 38 L 23 34'],
        'down-right' => ['0 0 64 48', 'M6 5 C 20 7, 40 16, 51 38 M51 38 L 54 27 M51 38 L 41 34'],
        'up' => ['0 0 40 48', 'M21 44 C 18 32, 23 18, 20 5 M20 5 L 13 13 M20 5 L 27 12'],
        'up-left' => ['0 0 56 48', 'M50 43 C 38 38, 20 28, 10 8 M10 8 L 9 19 M10 8 L 19 12'],
        'left' => ['0 0 72 36', 'M68 20 C 50 8, 26 30, 6 17 M6 17 L 15 12 M6 17 L 13 25'],
        'right' => ['0 0 84 40', 'M4 24 C 24 8, 54 34, 78 17 M78 17 L 68 14 M78 17 L 71 26'],
    ];
    $drawn = $arrows[$arrow] ?? null;
@endphp

<span {{ $attributes->class(['rm-scribble', "is-{$arrow}"]) }} style="--tilt: {{ (float) $tilt }}deg" aria-hidden="true">
    @if ($drawn)
        <svg class="rm-scribble-arrow" viewBox="{{ $drawn[0] }}" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $drawn[1] }}" /></svg>
    @endif
    <span>{{ $text }}</span>
</span>