<?php

namespace App\Services\Vision;

use App\Models\Finding;
use App\Models\Screenshot;
use App\Support\NormalizedArea;
use Illuminate\Support\Facades\Storage;

abstract class BaseVisionProvider implements VisionProvider
{
    /**
     * @return array{mime: string, base64: string}|null
     */
    protected function imagePayload(Screenshot $screenshot): ?array
    {
        $binary = Storage::disk($screenshot->disk)->get($screenshot->path);
        if ($binary === null || $binary === '') {
            return null;
        }

        $mime = match (strtolower(pathinfo($screenshot->path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        return ['mime' => $mime, 'base64' => base64_encode($binary)];
    }

    /**
     * Normalize a raw model response into capped, severity-whitelisted findings.
     *
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function parseFindings(mixed $raw): array
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
                'area' => $this->normalizeArea($item['area'] ?? null),
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
