@props(['mark'])

@php
    $source = $mark->screenshot;
    $after = $mark->afterScreenshot;
    ['window' => $window, 'ratio' => $ratio] = \App\Support\MarkFocus::forComparison($mark);
    $beforeStyle = \App\Support\MarkFocus::backgroundStyle($source->url(), $window);
@endphp

<div {{ $attributes->class('min-w-0') }} x-data="{ view: 'after' }">
    <div class="mb-2 inline-flex flex-wrap gap-0.5 rounded-xl bg-well p-0.5" role="group" aria-label="Compare the fix">
        @foreach (['before' => 'Before', 'after' => 'After', 'both' => 'Side by side'] as $mode => $label)
            <button type="button"
                class="h-8 rounded-lg px-3 text-xs font-medium transition-colors"
                x-bind:class="view === '{{ $mode }}' ? 'bg-raised text-zinc-900 shadow-xs' : 'text-muted-foreground hover:text-zinc-900'"
                x-bind:aria-pressed="(view === '{{ $mode }}').toString()"
                x-on:click="view = '{{ $mode }}'">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-2" x-bind:class="view === 'both' ? 'grid-cols-2' : 'grid-cols-1'">
        <figure x-show="view !== 'after'" x-cloak>
            <figcaption class="mb-1 text-xs text-muted-foreground" x-show="view === 'both'">Before</figcaption>
            <a href="{{ $source->url() }}" target="_blank" rel="noopener" aria-label="Open the original screenshot"
                class="mx-auto block w-full overflow-hidden rounded-xl border border-border bg-well"
                style="max-width: calc(min(42dvh, 26rem) * {{ $ratio }});">
                <span role="img" aria-label="Before — marked area"
                    class="block w-full bg-no-repeat" style="aspect-ratio: {{ $ratio }}; {{ $beforeStyle }}"></span>
            </a>
        </figure>
        <figure x-show="view !== 'before'">
            <figcaption class="mb-1 text-xs text-muted-foreground" x-show="view === 'both'" x-cloak>After</figcaption>
            <a href="{{ $after->url() }}" target="_blank" rel="noopener" aria-label="Open the after screenshot"
                class="mx-auto block w-full overflow-hidden rounded-xl border border-border bg-well"
                style="max-width: calc(min(42dvh, 26rem) * {{ $ratio }});">
                <img src="{{ $after->url() }}" alt="After — submitted screenshot of the fix"
                    class="block w-full object-contain" style="aspect-ratio: {{ $ratio }};">
            </a>
        </figure>
    </div>
</div>
