<?php

namespace App\Support;

use App\Models\Annotation;
use Illuminate\Support\Collection;

/**
 * Point marks that land on the same spot fan out side by side, so each stays
 * clickable. Mirrors stack() in resources/js/element-snap.js.
 */
class PinStack
{
    /**
     * @param  Collection<int, Annotation>  $annotations
     * @return array<int, int> annotation id => how many earlier points sit on it
     */
    public static function offsets(Collection $annotations, float $threshold = 0.012): array
    {
        $offsets = [];
        $placed = [];

        foreach ($annotations as $annotation) {
            if ($annotation->region() !== null) {
                continue;
            }

            $near = 0;
            foreach ($placed as [$x, $y]) {
                if (abs($x - $annotation->x) < $threshold && abs($y - $annotation->y) < $threshold) {
                    $near++;
                }
            }

            $offsets[$annotation->id] = $near;
            $placed[] = [(float) $annotation->x, (float) $annotation->y];
        }

        return $offsets;
    }
}
