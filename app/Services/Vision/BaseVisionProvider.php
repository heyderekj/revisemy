<?php

namespace App\Services\Vision;

use App\Models\Finding;
use App\Models\Screenshot;
use App\Support\NormalizedArea;
use Illuminate\Support\Facades\Storage;

abstract class BaseVisionProvider implements VisionProvider
{
    /**
     * The image a vision model sees: the vision copy the capture browser made
     * when there is one, else the stored shot. crop is the share of the
     * shot's height the image covers, for mapping regions back.
     *
     * @return array{mime: string, base64: string, crop: float}|null
     */
    protected function imagePayload(Screenshot $screenshot): ?array
    {
        $disk = Storage::disk($screenshot->disk);
        $visionPath = $screenshot->meta['vision_path'] ?? null;
        $crop = (float) ($screenshot->meta['vision_crop'] ?? 0);

        if (is_string($visionPath) && $crop > 0) {
            $vision = rescue(fn () => $disk->get($visionPath), report: false);

            if (is_string($vision) && $vision !== '') {
                return ['mime' => 'image/jpeg', 'base64' => base64_encode($vision), 'crop' => min(1.0, $crop)];
            }
        }

        $binary = $disk->get($screenshot->path);
        if ($binary === null || $binary === '') {
            return null;
        }

        $mime = match (strtolower(pathinfo($screenshot->path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        return ['mime' => $mime, 'base64' => base64_encode($binary), 'crop' => 1.0];
    }

    /**
     * Normalize a raw model response into capped, severity-whitelisted findings.
     * $crop is the share of the shot's height the model saw, from the top;
     * areas are stretched back onto the full shot.
     *
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function parseFindings(mixed $raw, float $crop = 1.0): array
    {
        $items = is_array($raw) ? ($raw['findings'] ?? []) : [];
        $out = [];

        foreach (array_slice(is_array($items) ? $items : [], 0, 6) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $severity = $item['severity'] ?? Finding::SEVERITY_SUGGESTION;
            if (! in_array($severity, [Finding::SEVERITY_SUGGESTION, Finding::SEVERITY_A11Y, Finding::SEVERITY_POLISH], true)) {
                $severity = Finding::SEVERITY_SUGGESTION;
            }
            $body = trim((string) ($item['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $out[] = [
                'severity' => $severity,
                'body' => $body,
                'area' => $this->onShot($this->normalizeArea($item['area'] ?? null), $crop),
            ];
        }

        return $out;
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    protected function normalizeArea(mixed $area): ?array
    {
        return NormalizedArea::from($area);
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}|null  $area
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    protected function onShot(?array $area, float $crop): ?array
    {
        if ($area === null || $crop >= 1.0) {
            return $area;
        }

        return [...$area, 'y' => $area['y'] * $crop, 'h' => $area['h'] * $crop];
    }

    /**
     * Decode a JSON object the model may have wrapped in markdown fences.
     */
    protected function decodeJson(?string $content): ?array
    {
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content);
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
