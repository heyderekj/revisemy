<?php

namespace App\Support;

/**
 * Normalize model- or agent-supplied mark regions into 0–1 x/y/w/h.
 * Accepts common aliases (width/height) and 0–100 percentages.
 */
class NormalizedArea
{
    /**
     * Smallest side a model-supplied region may have: anything thinner is
     * noise, not a place on the page.
     */
    public const MIN_SIDE = 0.01;

    /**
     * Smallest side a person's mark may have. On a 6,000px page 1% is 60px,
     * taller than most buttons, so marks get a far lower floor.
     */
    public const MIN_MARK_SIDE = 0.001;

    /**
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public static function from(mixed $area, float $min = self::MIN_SIDE): ?array
    {
        if (! is_array($area)) {
            return null;
        }

        $x = (float) ($area['x'] ?? 0);
        $y = (float) ($area['y'] ?? 0);
        $w = (float) ($area['w'] ?? $area['width'] ?? 0);
        $h = (float) ($area['h'] ?? $area['height'] ?? 0);

        // Models often return 0–100 percentages instead of 0–1 fractions.
        if (max($x, $y, $w, $h) > 1 && max($x, $y, $w, $h) <= 100) {
            $x /= 100;
            $y /= 100;
            $w /= 100;
            $h /= 100;
        }

        $x = max(0, min(1, $x));
        $y = max(0, min(1, $y));
        $w = max(0, min(1 - $x, $w));
        $h = max(0, min(1 - $y, $h));

        if ($w < $min || $h < $min) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }
}
