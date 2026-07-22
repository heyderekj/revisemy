<?php

namespace App\Services\Capture;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Default capture driver: a Browserless-compatible hosted screenshot API.
 * Laravel Cloud app containers ship no Chrome, so rendering happens over a
 * plain HTTPS POST — {url|html, viewport, options} in, image bytes out.
 */
class HostedCaptureDriver implements CaptureDriver
{
    public function enabled(): bool
    {
        return (string) config('revisemy.capture.endpoint') !== '';
    }

    public function captureUrl(string $url, array $viewports): array
    {
        // Full-page at DPR 1 — tall 2× PNGs routinely OOM Cloud's 256MB PHP.
        return $this->capture(
            ['url' => $url],
            $viewports,
            ['origin' => 'capture', 'page_url' => $url],
            fullPage: (bool) config('revisemy.capture.url_full_page', true),
            deviceScaleFactor: max(1, (int) config('revisemy.capture.url_device_scale_factor', 1)),
        );
    }

    public function captureHtml(string $html, array $viewports): array
    {
        return $this->capture(['html' => $html], $viewports, ['origin' => 'html'], fullPage: true);
    }

    public function captureDom(string $url): ?string
    {
        $endpoint = (string) config('revisemy.capture.content_endpoint');

        if ($endpoint === '') {
            return null;
        }

        $response = Http::timeout((int) config('revisemy.capture.timeout', 30))
            ->post($this->authenticatedUrl($endpoint), ['url' => $url]);

        if (! $response->successful() || $response->body() === '') {
            Log::warning('Hosted DOM capture failed', ['url' => $url, 'status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * @param  array<string, string>  $source
     * @param  list<array{0: int, 1: int, 2: string}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    protected function capture(array $source, array $viewports, array $baseMeta, bool $fullPage = false, ?int $deviceScaleFactor = null): array
    {
        $endpoint = $this->authenticatedUrl((string) config('revisemy.capture.endpoint'));
        $timeout = (int) config('revisemy.capture.timeout', 30);
        $waitMs = (int) config('revisemy.capture.wait_ms', 2500);
        $waitUntil = (string) config('revisemy.capture.wait_until', 'networkidle2');
        // Outer HTTP budget must cover navigation + settle delay.
        $httpTimeout = $timeout + (int) ceil($waitMs / 1000) + 5;
        $dpr = $deviceScaleFactor ?? max(1, (int) config('revisemy.capture.device_scale_factor', 2));
        $shots = [];

        foreach ($viewports as [$width, $height, $label]) {
            try {
                $response = Http::timeout($httpTimeout)
                    ->post($endpoint, $source + [
                        'viewport' => [
                            'width' => $width,
                            'height' => $height,
                            'deviceScaleFactor' => $dpr,
                        ],
                        'options' => ['type' => 'png', 'fullPage' => $fullPage],
                        'gotoOptions' => [
                            'waitUntil' => $waitUntil,
                            'timeout' => $timeout * 1000,
                        ],
                        'waitForTimeout' => $waitMs,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Hosted capture request failed', [
                    'label' => $label,
                    'error' => $e->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'capture' => "[capture_provider_failed] Capture timed out or could not reach the screenshot provider at {$label}. Raise REVISEMY_CAPTURE_TIMEOUT, check the Browserless endpoint/key, or fall back to create_review with images as data URLs.",
                ]);
            }

            if (! $response->successful() || $response->body() === '') {
                throw ValidationException::withMessages([
                    'capture' => "[capture_provider_failed] Could not capture at {$label} (HTTP {$response->status()}). Check Browserless token/quota/endpoint, or fall back to create_review with images as data URLs.",
                ]);
            }

            $shots[] = [
                'binary' => $response->body(),
                'meta' => $baseMeta + ['viewport' => $label],
            ];
        }

        return $shots;
    }

    /**
     * Browserless (and compatible hosts) expect ?token=…, not a Bearer header.
     */
    protected function authenticatedUrl(string $endpoint): string
    {
        $key = config('revisemy.capture.api_key');

        if (! is_string($key) || $key === '') {
            return $endpoint;
        }

        if (str_contains($endpoint, 'token=')) {
            return $endpoint;
        }

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').'token='.urlencode($key);
    }
}
