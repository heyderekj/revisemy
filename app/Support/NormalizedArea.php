<?php

namespace App\Support;

/**
 * Normalize model- or agent-supplied mark regions into 0–1 x/y/w/h.
 * Accepts common aliases (width/height) and 0–100 percentages.
 */
class NormalizedArea
{
    /**
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public static function from(mixed $area): ?array
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

        if ($w < 0.01 || $h < 0.01) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }
}
