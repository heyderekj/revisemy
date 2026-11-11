<?php

namespace App\Services\Capture;

use Illuminate\Validation\ValidationException;

final class CaptureQuality
{
    public static function requireSuccessfulShot(array $shots): array
    {
        foreach ($shots as $shot) {
            if ($shot['meta']['origin'] !== 'capture_failed') {
                return $shots;
            }
        }

        throw ValidationException::withMessages([
            'capture' => $shots[0]['meta']['capture_error'] ?? '[capture_provider_failed] No capture viewports were available.',
        ]);
    }

    public static function browserMeta(array $data): array
    {
        $quality = is_array($data['quality'] ?? null) ? $data['quality'] : [];
        $meta = array_intersect_key($quality, array_flip([
            'incomplete_images', 'incomplete_backgrounds', 'incomplete_videos', 'incomplete_fonts', 'sweep_complete',
        ]));

        return $meta + array_filter([
            'settled' => $data['stable'] ?? null,
            'loaded' => $data['loaded'] ?? null,
            'http_status' => $data['httpStatus'] ?? null,
            'browser' => $data['browser'] ?? null,
        ], fn ($value) => $value !== null);
    }

    public static function failedShot(array $meta, string $reason): array
    {
        return [
            'binary' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            'meta' => ['origin' => 'capture_failed', 'capture_error' => $reason] + $meta,
        ];
    }

    public static function describe(?array $meta): ?array
    {
        $origin = $meta['origin'] ?? null;
        if (! in_array($origin, ['capture', 'capture_failed', 'html'], true)) {
            return null;
        }

        if ($origin === 'capture_failed') {
            return ['status' => 'failed', 'message' => 'This screenshot could not be captured. Ask your agent to replace it.'];
        }

        $issues = [];
        foreach (['incomplete_images' => 'Images', 'incomplete_backgrounds' => 'Background images', 'incomplete_videos' => 'Videos', 'incomplete_fonts' => 'Web fonts'] as $key => $label) {
            if (($meta[$key] ?? 0) > 0) {
                $issues[] = $label.' did not load ('.$meta[$key].').';
            }
        }
        if (($meta['http_status'] ?? 0) >= 400) {
            $issues[] = 'The page returned HTTP '.$meta['http_status'].'.';
        }
        if (($meta['sweep_complete'] ?? null) === false) {
            $issues[] = 'The scroll sweep ran out of time.';
        }
        if (($meta['settled'] ?? null) === false) {
            $issues[] = 'The layout was still changing.';
        }
        if (($meta['loaded'] ?? null) === false && $issues === []) {
            $issues[] = 'Some content may still be loading.';
        }
        if ($issues !== []) {
            return ['status' => 'partial', 'message' => implode(' ', $issues).' Check the live page before marking affected areas.'];
        }
        if (($meta['loaded'] ?? null) !== true || ($meta['settled'] ?? null) !== true) {
            return ['status' => 'unverified', 'message' => 'This capture could not verify that the page finished loading.'];
        }

        return ['status' => 'ready', 'message' => null];
    }
}
