<?php

namespace App\Services;

use App\Models\Annotation;
use App\Support\MarkFocus;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * A picture of one mark for the agent to look at: the band of the screenshot
 * the board's focus preview shows (MarkFocus), with the mark drawn on it in
 * the key yellow, at most 1024px on the long edge.
 *
 * Like ScreenshotStorage::makeThumbnail(), it never decodes a source over
 * 4MP in GD. A tall capture is cropped from its vision copy instead, when the
 * mark sits in the part of the page that copy covers; below that there's no
 * picture, and the caller says so.
 */
class MarkCrop
{
    public const MAX_DECODE_PIXELS = 4_000_000;

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
        $window = $focus['window'];
        $disk = Storage::disk($shot->disk ?: ScreenshotStorage::diskName());

        // The page's full height maps onto the source; for a vision copy only
        // its top `crop` fraction does.
        $coverage = 1.0;
        $binary = null;

        if (((int) $shot->width * (int) $shot->height) > 0 && ((int) $shot->width * (int) $shot->height) <= self::MAX_DECODE_PIXELS) {
            $binary = rescue(fn () => $disk->get($shot->path), report: false);
        }

        if (! is_string($binary) || $binary === '') {
            $binary = null;
            $visionPath = $shot->meta['vision_path'] ?? null;
            $coverage = (float) ($shot->meta['vision_crop'] ?? 0);

            if (! is_string($visionPath) || $coverage <= 0 || $window['y'] >= $coverage) {
                return null;
            }

            $binary = rescue(fn () => $disk->get($visionPath), report: false);
        }

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $size = @getimagesizefromstring($binary);

        if (! is_array($size) || (($size[0] ?? 0) * ($size[1] ?? 0)) > self::MAX_DECODE_PIXELS) {
            return null;
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        return $this->render($source, $focus, $coverage);
    }

    /**
     * @param  array{window: array{x: float, y: float, w: float, h: float}, overlay: ?array{x: float, y: float, w: float, h: float}, point: ?array{x: float, y: float}}  $focus
     * @return array{jpeg: string, width: int, height: int}|null
     */
    protected function render(GdImage $source, array $focus, float $coverage): ?array
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $window = $focus['window'];

        // Page fractions → source pixels, clipped to what the source covers.
        $top = (int) floor(($window['y'] / $coverage) * $height);
        $bottom = (int) ceil(min(1.0, ($window['y'] + $window['h']) / $coverage) * $height);
        $left = (int) floor($window['x'] * $width);
        $cropW = max(1, (int) ceil($window['w'] * $width));
        $cropH = max(1, min($height, $bottom) - $top);

        $flat = imagecreatetruecolor($cropW, $cropH);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $source, 0, 0, $left, $top, $cropW, $cropH);

        // Band-local fractions were worked out for the whole band; when the
        // source cut it short, rescale them to the part we kept.
        $kept = $cropH / max(1, ($window['h'] / $coverage) * $height);
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
