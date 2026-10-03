<?php

namespace App\Support;

use App\Models\Screenshot;

/**
 * Ties marks to page elements using the element map recorded at capture
 * time (selector, kind, text and box per visible element). The map's boxes
 * are CSS px from the top of the document; here they become 0–1 fractions of
 * the capture, the same space marks are drawn in.
 */
class ElementAnchor
{
    /**
     * The map's elements with normalized boxes, compact for the browser:
     * {s: selector, k: kind, t: text, b: [x, y, w, h]}.
     *
     * @return list<array{s: string, k: string, t: string, b: array{0: float, 1: float, 2: float, 3: float}}>
     */
    public static function forCanvas(Screenshot $shot): array
    {
        $map = $shot->elementMap();

        if ($map === null) {
            return [];
        }

        $elements = [];

        foreach ((array) ($map['elements'] ?? []) as $element) {
            $area = self::normalize($map, $element);

            if ($area === null || ! is_string($element['selector'] ?? null)) {
                continue;
            }

            $elements[] = [
                's' => $element['selector'],
                'k' => (string) ($element['kind'] ?? 'Block'),
                't' => mb_substr((string) ($element['text'] ?? ''), 0, 120),
                'b' => [round($area['x'], 5), round($area['y'], 5), round($area['w'], 5), round($area['h'], 5)],
            ];
        }

        return $elements;
    }

    /**
     * Look a selector up in a screenshot's element map.
     *
     * @return array{selector: string, tag: string|null, kind: string, text: string, area: array{x: float, y: float, w: float, h: float}}|null
     */
    public static function resolve(Screenshot $shot, string $selector): ?array
    {
        $map = $shot->elementMap();

        if ($map === null || $selector === '') {
            return null;
        }

        foreach ((array) ($map['elements'] ?? []) as $element) {
            if (($element['selector'] ?? null) !== $selector) {
                continue;
            }

            $area = self::normalize($map, $element);

            if ($area === null) {
                return null;
            }

            return [
                'selector' => $selector,
                'tag' => is_string($element['tag'] ?? null) ? $element['tag'] : null,
                'kind' => (string) ($element['kind'] ?? 'Block'),
                'text' => (string) ($element['text'] ?? ''),
                'area' => $area,
            ];
        }

        return null;
    }

    /**
     * Whether suggested copy now reads on the element: the same words, give
     * or take whitespace and case.
     */
    public static function showsCopy(string $elementText, string $suggestedCopy): bool
    {
        $copy = self::squash($suggestedCopy);

        return $copy !== '' && str_contains(self::squash($elementText), $copy);
    }

    protected static function squash(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    /**
     * @param  array<string, mixed>  $map
     * @param  array<string, mixed>  $element
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    protected static function normalize(array $map, array $element): ?array
    {
        $docWidth = (float) ($map['docWidth'] ?? 0);
        $docHeight = (float) ($map['docHeight'] ?? 0);
        $box = $element['box'] ?? null;

        if ($docWidth <= 0 || $docHeight <= 0 || ! is_array($box)) {
            return null;
        }

        $x = max(0.0, min(1.0, (float) ($box['x'] ?? 0) / $docWidth));
        $y = max(0.0, min(1.0, (float) ($box['y'] ?? 0) / $docHeight));
        $w = max(0.0, min(1.0 - $x, (float) ($box['w'] ?? 0) / $docWidth));
        $h = max(0.0, min(1.0 - $y, (float) ($box['h'] ?? 0) / $docHeight));

        if ($w <= 0 || $h <= 0) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }
}
