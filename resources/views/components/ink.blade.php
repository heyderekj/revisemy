{{--
    A few words marked by hand: circled or underlined, the way you'd mark up a
    printout before handing it back. The sibling of x-scribble: that one adds a
    note beside the UI, this one marks words already there.

    The stroke draws itself in the first time the words scroll into view, and is
    simply there without script or under reduced motion. Decorative: the words
    read the same without it.
--}}
@props([
    'kind' => 'underline',
])

@php
    $paths = [
        'circle' => ['0 0 100 40', 'M9 21 C 6 8, 38 3, 62 4 C 88 5, 98 13, 96 23 C 93 35, 62 39, 38 37 C 14 35, 1 28, 9 15 C 13 9, 21 6, 31 4'],
        'underline' => ['0 0 100 10', 'M2 6 C 22 3, 48 8, 70 5 C 82 4, 92 5, 98 4'],
    ];
    [$box, $path] = $paths[$kind] ?? $paths['underline'];
@endphp

<span
    x-data
    x-init="$el.setAttribute('data-pending', '')"
    x-intersect.once.margin.-15%="$el.removeAttribute('data-pending')"
    {{ $attributes->class(['rm-ink', "is-{$kind}"]) }}
>{{ $slot }}<svg class="rm-ink-stroke" viewBox="{{ $box }}" preserveAspectRatio="none" fill="none" aria-hidden="true"><path d="{{ $path }}" pathLength="1" vector-effect="non-scaling-stroke" /></svg></span>