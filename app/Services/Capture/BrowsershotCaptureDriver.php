<?php

namespace App\Services\Capture;

use App\Support\ToolProgress;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Process;

/**
 * Optional self-host driver for installs that have Chrome available.
 * Requires `composer require spatie/browsershot` plus a local Chrome/Chromium;
 * guarded so the class never hard-depends on the package.
 *
 * URL capture uses Puppeteer directly so the screenshot, element map and
 * load diagnostics all come from one browser page.
 */
class BrowsershotCaptureDriver implements CaptureDriver
{
    public function enabled(): bool
    {
        return class_exists(Browsershot::class);
    }

    public function captureUrl(string $url, array $viewports): array
    {
        $shots = [];
        foreach ($viewports as $viewport) {
            $viewport = CaptureProfile::viewport($viewport);
            $label = $viewport['label'];
            $settings = $viewport['settings'];
            $fullPage = $settings['full_page'];
            $sweep = $fullPage && $settings['scroll_page'];
            $collect = (bool) config('revisemy.capture.collect_elements', true);
            $dpr = CaptureDevice::unmeasuredDpr($viewport);
            $timeout = (int) config('revisemy.capture.timeout', 30);
            $waitMs = (int) config('revisemy.capture.wait_ms', 1000);
            $budget = CaptureSettleScript::budgetMs($sweep, $collect);
            $meta = ['origin' => 'capture', 'page_url' => $url] + CaptureProfile::meta($viewport, 'browsershot', $dpr);
            $started = microtime(true);
            ToolProgress::report('Capturing '.ToolProgress::viewport($label));

            try {
                $process = new Process(
                    ['node', __DIR__.'/local-browser.cjs'],
                    base_path(),
                    ['NODE_PATH' => (string) config('revisemy.capture.node_modules')],
                    json_encode([
                        'url' => $url,
                        'executablePath' => config('revisemy.capture.chrome_path'),
                        'viewport' => [
                            'width' => $viewport['width'],
                            'height' => $viewport['height'],
                            'deviceScaleFactor' => $dpr,
                            'isMobile' => $viewport['mobile'],
                            'hasTouch' => $viewport['mobile'],
                        ],
                        'userAgent' => $viewport['user_agent'],
                        'goto' => ['waitUntil' => config('revisemy.capture.wait_until', 'networkidle2'), 'timeout' => $timeout * 1000],
                        'waitMs' => $waitMs,
                        'settle' => '('.CaptureSettleScript::functionBody(
                            sweep: $sweep,
                            hideConsent: $settings['hide_consent'],
                            collectElements: $collect,
                            hideChat: $settings['hide_chat'],
                            settings: $settings,
                        ).')()',
                        'fullPage' => $fullPage,
                        'maxHtml' => PageCaptureService::MAX_SNAPSHOT_BYTES,
                    ], JSON_THROW_ON_ERROR),
                    $timeout + (int) ceil(($waitMs + $budget) / 1000) + 15,
                );
                $process->mustRun();
                $data = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $binary = base64_decode($data['image'], true);
                if ($binary === false || ! @getimagesizefromstring($binary)) {
                    throw new \RuntimeException('Chrome did not return an image.');
                }
                $elements = $data['elements'] ?? null;
                $shots[] = [
                    'binary' => $binary,
                    'meta' => $meta + CaptureQuality::browserMeta($data) + [
                        'capture_ms' => (int) round((microtime(true) - $started) * 1000),
                        'timings' => $data['timings'] ?? [],
                    ] + (is_array($elements) ? ['elements_source' => 'same_load'] : []),
                    'elements' => $elements,
                    'html' => $data['html'] ?? null,
                    'snapshot_base' => $data['snapshotBase'] ?? null,
                ];
                ToolProgress::report('Captured '.ToolProgress::viewport($label), 'shot:'.$label);
            } catch (\Exception $e) {
                Log::warning('Local URL capture failed', ['viewport' => $label, 'error' => $e->getMessage()]);
                $shots[] = CaptureQuality::failedShot($meta, "[capture_provider_failed] Local Chrome could not capture {$label}.");
            }
        }

        return CaptureQuality::requireSuccessfulShot($shots);
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
            $shot = $this->configure(Browsershot::url($url), $sweep, hideConsent: true, hideChat: (bool) config('revisemy.capture.hide_chat', true));

            return $shot->bodyHtml();
        } catch (\Throwable $e) {
            Log::warning('Browsershot DOM capture failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  callable(): Browsershot  $factory
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device?: string}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    protected function capture(callable $factory, array $viewports, array $baseMeta, bool $fullPage, bool $sweep, bool $hideConsent, bool $hideChat = false): array
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages([
                'capture' => 'Browsershot is not installed — run `composer require spatie/browsershot` or switch to the hosted capture driver.',
            ]);
        }

        $shots = [];

        foreach ($viewports as $viewport) {
            ToolProgress::report('Capturing '.ToolProgress::viewport($viewport['label']));
            $dpr = $viewport['dpr'] ?? max(1, (int) config('revisemy.capture.device_scale_factor', 2));

            $shot = $this->configure($factory(), $sweep, $hideConsent, hideChat: $hideChat);
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
            ToolProgress::report('Captured '.ToolProgress::viewport($viewport['label']), 'shot:'.$viewport['label']);
        }

        return $shots;
    }

    /**
     * Navigation, settle delay and settle script — shared by every load so
     * the screenshot and the page data see the same final state.
     */
    protected function configure(Browsershot $shot, bool $sweep, bool $hideConsent, bool $collectElements = false, bool $hideChat = false): Browsershot
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
                CaptureSettleScript::expression(sweep: $sweep, hideConsent: $hideConsent, collectElements: $collectElements, hideChat: $hideChat),
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
     * @param  array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device?: string}  $viewport
     */
    protected function emulate(Browsershot $shot, array $viewport, int $dpr): void
    {
        $shot->windowSize($viewport['width'], $viewport['height'])
            ->deviceScaleFactor($dpr);

        if ($viewport['mobile']) {
            $shot->mobile()->touch();
        }

        if ($userAgent = CaptureDevice::userAgent($viewport)) {
            $shot->userAgent($userAgent);
        }
    }
}
