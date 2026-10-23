<?php

namespace App\Services;

use App\Models\Annotation;
use App\Support\MarkFocus;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * A picture of one mark for the agent to look at: the band of the screenshot
 * the board's focus preview shows (MarkFocus), with the mark drawn on it in
 * the key yellow, at most 1024px on the long edge.
 *
 * Like ScreenshotStorage::makeThumbnail(), it never decodes a source over
 * 4MP in GD. A bigger capture is cropped from its slices (the whole page at
 * no more than 1x, in pieces GD can read), joining up to MAX_SLICES_JOINED of
 * them when the band crosses a seam. Shots from before slices fall back to
 * the vision copy, which covers only the top of the page; below that there's
 * no picture, and the caller says so.
 */
class MarkCrop
{
    public const MAX_DECODE_PIXELS = 4_000_000;

    public const MAX_SLICES_JOINED = 3;

    public const LONG_EDGE = 1024;

    public const QUALITY = 78;

    /**
     * @return array{jpeg: string, width: int, height: int}|null
     */
    public function for(Annotation $mark): ?array
    {
        $shot = $mark->screenshot;

        if (! $shot) {
            return null;
        }

        $focus = MarkFocus::forMark($mark);
        $disk = Storage::disk($shot->disk ?: ScreenshotStorage::diskName());

        $source = $this->fromShot($shot->path, (int) $shot->width * (int) $shot->height, $disk)
            ?? $this->fromSlices((array) ($shot->meta['slices'] ?? []), $focus['window'], $disk)
            ?? $this->fromVision($shot->meta ?? [], $focus['window'], $disk);

        if ($source === null) {
            return null;
        }

        [$image, $from, $span] = $source;

        return $this->render($image, $focus, $from, $span);
    }

    /**
     * The shot itself, when it is small enough to decode.
     *
     * @return array{0: GdImage, 1: float, 2: float}|null
     */
    protected function fromShot(?string $path, int $pixels, Filesystem $disk): ?array
    {
        if (! is_string($path) || $pixels <= 0 || $pixels > self::MAX_DECODE_PIXELS) {
            return null;
        }

        $image = $this->decode($disk, $path);

        return $image ? [$image, 0.0, 1.0] : null;
    }

    /**
     * The slices the band falls in, joined top to bottom.
     *
     * @param  array<int, mixed>  $slices
     * @param  array{x: float, y: float, w: float, h: float}  $window
     * @return array{0: GdImage, 1: float, 2: float}|null
     */
    protected function fromSlices(array $slices, array $window, Filesystem $disk): ?array
    {
        $bottom = $window['y'] + $window['h'];
        $picked = [];

        foreach ($slices as $slice) {
            if (! is_string($slice['path'] ?? null) || ! is_numeric($slice['top'] ?? null) || ! is_numeric($slice['height'] ?? null)) {
                continue;
            }

            $top = (float) $slice['top'];
            $end = $top + (float) $slice['height'];

            // The first slice holds the band's top; the rest follow on from it.
            if ($picked === [] ? ($window['y'] >= $top && $window['y'] < $end) : $top < $bottom) {
                $picked[] = ['path' => $slice['path'], 'top' => $top, 'height' => (float) $slice['height']];
            }

            if ($picked !== [] && ($end >= $bottom || count($picked) >= self::MAX_SLICES_JOINED)) {
                break;
            }
        }

        if ($picked === []) {
            return null;
        }

        $images = [];

        foreach ($picked as $slice) {
            $image = $this->decode($disk, $slice['path']);

            if (! $image) {
                break;
            }

            $images[] = $image;
        }

        if ($images === []) {
            return null;
        }

        $picked = array_slice($picked, 0, count($images));
        $span = array_sum(array_column($picked, 'height'));

        if (count($images) === 1) {
            return [$images[0], $picked[0]['top'], $span];
        }

        // Slices share one width; stack them into a single source.
        $width = imagesx($images[0]);
        $joined = imagecreatetruecolor($width, array_sum(array_map(fn (GdImage $image) => imagesy($image), $images)));
        $y = 0;

        foreach ($images as $image) {
            imagecopy($joined, $image, 0, $y, 0, 0, min($width, imagesx($image)), imagesy($image));
            $y += imagesy($image);
            imagedestroy($image);
        }

        return [$joined, $picked[0]['top'], $span];
    }

    /**
     * The vision copy, for shots from before slices: it covers the top
     * `vision_crop` of the page.
     *
     * @param  array<string, mixed>  $meta
     * @param  array{x: float, y: float, w: float, h: float}  $window
     * @return array{0: GdImage, 1: float, 2: float}|null
     */
    protected function fromVision(array $meta, array $window, Filesystem $disk): ?array
    {
        $path = $meta['vision_path'] ?? null;
        $coverage = (float) ($meta['vision_crop'] ?? 0);

        if (! is_string($path) || $coverage <= 0 || $window['y'] >= $coverage) {
            return null;
        }

        $image = $this->decode($disk, $path);

        return $image ? [$image, 0.0, $coverage] : null;
    }

    /**
     * One stored image, or null when it's missing, over MAX_DECODE_PIXELS
     * or in a format this GD can't read.
     */
    public function decode(Filesystem $disk, string $path): ?GdImage
    {
        $binary = rescue(fn () => $disk->get($path), report: false);

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $size = @getimagesizefromstring($binary);

        if (! is_array($size)
            || (($size[0] ?? 0) * ($size[1] ?? 0)) > self::MAX_DECODE_PIXELS
            || ! ScreenshotStorage::gdReads($size['mime'] ?? null)) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        return $image === false ? null : $image;
    }

    /**
     * The source holds the page from $from down to $from + $span, as shares
     * of the shot's height.
     *
     * @param  array{window: array{x: float, y: float, w: float, h: float}, overlay: ?array{x: float, y: float, w: float, h: float}, point: ?array{x: float, y: float}}  $focus
     * @return array{jpeg: string, width: int, height: int}|null
     */
    protected function render(GdImage $source, array $focus, float $from, float $span): ?array
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $window = $focus['window'];
        $span = max($span, 0.0001);

        // Page fractions → source pixels, clipped to what the source covers.
        $top = max(0, (int) floor((($window['y'] - $from) / $span) * $height));
        $bottom = (int) ceil(min(1.0, ($window['y'] + $window['h'] - $from) / $span) * $height);
        $left = (int) floor($window['x'] * $width);
        $cropW = max(1, (int) ceil($window['w'] * $width));
        $cropH = max(1, min($height, $bottom) - $top);

        $flat = imagecreatetruecolor($cropW, $cropH);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $source, 0, 0, $left, $top, $cropW, $cropH);

        // Band-local fractions were worked out for the whole band; when the
        // source cut it short, rescale them to the part we kept.
        $kept = $cropH / max(1, ($window['h'] / $span) * $height);
        $this->drawMark($flat, $focus, $cropW, $cropH, $kept);

        $scale = min(1.0, self::LONG_EDGE / max($cropW, $cropH));
        $out = $scale < 1.0 ? imagescale($flat, max(1, (int) round($cropW * $scale)), max(1, (int) round($cropH * $scale))) : $flat;

        if ($out === false) {
            return null;
        }

        ob_start();
        imagejpeg($out, null, self::QUALITY);
        $jpeg = (string) ob_get_clean();

        return $jpeg === '' ? null : ['jpeg' => $jpeg, 'width' => imagesx($out), 'height' => imagesy($out)];
    }

    /**
     * The mark as the person drew it: their box, or a dot where they clicked.
     *
     * @param  array{overlay: ?array{x: float, y: float, w: float, h: float}, point: ?array{x: float, y: float}}  $focus
     */
    protected function drawMark(GdImage $image, array $focus, int $width, int $height, float $kept): void
    {
        $key = imagecolorallocate($image, 0xFF, 0xC5, 0x3D);
        $ink = imagecolorallocate($image, 0x14, 0x13, 0x12);
        $stroke = max(3, (int) round(min($width, $height) / 200));
        $y = fn (float $fraction) => (int) round(($fraction / max($kept, 0.0001)) * $height);

        if ($focus['overlay']) {
            $o = $focus['overlay'];
            $x1 = (int) round($o['x'] * $width);
            $y1 = $y($o['y']);
            $x2 = (int) round(($o['x'] + $o['w']) * $width);
            $y2 = $y($o['y'] + $o['h']);
            $edge = intdiv($stroke, 2) + 1;

            // Yellow on a light page is faint: a dark hairline either side keeps it readable.
            imagesetthickness($image, 1);
            imagerectangle($image, $x1 - $edge, $y1 - $edge, $x2 + $edge, $y2 + $edge, $ink);
            imagerectangle($image, $x1 + $edge, $y1 + $edge, $x2 - $edge, $y2 - $edge, $ink);
            imagesetthickness($image, $stroke);
            imagerectangle($image, $x1, $y1, $x2, $y2, $key);
            imagesetthickness($image, 1);

            return;
        }

        if ($focus['point']) {
            $cx = (int) round($focus['point']['x'] * $width);
            $cy = $y($focus['point']['y']);
            $r = max(10, $stroke * 4);
            imagefilledellipse($image, $cx, $cy, ($r + $stroke) * 2, ($r + $stroke) * 2, $ink);
            imagefilledellipse($image, $cx, $cy, $r * 2, $r * 2, $key);
        }
    }
}
