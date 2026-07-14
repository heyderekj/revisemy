{{-- Optional desktop/mobile product shots. Set product_shots.dir + alt on the guide page. --}}
@php
    $shots = $page['product_shots'] ?? null;
    $desktop = ['url' => null, 'width' => null, 'height' => null];
    $mobile = ['url' => null, 'width' => null, 'height' => null];

    if (is_array($shots) && ! empty($shots['dir'])) {
        $relDir = trim($shots['dir'], '/');
        $absDir = public_path($relDir);
        $pick = function (string $stem) use ($absDir, $relDir): array {
            foreach (['png', 'webp', 'jpg', 'jpeg'] as $ext) {
                $path = "{$absDir}/{$stem}.{$ext}";
                if (! is_file($path)) {
                    continue;
                }
                $size = @getimagesize($path) ?: [null, null];

                return [
                    'url' => asset("{$relDir}/{$stem}.{$ext}"),
                    'width' => $size[0],
                    'height' => $size[1],
                ];
            }

            return ['url' => null, 'width' => null, 'height' => null];
        };
        $desktop = $pick('desktop');
        $mobile = $pick('mobile');
    }
@endphp

@if ($desktop['url'] || $mobile['url'])
    <div class="rm-fade-up mt-10 sm:mt-12">
        <div class="overflow-hidden rounded-xl border border-zinc-900/10 bg-zinc-100 shadow-[0_18px_50px_-28px_rgba(24,24,27,0.45)]">
            <picture>
                @if ($desktop['url'])
                    <source
                        media="(min-width: 640px)"
                        srcset="{{ $desktop['url'] }}"
                        @if ($desktop['width']) width="{{ $desktop['width'] }}" @endif
                        @if ($desktop['height']) height="{{ $desktop['height'] }}" @endif
                    >
                @endif
                <img
                    src="{{ $mobile['url'] ?? $desktop['url'] }}"
                    @if (($mobile['width'] ?? $desktop['width'])) width="{{ $mobile['width'] ?? $desktop['width'] }}" @endif
                    @if (($mobile['height'] ?? $desktop['height'])) height="{{ $mobile['height'] ?? $desktop['height'] }}" @endif
                    alt="{{ $shots['alt'] ?? ($page['label'].' product shot') }}"
                    class="block h-auto w-full"
                    decoding="async"
                    fetchpriority="high"
                >
            </picture>
        </div>
    </div>
@endif
