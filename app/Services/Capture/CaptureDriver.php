<?php

namespace App\Services\Capture;

interface CaptureDriver
{
    public function enabled(): bool;

    /**
     * Render a live URL at each viewport.
     *
     * @param  list<array{0: int, 1: int, 2: string}>  $viewports  [width, height, label]
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function captureUrl(string $url, array $viewports): array;

    /**
     * Render raw HTML (e.g. an email) at each viewport.
     *
     * @param  list<array{0: int, 1: int, 2: string}>  $viewports
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function captureHtml(string $html, array $viewports): array;
}
