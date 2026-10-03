<?php

namespace App\Services\Capture;

interface CaptureDriver
{
    public function enabled(): bool;

    /**
     * Render a live URL at each viewport, settled to its final resting state.
     * Each shot may also carry the element map and rendered DOM from the same
     * page load; null when the driver can't provide them from that load.
     *
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool}>  $viewports
     * @return list<array{binary: string, meta: array<string, mixed>, elements?: array<string, mixed>|null, html?: string|null}>
     */
    public function captureUrl(string $url, array $viewports): array;

    /**
     * Render raw HTML (e.g. an email) at each viewport.
     *
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool}>  $viewports
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function captureHtml(string $html, array $viewports): array;

    /**
     * Rendered-DOM snapshot of a live URL, kept as hidden AI context, for
     * when captureUrl() couldn't return it from the screenshot's own load.
     * Settled the same way, with the element map embedded as a
     * #__rm-elements JSON script. Best-effort: null when unavailable.
     */
    public function captureDom(string $url): ?string;
}
