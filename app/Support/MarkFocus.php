<?php

namespace App\Support;

use App\Models\Annotation;
use App\Models\Screenshot;

/**
 * Board mark preview focus: always full image width, vertical crop around the mark.
 */
class MarkFocus
{
    public const REGION_PAD = 0.08;

    /** Extra vertical context for point marks (normalized), on top of the target band. */
    public const POINT_PAD = 0.02;

    /**
     * Target pixel aspect (w/h) for the preview band — wide sheet, so prefer landscape.
     * Full-width crop height is derived from this.
     */
    public const TARGET_RATIO = 1.6;

    /** Never make the vertical band taller than this pixel aspect (w/h). */
    public const RATIO_MIN = 0.85;

    public const MIN_WINDOW_H = 0.04;

    /**
     * @return array{
     *     window: array{x: float, y: float, w: float, h: float},
     *     overlay: array{x: float, y: float, w: float, h: float}|null,
     *     point: array{x: float, y: float}|null,
     *     ratio: float,
     *     bg_style: string
     * }
     */
    public static function forMark(Annotation $mark): array
    {
        $shot = $mark->screenshot;
        $region = $mark->region();
        $url = $shot?->url() ?? '';
        $focusX = (float) $mark->x;
        $focusY = (float) $mark->y;

        if ($region) {
            $focusX = $region['x'] + ($region['w'] / 2);
            $focusY = $region['y'] + ($region['h'] / 2);
            $window = self::windowFromRegion($region, $shot);
            $overlay = self::toCropLocal($region, $window);
            $point = null;
        } else {
            $window = self::windowFromPoint($focusX, $focusY, $shot);
            $overlay = null;
            $point = [
                'x' => self::clamp01($focusX),
                'y' => self::clamp01(($focusY - $window['y']) / max($window['h'], 0.0001)),
            ];
        }

        $ratio = self::trueAspectRatio($window, $shot);

        return [
            'window' => $window,
            'overlay' => $overlay,
            'point' => $point,
            'ratio' => $ratio,
            'bg_style' => self::backgroundStyle($url, $window),
        ];
    }

    /**
     * Full-width vertical band covering the region (+ pad), at least the target band height.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $region
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function windowFromRegion(array $region, ?Screenshot $shot = null): array
    {
        $cy = $region['y'] + ($region['h'] / 2);
        $needed = $region['h'] + (2 * self::REGION_PAD);
        $band = max($needed, self::targetBandHeight($shot));
        $window = self::fullWidthBand($cy, $band, $shot);

        return self::capBandHeight($window, $shot, $cy);
    }

    /**
     * Full-width vertical band centered on the point.
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function windowFromPoint(float $x, float $y, ?Screenshot $shot = null): array
    {
        $band = self::targetBandHeight($shot) + self::POINT_PAD;
        $window = self::fullWidthBand($y, $band, $shot);

        return self::capBandHeight($window, $shot, $y);
    }

    /**
     * Normalized height of a full-width band with ~TARGET_RATIO pixel aspect.
     */
    public static function targetBandHeight(?Screenshot $shot): float
    {
        [$imgW, $imgH] = self::imageSize($shot);
        $h = $imgW / (self::TARGET_RATIO * $imgH);

        return max(min($h, 1.0), self::MIN_WINDOW_H);
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function fullWidthBand(float $focusY, float $h, ?Screenshot $shot = null): array
    {
        $h = max(min($h, 1.0), self::MIN_WINDOW_H);
        $y = $focusY - ($h / 2);

        if ($y < 0) {
            $y = 0;
        }
        if ($y + $h > 1) {
            $y = max(0.0, 1.0 - $h);
        }
        $h = min($h, 1.0 - $y);

        return [
            'x' => 0.0,
            'y' => self::clamp01($y),
            'w' => 1.0,
            'h' => max(min($h, 1.0), self::MIN_WINDOW_H),
        ];
    }

    /**
     * Cap band height so pixel aspect stays >= RATIO_MIN (preview never absurdly tall).
     *
     * @param  array{x: float, y: float, w: float, h: float}  $window
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function capBandHeight(array $window, ?Screenshot $shot, float $focusY): array
    {
        [$imgW, $imgH] = self::imageSize($shot);
        $maxH = $imgW / (self::RATIO_MIN * $imgH);
        $maxH = max(min($maxH, 1.0), self::MIN_WINDOW_H);

        if ($window['h'] <= $maxH + 0.0001) {
            return $window;
        }

        return self::fullWidthBand($focusY, $maxH, $shot);
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $rect
     * @param  array{x: float, y: float, w: float, h: float}  $window
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function toCropLocal(array $rect, array $window): array
    {
        $ww = max($window['w'], 0.0001);
        $wh = max($window['h'], 0.0001);

        return [
            'x' => self::clamp01(($rect['x'] - $window['x']) / $ww),
            'y' => self::clamp01(($rect['y'] - $window['y']) / $wh),
            'w' => min(1.0, max(0.0, $rect['w'] / $ww)),
            'h' => min(1.0, max(0.0, $rect['h'] / $wh)),
        ];
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $window
     */
    public static function backgroundStyle(string $url, array $window): string
    {
        if ($url === '') {
            return '';
        }

        $sizeX = 100 / max($window['w'], 0.02);
        $sizeY = 100 / max($window['h'], 0.02);
        $posX = $window['w'] < 1 ? ($window['x'] / (1 - $window['w'])) * 100 : 0;
        $posY = $window['h'] < 1 ? ($window['y'] / (1 - $window['h'])) * 100 : 0;

        return sprintf(
            'background-image:url(%s);background-size:%.2f%% %.2f%%;background-position:%.2f%% %.2f%%;background-repeat:no-repeat;',
            $url,
            $sizeX,
            $sizeY,
            $posX,
            $posY,
        );
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $window
     */
    public static function trueAspectRatio(array $window, ?Screenshot $shot): float
    {
        [$imgW, $imgH] = self::imageSize($shot);

        return ($window['w'] * $imgW) / max($window['h'] * $imgH, 1);
    }

    /**
     * @return array{0: float, 1: float}
     */
    protected static function imageSize(?Screenshot $shot): array
    {
        $imgW = (float) ($shot?->width ?: 0);
        $imgH = (float) ($shot?->height ?: 0);

        if ($imgW <= 0 || $imgH <= 0) {
            return [1280.0, 800.0];
        }

        return [$imgW, $imgH];
    }

    protected static function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
