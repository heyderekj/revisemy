<?php

namespace App\Services\Capture;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Default capture driver: a Browserless-compatible hosted screenshot API.
 * Laravel Cloud app containers ship no Chrome, so rendering happens over a
 * plain HTTPS POST.
 *
 * URL captures prefer Browserless /function: one page session settles the
 * page, takes the screenshot, and reads the element map and DOM, so all three
 * describe exactly the same frame. Hosts without /function fall back to
 * /screenshot (bytes only) plus a separate, equally settled /content load.
 */
class HostedCaptureDriver implements CaptureDriver
{
    /**
     * Runs inside Browserless /function (Puppeteer). Kept free of PHP
     * interpolation; every value arrives through `context`.
     */
    protected const FUNCTION_CODE = <<<'JS'
export default async function ({ page, context }) {
  if (context.userAgent) {
    await page.setUserAgent(context.userAgent);
  }
  await page.setViewport(context.viewport);
  await page.goto(context.url, { waitUntil: context.waitUntil, timeout: context.timeout });
  if (context.waitMs > 0) {
    await new Promise((resolve) => setTimeout(resolve, context.waitMs));
  }
  await page.evaluate('(' + context.settle + ')()');
  const image = await page.screenshot({ type: 'png', fullPage: context.fullPage, encoding: 'base64' });
  const elements = context.collectElements ? await page.evaluate(() => window.__rmElements || null) : null;
  const html = context.withHtml ? await page.content() : null;
  const stable = await page.evaluate(() => window.__rmSettleStable === true);

  return { data: { image, elements, html, stable }, type: 'application/json' };
}
JS;

    public function enabled(): bool
    {
        return (string) config('revisemy.capture.endpoint') !== '';
    }

    public function captureUrl(string $url, array $viewports): array
    {
        $fullPage = (bool) config('revisemy.capture.url_full_page', true);
        $sweep = $fullPage && CaptureSettleScript::enabled();
        $baseMeta = ['origin' => 'capture', 'page_url' => $url];

        if ($functionEndpoint = $this->functionEndpoint()) {
            $shots = [];

            foreach ($viewports as $index => $viewport) {
                $shot = $this->captureViaFunction($functionEndpoint, $url, $viewport, $baseMeta, $fullPage, $sweep, withHtml: $index === 0);

                if ($shot === null) {
                    // This host has no /function — use /screenshot for the lot.
                    Log::info('Hosted capture: /function unavailable, falling back to /screenshot', ['endpoint' => $functionEndpoint]);
                    $shots = null;
                    break;
                }

                $shots[] = $shot;
            }

            if ($shots !== null) {
                return $shots;
            }
        }

        return $this->captureViaScreenshot(
            ['url' => $url],
            $viewports,
            $baseMeta,
            fullPage: $fullPage,
            sweep: $sweep,
            hideConsent: (bool) config('revisemy.capture.hide_consent', true),
        );
    }

    public function captureHtml(string $html, array $viewports): array
    {
        return $this->captureViaScreenshot(['html' => $html], $viewports, ['origin' => 'html'], fullPage: true, sweep: false, hideConsent: false);
    }

    public function captureDom(string $url): ?string
    {
        $endpoint = (string) config('revisemy.capture.content_endpoint');

        if ($endpoint === '') {
            return null;
        }

        $sweep = (bool) config('revisemy.capture.url_full_page', true) && CaptureSettleScript::enabled();
        $collect = (bool) config('revisemy.capture.collect_elements', true);
        $budgetMs = CaptureSettleScript::budgetMs($sweep, $collect);
        $viewport = $this->primaryViewport();

        $response = Http::timeout($this->requestTimeout($budgetMs))
            ->post($this->authenticatedUrl($endpoint), [
                'url' => $url,
                'viewport' => [
                    'width' => $viewport['width'],
                    'height' => $viewport['height'],
                    'deviceScaleFactor' => 1,
                ],
                'gotoOptions' => $this->gotoOptions(),
                'waitForTimeout' => $this->waitMs(),
                'waitForFunction' => [
                    'fn' => CaptureSettleScript::functionBody(
                        sweep: $sweep,
                        hideConsent: (bool) config('revisemy.capture.hide_consent', true),
                        collectElements: $collect,
                        embedElements: $collect,
                    ),
                    'timeout' => $budgetMs + CaptureSettleScript::OUTER_HEADROOM_MS,
                ],
            ]);

        if (! $response->successful() || $response->body() === '') {
            Log::warning('Hosted DOM capture failed', ['url' => $url, 'status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * One viewport through Browserless /function. Null when the host has no
     * /function endpoint (404/405), so the caller can fall back.
     *
     * @param  array{width: int, height: int, label: string, dpr: int|null, mobile: bool}  $viewport
     * @param  array<string, mixed>  $baseMeta
     * @return array{binary: string, meta: array<string, mixed>, elements: array<string, mixed>|null, html: string|null}|null
     */
    protected function captureViaFunction(string $endpoint, string $url, array $viewport, array $baseMeta, bool $fullPage, bool $sweep, bool $withHtml): ?array
    {
        $collect = (bool) config('revisemy.capture.collect_elements', true);
        $budgetMs = CaptureSettleScript::budgetMs($sweep, $collect);
        $httpTimeout = $this->requestTimeout($budgetMs);
        $dpr = $viewport['dpr'] ?? $this->urlDeviceScaleFactor();
        $label = $viewport['label'];

        $context = [
            'url' => $url,
            'viewport' => [
                'width' => $viewport['width'],
                'height' => $viewport['height'],
                'deviceScaleFactor' => $dpr,
                'isMobile' => $viewport['mobile'],
                'hasTouch' => $viewport['mobile'],
            ],
            'userAgent' => $viewport['mobile'] ? $this->mobileUserAgent() : null,
            'waitUntil' => $this->gotoOptions()['waitUntil'],
            'timeout' => $this->gotoOptions()['timeout'],
            'waitMs' => $this->waitMs(),
            'settle' => CaptureSettleScript::functionBody(
                sweep: $sweep,
                hideConsent: (bool) config('revisemy.capture.hide_consent', true),
                collectElements: $collect,
            ),
            'fullPage' => $fullPage,
            'collectElements' => $collect,
            'withHtml' => $withHtml,
        ];

        // Browserless ends a session at its own timeout; give it the same budget.
        $target = $endpoint.(str_contains($endpoint, '?') ? '&' : '?').'timeout='.($httpTimeout * 1000);

        $response = $this->send($target, ['code' => self::FUNCTION_CODE, 'context' => $context], $httpTimeout, $label);

        // 404/405: this host has no /function. 400: Browserless rejected the
        // function payload (bad code, timeout, or plan). Either way /screenshot
        // can still render the page, so fall back instead of failing the review.
        if (in_array($response->status(), [400, 404, 405], true)) {
            Log::info('Hosted capture: /function rejected, falling back to /screenshot', [
                'label' => $label,
                'status' => $response->status(),
                'body' => $this->providerSnippet($response),
            ]);

            return null;
        }

        $data = $response->successful() ? $response->json() : null;
        $binary = is_array($data) && is_string($data['image'] ?? null) ? base64_decode($data['image'], true) : false;

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'capture' => $this->failureMessage($label, $response),
            ]);
        }

        return [
            'binary' => $binary,
            'meta' => $baseMeta + [
                'viewport' => $label,
                'css_width' => $viewport['width'],
                'dpr' => $dpr,
                'mobile' => $viewport['mobile'],
                'settled' => (bool) ($data['stable'] ?? false),
            ] + (is_array($data['elements'] ?? null) ? ['elements_source' => 'same_load'] : []),
            'elements' => is_array($data['elements'] ?? null) ? $data['elements'] : null,
            'html' => is_string($data['html'] ?? null) && $data['html'] !== '' ? $data['html'] : null,
        ];
    }

    /**
     * @param  array<string, string>  $source
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    protected function captureViaScreenshot(array $source, array $viewports, array $baseMeta, bool $fullPage, bool $sweep, bool $hideConsent): array
    {
        $endpoint = $this->authenticatedUrl((string) config('revisemy.capture.endpoint'));
        // Browserless runs waitForTimeout before waitForFunction, so the
        // settle (and any scroll sweep) lives in waitForFunction.
        $budgetMs = CaptureSettleScript::budgetMs($sweep);
        $httpTimeout = $this->requestTimeout($budgetMs);
        $isUrl = isset($source['url']);
        $shots = [];

        foreach ($viewports as $viewport) {
            // URL captures: full-page at DPR 1 by default — tall 2× PNGs OOM
            // Cloud's 256MB PHP; narrow viewports may ask for more.
            $dpr = $viewport['dpr'] ?? ($isUrl ? $this->urlDeviceScaleFactor() : max(1, (int) config('revisemy.capture.device_scale_factor', 2)));
            $label = $viewport['label'];

            $payload = $source + [
                'viewport' => [
                    'width' => $viewport['width'],
                    'height' => $viewport['height'],
                    'deviceScaleFactor' => $dpr,
                ] + ($viewport['mobile'] ? ['isMobile' => true, 'hasTouch' => true] : []),
                'options' => ['type' => 'png', 'fullPage' => $fullPage],
                'gotoOptions' => $this->gotoOptions(),
                'waitForTimeout' => $this->waitMs(),
                'waitForFunction' => [
                    'fn' => CaptureSettleScript::functionBody(sweep: $sweep, hideConsent: $hideConsent),
                    'timeout' => $budgetMs + CaptureSettleScript::OUTER_HEADROOM_MS,
                ],
            ];

            if ($viewport['mobile']) {
                $payload['userAgent'] = $this->mobileUserAgent();
            }

            $response = $this->send($endpoint, $payload, $httpTimeout, $label);

            if (! $response->successful() || $response->body() === '') {
                throw ValidationException::withMessages([
                    'capture' => $this->failureMessage($label, $response),
                ]);
            }

            $shots[] = [
                'binary' => $response->body(),
                'meta' => $baseMeta + [
                    'viewport' => $label,
                    'css_width' => $viewport['width'],
                    'dpr' => $dpr,
                    'mobile' => $viewport['mobile'],
                ],
            ];
        }

        return $shots;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function send(string $endpoint, array $payload, int $timeout, string $label): Response
    {
        try {
            return Http::timeout($timeout)->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::warning('Hosted capture request failed', [
                'label' => $label,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'capture' => "[capture_provider_failed] Capture timed out or could not reach the screenshot provider at {$label}. Raise REVISEMY_CAPTURE_TIMEOUT, check the Browserless endpoint/key, or fall back to create_review with images as data URLs.",
            ]);
        }
    }

    protected function failureMessage(string $label, Response $response): string
    {
        $snippet = $this->providerSnippet($response);
        $detail = $snippet !== '' ? " Provider said: {$snippet}." : '';

        return "[capture_provider_failed] Could not capture at {$label} (HTTP {$response->status()}).{$detail} Check Browserless token/quota/endpoint, or fall back to create_review with images as data URLs.";
    }

    protected function providerSnippet(Response $response): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $response->body()) ?? '');

        return $body === '' ? '' : mb_substr($body, 0, 180);
    }

    /**
     * The /function endpoint: configured explicitly, or the screenshot
     * endpoint with /screenshot swapped for /function. Null when neither.
     */
    protected function functionEndpoint(): ?string
    {
        $configured = (string) config('revisemy.capture.function_endpoint');

        if ($configured !== '') {
            return $this->authenticatedUrl($configured);
        }

        $endpoint = (string) config('revisemy.capture.endpoint');
        $swapped = preg_replace('#/screenshot(?=$|\?)#', '/function', $endpoint, 1, $count);

        return $count === 1 ? $this->authenticatedUrl((string) $swapped) : null;
    }

    /**
     * Outer HTTP budget: navigation + settle delay + the page function's own
     * deadline, with headroom so the function always resolves first.
     */
    protected function requestTimeout(int $budgetMs): int
    {
        $timeout = (int) config('revisemy.capture.timeout', 30);

        return $timeout
            + (int) ceil($this->waitMs() / 1000)
            + (int) ceil(($budgetMs + CaptureSettleScript::OUTER_HEADROOM_MS) / 1000)
            + 5;
    }

    /**
     * @return array{waitUntil: string, timeout: int}
     */
    protected function gotoOptions(): array
    {
        return [
            'waitUntil' => (string) config('revisemy.capture.wait_until', 'networkidle2'),
            'timeout' => (int) config('revisemy.capture.timeout', 30) * 1000,
        ];
    }

    protected function waitMs(): int
    {
        return (int) config('revisemy.capture.wait_ms', 1000);
    }

    protected function urlDeviceScaleFactor(): int
    {
        return max(1, (int) config('revisemy.capture.url_device_scale_factor', 1));
    }

    protected function mobileUserAgent(): string
    {
        return (string) config('revisemy.capture.mobile_user_agent');
    }

    /**
     * @return array{width: int, height: int}
     */
    protected function primaryViewport(): array
    {
        $first = array_values((array) config('revisemy.capture.viewports', []))[0] ?? [1280, 800];

        return [
            'width' => (int) ($first[0] ?? $first['width'] ?? 1280),
            'height' => (int) ($first[1] ?? $first['height'] ?? 800),
        ];
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
