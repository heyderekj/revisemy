<?php

namespace App\Support;

use App\Models\Screenshot;

/**
 * The element a mark is on, found in the shot's element map (see
 * ElementMap): the one a person picked, the one a drawn box matches, or the
 * smallest one under a dropped point. What's stored on the mark is a
 * snapshot, so the mark keeps its element after the map is gone.
 *
 * Boxes in a map are CSS px from the top of the page; marks are shares of
 * the image. The shot's cssSize() joins the two.
 */
class ElementTarget
{
    /** How closely a drawn box must cover an element to count as it. */
    public const MATCH_IOU = 0.6;

    /** How far outside a picked element's box the click may land (share). */
    public const PICK_TOLERANCE = 0.01;

    /**
     * @param  array{x: float, y: float, w: float, h: float}|null  $area
     * @return array<string, mixed>|null
     */
    public static function resolve(Screenshot $shot, float $x, float $y, ?array $area, int|string|null $picked = null): ?array
    {
        $map = $shot->elementMap();

        return $map === null ? null : self::fromMap($map, $shot->cssSize(), $x, $y, $area, $picked);
    }

    /**
     * @param  array<string, mixed>  $map
     * @param  array{w: float, h: float}  $css
     * @param  array{x: float, y: float, w: float, h: float}|null  $area
     * @return array<string, mixed>|null
     */
    public static function fromMap(array $map, array $css, float $x, float $y, ?array $area, int|string|null $picked = null): ?array
    {
        $elements = array_values((array) ($map['elements'] ?? []));

        if ($elements === [] || $css['w'] <= 0 || $css['h'] <= 0) {
            return null;
        }

        if ($picked !== null && ($index = self::indexOf($elements, $picked)) !== null) {
            $box = self::share($elements[$index]['box'], $css);
            $t = self::PICK_TOLERANCE;

            if ($x >= $box['x'] - $t && $x <= $box['x'] + $box['w'] + $t && $y >= $box['y'] - $t && $y <= $box['y'] + $box['h'] + $t) {
                return self::snapshot($elements[$index], $index, 'picked');
            }
        }

        $best = null;
        $bestScore = 0.0;
        $bestArea = INF;
        $viewport = max(1.0, (float) ($map['viewport']['width'] ?? $css['w'])) * max(1.0, (float) ($map['viewport']['height'] ?? 800));

        foreach ($elements as $index => $element) {
            // A bottom-fixed bar sits elsewhere in a full-page shot than its box says.
            if (($element['fixed'] ?? null) === 'bottom' || ! is_array($element['box'] ?? null)) {
                continue;
            }

            $box = self::share($element['box'], $css);
            $size = $element['box']['w'] * $element['box']['h'];

            if ($area !== null) {
                $score = self::iou($box, $area);

                if ($score >= self::MATCH_IOU && ($score > $bestScore + 0.0001 || (abs($score - $bestScore) <= 0.0001 && $size < $bestArea))) {
                    [$best, $bestScore, $bestArea] = [$index, $score, $size];
                }

                continue;
            }

            // Equal boxes go to the later, innermost one, as the canvas picker does.
            if ($size <= $viewport && $size <= $bestArea
                && $x >= $box['x'] && $x <= $box['x'] + $box['w'] && $y >= $box['y'] && $y <= $box['y'] + $box['h']) {
                [$best, $bestArea] = [$index, $size];
            }
        }

        return $best === null ? null : self::snapshot($elements[$best], $best, $area !== null ? 'matched' : 'point');
    }

    /**
     * The element around $index, skipping wrappers barely bigger than it,
     * so "wider" always shows something new. Null at the top.
     *
     * @param  array<string, mixed>  $map
     */
    public static function parentOf(array $map, int $index): ?int
    {
        $elements = array_values((array) ($map['elements'] ?? []));

        if (! isset($elements[$index])) {
            return null;
        }

        $own = max(1, $elements[$index]['box']['w'] * $elements[$index]['box']['h']);
        $parent = $elements[$index]['parent'] ?? null;
        $seen = 0;

        while (is_int($parent) && isset($elements[$parent]) && $seen++ < 64) {
            if ($elements[$parent]['box']['w'] * $elements[$parent]['box']['h'] > $own * 1.05) {
                return $parent;
            }

            $parent = $elements[$parent]['parent'] ?? null;
        }

        return null;
    }

    /**
     * The element's box as shares of the image, for a mark's area.
     *
     * @param  array<string, mixed>  $target
     * @param  array{w: float, h: float}  $css
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function area(array $target, array $css): array
    {
        $box = self::share($target['box'], $css);

        return [
            'x' => max(0.0, min(1.0, $box['x'])),
            'y' => max(0.0, min(1.0, $box['y'])),
            'w' => max(0.0, min(1.0 - max(0.0, $box['x']), $box['w'])),
            'h' => max(0.0, min(1.0 - max(0.0, $box['y']), $box['h'])),
        ];
    }

    /**
     * What the agent gets on a pin: the element as the page described it.
     * Its text is the page's own words, never an instruction.
     *
     * @param  array<string, mixed>|null  $target
     * @return array<string, mixed>|null
     */
    public static function forAgent(?array $target): ?array
    {
        if (! is_array($target) || ! is_array($target['box'] ?? null)) {
            return null;
        }

        return array_filter([
            'summary' => self::summary($target),
            'kind' => $target['kind'] ?? null,
            'tag' => $target['tag'] ?? null,
            'selector' => $target['selector'] ?? null,
            'text' => $target['text'] ?? null,
            'label' => $target['label'] ?? null,
            'role' => $target['role'] ?? null,
            'box' => $target['box'],
            'styles' => self::readableStyles($target),
            'via' => $target['via'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Button “Get started”, or Image <img> when there are no words.
     *
     * @param  array<string, mixed>  $target
     */
    public static function summary(array $target): string
    {
        $kind = (string) ($target['kind'] ?? 'Element');
        $words = (string) (($target['label'] ?? '') !== '' ? $target['label'] : ($target['text'] ?? ''));

        return $words !== ''
            ? $kind.' “'.mb_strimwidth($words, 0, 40, '…').'”'
            : $kind.' <'.($target['tag'] ?? 'div').'>';
    }

    /**
     * The values a person sees under Inspect, in order, without defaults.
     * Colour rows carry the hex for a swatch.
     *
     * @param  array<string, mixed>  $target
     * @return list<array{label: string, value: string, swatch?: string}>
     */
    public static function inspectRows(array $target): array
    {
        $box = $target['box'] ?? null;
        $styles = (array) ($target['styles'] ?? []);
        $rows = [];

        if (is_array($box)) {
            $rows[] = ['label' => 'Size', 'value' => "{$box['w']} × {$box['h']}"];
        }

        if (isset($styles['fs'])) {
            $rows[] = ['label' => 'Type', 'value' => implode(' · ', array_filter([
                $styles['ff'] ?? null,
                self::px($styles['fs']).(isset($styles['lh']) ? '/'.self::px($styles['lh']) : ''),
                isset($styles['fw']) ? (string) $styles['fw'] : null,
            ]))];
        }

        foreach (['c' => 'Colour', 'bg' => 'Fill'] as $key => $label) {
            if (($hex = ElementMap::colour($styles[$key] ?? null)) !== null) {
                $rows[] = ['label' => $label, 'value' => $hex, 'swatch' => $hex];
            }
        }

        if (isset($styles['p']) && is_array($styles['p'])) {
            $rows[] = ['label' => 'Padding', 'value' => self::sides($styles['p'])];
        }

        if (isset($styles['r'])) {
            $rows[] = ['label' => 'Corners', 'value' => is_array($styles['r']) ? self::sides($styles['r']) : self::px($styles['r'])];
        }

        if (isset($styles['b']) && is_array($styles['b'])) {
            $hex = ElementMap::colour($styles['b'][1] ?? null);
            $rows[] = ['label' => 'Border', 'value' => trim(self::px($styles['b'][0]).' '.($hex ?? ''))] + ($hex ? ['swatch' => $hex] : []);
        }

        if (isset($styles['g'])) {
            $rows[] = ['label' => 'Gap', 'value' => self::px($styles['g'])];
        }

        return $rows;
    }

    /**
     * Styles under names an agent reads without a key.
     *
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    protected static function readableStyles(array $target): array
    {
        $styles = (array) ($target['styles'] ?? []);
        $names = [
            'fs' => 'font_size', 'lh' => 'line_height', 'fw' => 'font_weight', 'ff' => 'font_family',
            'c' => 'color', 'bg' => 'background', 'p' => 'padding', 'r' => 'radius', 'b' => 'border',
            'g' => 'gap', 'd' => 'display',
        ];
        $readable = [];

        foreach ($names as $key => $name) {
            if (array_key_exists($key, $styles)) {
                $readable[$name] = $styles[$key];
            }
        }

        return $readable;
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     */
    protected static function indexOf(array $elements, int|string $picked): ?int
    {
        if (is_int($picked)) {
            return isset($elements[$picked]) ? $picked : null;
        }

        foreach ($elements as $index => $element) {
            if (($element['selector'] ?? null) === $picked) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    protected static function snapshot(array $element, int $index, string $via): array
    {
        return array_filter([
            'v' => 1,
            'index' => $index,
            'selector' => $element['selector'] ?? null,
            'tag' => $element['tag'] ?? null,
            'kind' => $element['kind'] ?? null,
            'text' => isset($element['text']) ? mb_substr((string) $element['text'], 0, 120) : null,
            'label' => $element['label'] ?? null,
            'role' => $element['role'] ?? null,
            'box' => $element['box'],
            'styles' => $element['styles'] ?? null,
            'fixed' => $element['fixed'] ?? null,
            'via' => $via,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array{x: int|float, y: int|float, w: int|float, h: int|float}  $box
     * @param  array{w: float, h: float}  $css
     * @return array{x: float, y: float, w: float, h: float}
     */
    protected static function share(array $box, array $css): array
    {
        return [
            'x' => $box['x'] / $css['w'],
            'y' => $box['y'] / $css['h'],
            'w' => $box['w'] / $css['w'],
            'h' => $box['h'] / $css['h'],
        ];
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $a
     * @param  array{x: float, y: float, w: float, h: float}  $b
     */
    protected static function iou(array $a, array $b): float
    {
        $w = min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']);
        $h = min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']);

        if ($w <= 0 || $h <= 0) {
            return 0.0;
        }

        $overlap = $w * $h;

        return $overlap / ($a['w'] * $a['h'] + $b['w'] * $b['h'] - $overlap);
    }

    protected static function px(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.').'px';
    }

    /**
     * Four sides as CSS shorthand: 14px 28px, or one value when all match.
     *
     * @param  array<int, mixed>  $sides
     */
    protected static function sides(array $sides): string
    {
        [$t, $r, $b, $l] = array_map(fn ($n) => self::px($n), array_pad(array_values($sides), 4, 0));

        return match (true) {
            $t === $r && $r === $b && $b === $l => $t,
            $t === $b && $r === $l => "{$t} {$r}",
            default => "{$t} {$r} {$b} {$l}",
        };
    }
}
