@props(['mark'])

@php
    $after = $mark->afterScreenshot;
    $source = $mark->screenshot;
    $region = $mark->region();
@endphp

@if ($after && $source)
    @php
        // CSS-crop the source screenshot to the mark's normalized region so the
        // "before" thumb shows the exact area the mark points at. Falls back to
        // the full frame for point marks (no rectangle).
        $bgStyle = '';
        $ratio = ($source->width && $source->height) ? $source->width / max($source->height, 1) : 4 / 3;

        if ($region) {
            $sizeX = 100 / max($region['w'], 0.02);
            $sizeY = 100 / max($region['h'], 0.02);
            $posX = $region['w'] < 1 ? ($region['x'] / (1 - $region['w'])) * 100 : 0;
            $posY = $region['h'] < 1 ? ($region['y'] / (1 - $region['h'])) * 100 : 0;
            $bgStyle = sprintf(
                'background-image:url(%s);background-size:%.2f%% %.2f%%;background-position:%.2f%% %.2f%%;',
                $source->url(), $sizeX, $sizeY, $posX, $posY,
            );
            $ratio = ($source->width && $source->height)
                ? ($region['w'] * $source->width) / max($region['h'] * $source->height, 1)
                : $ratio;
        } else {
            $bgStyle = sprintf('background-image:url(%s);background-size:cover;background-position:center;', $source->url());
        }
    @endphp
    <div class="mt-2 grid grid-cols-2 gap-2">
        <a href="{{ $source->url() }}" target="_blank" rel="noopener" class="group block min-w-0">
            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-zinc-400">Before</span>
            <span
                class="block w-full rounded-lg border border-zinc-200 bg-zinc-100 bg-no-repeat transition group-hover:border-zinc-300"
                style="aspect-ratio: {{ max(min($ratio, 3), 0.4) }}; {{ $bgStyle }}"
                role="img"
                aria-label="Before — marked area of the original screenshot"
            ></span>
        </a>
        <a href="{{ $after->url() }}" target="_blank" rel="noopener" class="group block min-w-0">
            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-emerald-600">After</span>
            <img
                src="{{ $after->url() }}"
                alt="After — the agent's screenshot of the fixed area"
                class="w-full rounded-lg border border-emerald-200 object-cover transition group-hover:border-emerald-300"
                style="aspect-ratio: {{ max(min($ratio, 3), 0.4) }};"
                loading="lazy"
            />
        </a>
    </div>
@endif
