<?php

namespace App\Services\Capture;

use App\Support\ToolProgress;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Browsershot\Browsershot;

/**
 * Optional self-host driver for installs that have Chrome available.
 * Requires `composer require spatie/browsershot` plus a local Chrome/Chromium;
 * guarded so the class never hard-depends on the package.
 *
 * Browsershot can't hand back pixels and page data from one call, so the
 * element map and DOM come from a second, identically emulated and settled
 * load of each viewport.
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
        $sweep = $fullPage && CaptureSettleScript::enabled();
        $hideConsent = (bool) config('revisemy.capture.hide_consent', true);
        $collect = (bool) config('revisemy.capture.collect_elements', true);

        // Full-page at DPR 1 by default — matches hosted driver; avoids Cloud
        // OOM on tall pages. Narrow viewports may ask for more.
        $shots = $this->capture(
            fn () => Browsershot::url($url),
            $viewports,
            ['origin' => 'capture', 'page_url' => $url],
            fullPage: $fullPage,
            deviceScaleFactor: max(1, (int) config('revisemy.capture.url_device_scale_factor', 1)),
            sweep: $sweep,
            hideConsent: $hideConsent,
        );

        if (! $collect) {
            return $shots;
        }

        foreach ($viewports as $index => $viewport) {
            $page = $this->pageData($url, $viewport, $sweep, $hideConsent);

            if ($page === null) {
                continue;
            }

            if (is_array($page['elements'] ?? null)) {
                $shots[$index]['elements'] = $page['elements'];
                $shots[$index]['meta']['elements_source'] = 'separate_load';
            }

            if ($index === 0 && is_string($page['html'] ?? null)) {
                $shots[$index]['html'] = $page['html'];
            }
        }

        return $shots;
    }

    public function captureHtml(string $html, array $viewports): array
    {
        return $this->capture(
            fn () => Browsershot::html($html),
            $viewports,
            ['origin' => 'html'],
            fullPage: true,
            sweep: false,
            hideConsent: false,
        );
    }

    public function captureDom(string $url): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $sweep = (bool) config('revisemy.capture.url_full_page', true) && CaptureSettleScript::enabled();

        try {
            $shot = $this->configure(Browsershot::url($url), $sweep, hideConsent: true);

            return $shot->bodyHtml();
        } catch (\Throwable $e) {
            Log::warning('Browsershot DOM capture failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Element map + DOM from a settled load of one viewport. Best-effort.
     *
     * @param  array{width: int, height: int, label: string, dpr: int|null, mobile: bool}  $viewport
     * @return array{elements: array<string, mixed>|null, html: string|null}|null
     */
    protected function pageData(string $url, array $viewport, bool $sweep, bool $hideConsent): ?array
    {
        try {
            $shot = $this->configure(Browsershot::url($url), $sweep, $hideConsent, collectElements: true);
            $this->emulate($shot, $viewport, 1);

            $json = $shot->evaluate('JSON.stringify({ elements: window.__rmElements || null, html: document.documentElement.outerHTML })');
            $data = json_decode($json, true);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('Browsershot element map failed', ['url' => $url, 'viewport' => $viewport['label'], 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  callable(): Browsershot  $factory
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    protected function capture(callable $factory, array $viewports, array $baseMeta, bool $fullPage, bool $sweep, bool $hideConsent, ?int $deviceScaleFactor = null): array
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages([
                'capture' => 'Browsershot is not installed — run `composer require spatie/browsershot` or switch to the hosted capture driver.',
            ]);
        }

        $shots = [];

        foreach ($viewports as $viewport) {
            ToolProgress::report('Capturing '.ToolProgress::viewport($viewport['label']));
            $dpr = $viewport['dpr'] ?? $deviceScaleFactor ?? max(1, (int) config('revisemy.capture.device_scale_factor', 2));

            $shot = $this->configure($factory(), $sweep, $hideConsent);
            $this->emulate($shot, $viewport, $dpr);

            if ($fullPage) {
                $shot->fullPage();
            }

            $shots[] = [
                'binary' => $shot->screenshot(),
                'meta' => $baseMeta + [
                    'viewport' => $viewport['label'],
                    'css_width' => $viewport['width'],
                    'dpr' => $dpr,
                    'mobile' => $viewport['mobile'],
                ],
            ];
        }

        return $shots;
    }

    /**
     * Navigation, settle delay and settle script — shared by every load so
     * the screenshot and the page data see the same final state.
     */
    protected function configure(Browsershot $shot, bool $sweep, bool $hideConsent, bool $collectElements = false): Browsershot
    {
        $timeout = (int) config('revisemy.capture.timeout', 30);
        $waitMs = (int) config('revisemy.capture.wait_ms', 1000);
        $budgetMs = CaptureSettleScript::budgetMs($sweep, $collectElements);
        // delay runs before waitForFunction in Browsershot's browser.cjs, so
        // the settle (and its scroll sweep) lives in waitForFunction.
        $chromeTimeout = $timeout + (int) ceil($waitMs / 1000) + (int) ceil(($budgetMs + CaptureSettleScript::OUTER_HEADROOM_MS) / 1000) + 5;

        $shot->timeout($chromeTimeout)
            // networkidle0 = strict; networkidle2 = non-strict (default).
            ->waitUntilNetworkIdle(config('revisemy.capture.wait_until', 'networkidle2') === 'networkidle0')
            ->delay($waitMs)
            ->waitForFunction(
                CaptureSettleScript::expression(sweep: $sweep, hideConsent: $hideConsent, collectElements: $collectElements),
                timeout: $budgetMs + CaptureSettleScript::OUTER_HEADROOM_MS,
            );

        if ($nodeModules = config('revisemy.capture.node_modules')) {
            $shot->setNodeModulePath($nodeModules);
        }

        if ($chromePath = config('revisemy.capture.chrome_path')) {
            $shot->setChromePath($chromePath);
        }

        return $shot;
    }

    /**
     * @param  array{width: int, height: int, label: string, dpr: int|null, mobile: bool}  $viewport
     */
    protected function emulate(Browsershot $shot, array $viewport, int $dpr): void
    {
        $shot->windowSize($viewport['width'], $viewport['height'])
            ->deviceScaleFactor($dpr);

        if ($viewport['mobile']) {
            $shot->mobile()->touch()->userAgent((string) config('revisemy.capture.mobile_user_agent'));
        }
    }
}
