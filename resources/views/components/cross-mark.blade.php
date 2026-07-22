@props([
    'left' => '50%',
    'top' => '50%',
    // Which breakpoints the mark shows at. Passed as a prop rather than a class so
    // it replaces the default outright — merging via $attributes->class() would
    // concatenate conflicting breakpoints and the wider one would win.
    'visibility' => 'block',
])

@php
    // Absolute coords are the padding edge; 1px borders sit half outside that edge.
    // Nudge edge-aligned marks onto the hairline so the + sits on the intersection.
    $crossOffset = function (string $value): string {
        $v = trim($value);
        if ($v === '0' || $v === '0%') {
            return '-0.5px';
        }
        if ($v === '100%' || $v === '100') {
            return 'calc(100% + 0.5px)';
        }
        // Pixel edges that land on a border-box outer edge (e.g. sidebar width).
        if (preg_match('/^(\d+(?:\.\d+)?)px$/', $v, $m)) {
            return 'calc('.$m[1].'px - 0.5px)';
        }

        return $v;
    };
    $leftPos = $crossOffset($left);
    $topPos = $crossOffset($top);
@endphp

{{-- Grid intersection crosshair. Parent must be `relative`. --}}
<svg
    aria-hidden="true"
    viewBox="0 0 16 16"
    {{ $attributes->class(['pointer-events-none absolute z-10 h-4 w-4 -translate-x-1/2 -translate-y-1/2 text-[var(--color-border-strong)]', $visibility]) }}
    style="left: {{ $leftPos }}; top: {{ $topPos }};"
>
    <path d="M8 1v14M1 8h14" stroke="currentColor" stroke-width="1" vector-effect="non-scaling-stroke" />
</svg>
