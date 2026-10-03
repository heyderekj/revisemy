<?php

namespace App\Services\Capture;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PageCaptureService
{
    public function enabled(): bool
    {
        return $this->driver()?->enabled() ?? false;
    }

    /**
     * Website capture: one shot per configured viewport (desktop, mobile,
     * tablet), plus the rendered DOM as hidden AI context.
     *
     * The DOM and element maps come from the same settled page load as the
     * pixels when the driver can do that; otherwise the DOM is a separate,
     * equally settled load, and its embedded element map is given to the
     * desktop shot.
     *
     * @return array{shots: list<array{binary: string, meta: array<string, mixed>, elements?: array<string, mixed>|null, html?: string|null}>, dom: string|null}
     */
    public function capturePage(string $url): array
    {
        $shots = $this->requireDriver()->captureUrl($url, $this->viewports());

        $dom = null;
        foreach ($shots as $index => $shot) {
            $dom ??= $shot['html'] ?? null;
            unset($shots[$index]['html']);
        }

        if ($dom === null) {
            $dom = $this->captureDom($url);

            if ($dom !== null) {
                [$dom, $elements] = self::extractEmbeddedElements($dom);

                if ($elements !== null && $shots !== [] && empty($shots[0]['elements'])) {
                    $shots[0]['elements'] = $elements;
                    $shots[0]['meta']['elements_source'] = 'separate_load';
                }
            }
        }

        if ($dom !== null) {
            // The settle script's own style tags are capture plumbing, not page.
            $dom = (string) preg_replace('#<style data-rm-capture[^>]*>.*?</style>#s', '', $dom);
        }

        return ['shots' => array_values($shots), 'dom' => $dom];
    }

    /**
     * @return list<array{binary: string, meta: array<string, mixed>, elements?: array<string, mixed>|null}>
     */
    public function captureUrl(string $url): array
    {
        return $this->capturePage($url)['shots'];
    }

    /**
     * Pull the element map the settle script embedded as a JSON script tag
     * out of a rendered DOM, returning the DOM without it.
     *
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    public static function extractEmbeddedElements(string $html): array
    {
        $pattern = '#<script[^>]*id="__rm-elements"[^>]*>(.*?)</script>#s';

        if (! preg_match($pattern, $html, $matches)) {
            return [$html, null];
        }

        $elements = json_decode($matches[1], true);

        return [
            (string) preg_replace($pattern, '', $html, 1),
            is_array($elements) ? $elements : null,
        ];
    }

    /**
     * Email/HTML capture: a single ~600px-wide frame, how clients render it.
     *
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function captureHtml(string $html): array
    {
        return $this->requireDriver()->captureHtml($html, [
            ['width' => 600, 'height' => 800, 'label' => 'email-600', 'dpr' => null, 'mobile' => false],
        ]);
    }

    /**
     * Best-effort rendered-DOM snapshot for AI context; null when the driver
     * is missing, unconfigured for content, or the fetch fails. Never blocks
     * review creation.
     */
    public function captureDom(string $url): ?string
    {
        try {
            return $this->driver()?->captureDom($url);
        } catch (\Throwable $e) {
            Log::warning('DOM capture failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Configured viewports, each [width, height] plus optional 'dpr' and
     * 'mobile' (touch, mobile user agent, isMobile) keys.
     *
     * @return list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool}>
     */
    protected function viewports(): array
    {
        $configured = (array) config('revisemy.capture.viewports', [
            'desktop' => [1280, 800],
            'mobile' => [375, 812, 'dpr' => 2, 'mobile' => true],
        ]);

        $viewports = [];
        foreach ($configured as $label => $viewport) {
            $width = (int) ($viewport[0] ?? $viewport['width'] ?? 0);
            $height = (int) ($viewport[1] ?? $viewport['height'] ?? 0);

            if ($width < 1 || $height < 1) {
                continue;
            }

            $viewports[] = [
                'width' => $width,
                'height' => $height,
                'label' => $label.'-'.$width,
                'dpr' => isset($viewport['dpr']) ? max(1, (int) $viewport['dpr']) : null,
                'mobile' => (bool) ($viewport['mobile'] ?? false),
            ];
        }

        return $viewports;
    }

    protected function requireDriver(): CaptureDriver
    {
        $driver = $this->driver();

        if (! $driver || ! $driver->enabled()) {
            throw ValidationException::withMessages([
                'capture' => '[capture_not_configured] Server-side capture is off. Set REVISEMY_CAPTURE_DRIVER=hosted plus REVISEMY_CAPTURE_ENDPOINT/KEY (Browserless) on Cloud, or browsershot locally. Fallback: call create_review with images as desktop+mobile data URLs instead of capture_url.',
            ]);
        }

        return $driver;
    }

    protected function driver(): ?CaptureDriver
    {
        return match (config('revisemy.capture.driver')) {
            'hosted' => app(HostedCaptureDriver::class),
            'browsershot' => app(BrowsershotCaptureDriver::class),
            default => null,
        };
    }
}
