@props(['mark'])

@php
    $focus = \App\Support\MarkFocus::forMark($mark);
    $overlay = $focus['overlay'];
    $point = $focus['point'];
    $ratio = max($focus['ratio'], 0.01);
@endphp

{{--
  Full image width, vertical band only. Outer max-height may clip; inner keeps
  true crop aspect so the capture never stretches and never loses horizontal content.
--}}
<div class="w-full max-h-[min(48dvh,28rem)] overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 lg:max-h-[min(62dvh,36rem)]">
    <div
        class="relative w-full bg-zinc-100 bg-no-repeat"
        style="aspect-ratio: {{ $ratio }}; {{ $focus['bg_style'] }}"
        role="img"
        aria-label="Cropped screenshot focused on mark M{{ $mark->number }}"
    >
        @if ($overlay)
            <div
                class="pointer-events-none absolute rounded-md border-2 border-rose-500 bg-rose-500/15"
                style="left: {{ $overlay['x'] * 100 }}%; top: {{ $overlay['y'] * 100 }}%; width: {{ $overlay['w'] * 100 }}%; height: {{ $overlay['h'] * 100 }}%;"
            ></div>
        @elseif ($point)
            <span
                class="pointer-events-none absolute flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white shadow ring-2 ring-white {{ $mark->markerClass() }}"
                style="left: {{ $point['x'] * 100 }}%; top: {{ $point['y'] * 100 }}%;"
            >M{{ $mark->number }}</span>
        @endif
    </div>
</div>
