<?php

namespace App\Services\Capture;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Browsershot\Browsershot;

/**
 * Optional self-host driver for installs that have Chrome available.
 * Requires `composer require spatie/browsershot` plus a local Chrome/Chromium;
 * guarded so the class never hard-depends on the package.
 */
class BrowsershotCaptureDriver implements CaptureDriver
{
    public function enabled(): bool
    {
        return class_exists(Browsershot::class);
    }

    public function captureUrl(string $url, array $viewports): array
    {
        $fullPage = (bool) config('revisemy.capture.url_full_page', true);

        // Full-page at DPR 1 — matches hosted driver; avoids Cloud OOM on tall pages.
        return $this->capture(
            fn () => Browsershot::url($url),
            $viewports,
            ['origin' => 'capture', 'page_url' => $url],
            fullPage: $fullPage,
            deviceScaleFactor: max(1, (int) config('revisemy.capture.url_device_scale_factor', 1)),
            scrollReveal: $fullPage && ScrollRevealScript::enabled(),
        );
    }

    public function captureHtml(string $html, array $viewports): array
    {
        return $this->capture(
            fn () => Browsershot::html($html),
            $viewports,
            ['origin' => 'html'],
            fullPage: true,
        );
    }

    public function captureDom(string $url): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $shot = Browsershot::url($url)
                ->timeout((int) config('revisemy.capture.timeout', 30));

            if ($nodeModules = config('revisemy.capture.node_modules')) {
                $shot->setNodeModulePath($nodeModules);
            }

            if ($chromePath = config('revisemy.capture.chrome_path')) {
                $shot->setChromePath($chromePath);
            }

            return $shot->bodyHtml();
        } catch (\Throwable $e) {
            Log::warning('Browsershot DOM capture failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  callable(): Browsershot  $factory
     * @param  list<array{0: int, 1: int, 2: string}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    protected function capture(callable $factory, array $viewports, array $baseMeta, bool $fullPage = false, ?int $deviceScaleFactor = null, bool $scrollReveal = false): array
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages([
                'capture' => 'Browsershot is not installed — run `composer require spatie/browsershot` or switch to the hosted capture driver.',
            ]);
        }

        $shots = [];

        $timeout = (int) config('revisemy.capture.timeout', 30);
        $waitMs = (int) config('revisemy.capture.wait_ms', 2500);
        $waitUntil = (string) config('revisemy.capture.wait_until', 'networkidle2');
        // networkidle0 = strict; networkidle2 = non-strict (default).
        $networkIdleStrict = $waitUntil === 'networkidle0';
        $scrollTimeoutMs = $scrollReveal ? ScrollRevealScript::timeoutMs() : 0;
        // delay runs before waitForFunction in Browsershot's browser.cjs — same
        // order as Browserless — so the scroll sweep lives in waitForFunction.
        $chromeTimeout = $timeout + (int) ceil($waitMs / 1000) + (int) ceil($scrollTimeoutMs / 1000) + 5;
        $dpr = $deviceScaleFactor ?? max(1, (int) config('revisemy.capture.device_scale_factor', 2));

        foreach ($viewports as [$width, $height, $label]) {
            $shot = $factory()
                ->windowSize($width, $height)
                ->deviceScaleFactor($dpr)
                ->timeout($chromeTimeout)
                ->waitUntilNetworkIdle($networkIdleStrict)
                ->delay($waitMs);

            if ($scrollReveal) {
                $shot->waitForFunction(
                    ScrollRevealScript::functionBody(),
                    timeout: $scrollTimeoutMs,
                );
            }

            if ($fullPage) {
                $shot->fullPage();
            }

            if ($nodeModules = config('revisemy.capture.node_modules')) {
                $shot->setNodeModulePath($nodeModules);
            }

            if ($chromePath = config('revisemy.capture.chrome_path')) {
                $shot->setChromePath($chromePath);
            }

            $binary = $shot->screenshot();

            $shots[] = [
                'binary' => $binary,
                'meta' => $baseMeta + ['viewport' => $label],
            ];
        }

        return $shots;
    }
}
