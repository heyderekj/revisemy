<?php

namespace App\Support;

use App\Services\Capture\CaptureSettleScript;

/**
 * An element map is what a page said about itself: each visible element's
 * selector, kind, text and box (see CaptureSettleScript::COLLECTOR). Pages
 * are untrusted, and so are maps an agent sends with an upload, so every map
 * is cleaned here before it is stored: counts and lengths capped, tags,
 * kinds and roles held to simple patterns, boxes made finite and clamped to
 * the page, colours held to hex. Nothing in a map is ever an instruction.
 */
class ElementMap
{
    /** Largest map accepted, as JSON. */
    public const MAX_BYTES = 1_500_000;

    public const KINDS = [
        'Heading', 'Paragraph', 'Link', 'Button', 'Image', 'Graphic', 'Video', 'List', 'List item',
        'Navigation', 'Header', 'Footer', 'Section', 'Form', 'Field', 'Label', 'Quote', 'Figure',
        'Table', 'Main', 'Article', 'Aside', 'Dialog', 'Tab', 'Box', 'Text',
    ];

    /** Short style keys a map may carry, and what each holds. */
    protected const STYLE_NUMBERS = ['fs', 'lh', 'fw', 'g'];

    protected const STYLE_COLOURS = ['c', 'bg'];

    /**
     * A clean copy of $raw for a shot whose image shows $css (the page's CSS
     * size), or null with a short reason when the map can't be used.
     *
     * @param  array{w: float, h: float}  $css
     * @return array{map: array<string, mixed>|null, note: string|null}
     */
    public static function sanitize(mixed $raw, array $css): array
    {
        if (! is_array($raw) || ! is_array($raw['elements'] ?? null)) {
            return ['map' => null, 'note' => 'the element map has no elements list'];
        }

        if (strlen((string) json_encode($raw)) > self::MAX_BYTES) {
            return ['map' => null, 'note' => 'the element map is over 1.5MB'];
        }

        $docWidth = self::number($raw['docWidth'] ?? null, 1, 100_000);
        $docHeight = self::number($raw['docHeight'] ?? null, 1, 100_000);
        $width = max(1.0, $css['w']);
        $height = max(1.0, $css['h']);

        $elements = [];

        foreach (array_slice(array_values($raw['elements']), 0, CaptureSettleScript::MAX_ELEMENTS) as $index => $element) {
            $clean = is_array($element) ? self::element($element, $width, $height) : null;

            if ($clean === null) {
                continue;
            }

            // Parents point back up the list; an index that doesn't is dropped.
            $parent = $element['parent'] ?? null;
            $clean['parent'] = is_int($parent) && $parent >= 0 && $parent < $index ? $parent : null;
            $clean['source_index'] = $index;
            $elements[] = $clean;
        }

        // Dropped elements shift the list: point parents at the new positions.
        $moved = [];
        foreach ($elements as $position => $element) {
            $moved[$element['source_index']] = $position;
        }
        foreach ($elements as $position => $element) {
            $elements[$position]['parent'] = $element['parent'] !== null ? ($moved[$element['parent']] ?? null) : null;
            unset($elements[$position]['source_index']);
        }

        $viewport = is_array($raw['viewport'] ?? null) ? $raw['viewport'] : [];

        return ['map' => [
            'version' => (int) ($raw['version'] ?? 1) === 2 ? 2 : 1,
            'docWidth' => $docWidth !== null ? (int) round($docWidth) : (int) round($width),
            'docHeight' => $docHeight !== null ? (int) round($docHeight) : (int) round($height),
            'viewport' => [
                'width' => (int) round(self::number($viewport['width'] ?? null, 1, 10_000) ?? $width),
                'height' => (int) round(self::number($viewport['height'] ?? null, 1, 10_000) ?? 800),
            ],
            'elements' => $elements,
        ], 'note' => null];
    }

    /**
     * The compact list the review canvas hit-tests against: one row per
     * element, in map order, so a row's index is the element's index.
     * Row: [kind index, x, y, w, h, parent, short text, flags]; flag 1 means
     * the element can't be picked (a bottom-fixed bar sits elsewhere in a
     * full-page shot than its box says).
     *
     * @param  array<string, mixed>  $map
     * @param  array{w: float, h: float}  $css
     * @return array<string, mixed>
     */
    public static function pickList(array $map, array $css): array
    {
        $kinds = [];
        $rows = [];

        foreach ((array) ($map['elements'] ?? []) as $element) {
            $kind = (string) ($element['kind'] ?? 'Text');
            $kindIndex = array_search($kind, $kinds, true);

            if ($kindIndex === false) {
                $kinds[] = $kind;
                $kindIndex = count($kinds) - 1;
            }

            $box = $element['box'];
            $text = (string) ($element['label'] ?? '') !== '' ? (string) $element['label'] : (string) ($element['text'] ?? '');

            $rows[] = [
                $kindIndex,
                $box['x'],
                $box['y'],
                $box['w'],
                $box['h'],
                $element['parent'] ?? null,
                mb_strimwidth($text, 0, 48, '…'),
                ($element['fixed'] ?? null) === 'bottom' ? 1 : 0,
            ];
        }

        return [
            'v' => 1,
            'w' => (int) round($css['w']),
            'h' => (int) round($css['h']),
            'vw' => (int) ($map['viewport']['width'] ?? $css['w']),
            'vh' => (int) ($map['viewport']['height'] ?? 800),
            'kinds' => $kinds,
            'e' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>|null
     */
    protected static function element(array $element, float $width, float $height): ?array
    {
        $box = is_array($element['box'] ?? null) ? $element['box'] : [];
        $x = self::number($box['x'] ?? null, -100_000, 100_000);
        $y = self::number($box['y'] ?? null, -100_000, 100_000);
        $w = self::number($box['w'] ?? null, 0, 100_000);
        $h = self::number($box['h'] ?? null, 0, 100_000);

        if ($x === null || $y === null || $w === null || $h === null) {
            return null;
        }

        // Clamp to the page the image shows; an element wholly off it is dropped.
        $left = max(0.0, $x);
        $top = max(0.0, $y);
        $right = min($width, $x + $w);
        $bottom = min($height, $y + $h);

        if ($right - $left < 1 || $bottom - $top < 1) {
            return null;
        }

        $tag = strtolower((string) ($element['tag'] ?? ''));
        $kind = (string) ($element['kind'] ?? '');
        $role = strtolower((string) ($element['role'] ?? ''));
        $src = (string) ($element['src'] ?? '');

        return array_filter([
            'selector' => self::text($element['selector'] ?? null, 300),
            'tag' => preg_match('/^[a-z][a-z0-9-]{0,31}$/', $tag) ? $tag : 'div',
            'kind' => in_array($kind, self::KINDS, true) ? $kind : 'Text',
            'text' => self::text($element['text'] ?? null, 200),
            'label' => self::text($element['label'] ?? null, 120),
            'role' => preg_match('/^[a-z-]{1,32}$/', $role) ? $role : null,
            'src' => preg_match('#^https?://#i', $src) ? mb_substr($src, 0, 500) : null,
            'box' => [
                'x' => (int) round($left),
                'y' => (int) round($top),
                'w' => (int) round($right - $left),
                'h' => (int) round($bottom - $top),
            ],
            'fixed' => in_array($element['fixed'] ?? null, ['top', 'bottom'], true) ? $element['fixed'] : null,
            'styles' => self::styles($element['styles'] ?? null),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function styles(mixed $styles): array
    {
        if (! is_array($styles)) {
            return [];
        }

        $clean = [];

        foreach (self::STYLE_NUMBERS as $key) {
            if (($value = self::number($styles[$key] ?? null, 0, 2_000)) !== null) {
                $clean[$key] = round($value, 1);
            }
        }

        foreach (self::STYLE_COLOURS as $key) {
            if (($colour = self::colour($styles[$key] ?? null)) !== null) {
                $clean[$key] = $colour;
            }
        }

        if (is_string($styles['ff'] ?? null) && preg_match('/^[\w\s\-]{1,40}$/u', $styles['ff'])) {
            $clean['ff'] = $styles['ff'];
        }

        if (is_string($styles['d'] ?? null) && in_array($styles['d'], ['flex', 'grid', 'inline-flex', 'inline-grid'], true)) {
            $clean['d'] = $styles['d'];
        }

        if (is_array($styles['p'] ?? null) && count($styles['p']) === 4) {
            $padding = array_map(fn ($n) => self::number($n, 0, 2_000), array_values($styles['p']));
            if (! in_array(null, $padding, true)) {
                $clean['p'] = array_map(fn (float $n) => round($n, 1), $padding);
            }
        }

        $radius = $styles['r'] ?? null;
        if (($single = self::number($radius, 0, 10_000)) !== null) {
            $clean['r'] = round($single, 1);
        } elseif (is_array($radius) && count($radius) === 4) {
            $corners = array_map(fn ($n) => self::number($n, 0, 10_000), array_values($radius));
            if (! in_array(null, $corners, true)) {
                $clean['r'] = array_map(fn (float $n) => round($n, 1), $corners);
            }
        }

        if (is_array($styles['b'] ?? null) && ($width = self::number($styles['b'][0] ?? null, 0, 1_000)) !== null) {
            $clean['b'] = [round($width, 1), self::colour($styles['b'][1] ?? null)];
        }

        return $clean;
    }

    public static function colour(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})?$/', strtolower($value)) ? strtolower($value) : null;
    }

    protected static function number(mixed $value, float $min, float $max): ?float
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = (float) $value;

        return is_finite($value) && $value >= $min && $value <= $max ? $value : null;
    }

    /**
     * Page words, kept as words: whitespace collapsed, control and
     * direction-override characters out, cut to $max.
     */
    public static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = preg_replace('/[\x{0000}-\x{001F}\x{007F}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', ' ', $value) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }
}
