<?php

namespace App\Services\Capture;

use App\Services\ScreenshotStorage;
use App\Support\ToolProgress;
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
 * page, waits for the network to go quiet, takes the screenshot, its rail
 * thumbnail and a vision-sized copy, and reads the element map and DOM, so all of them describe
 * exactly the same frame. It measures the page first, so it can shoot at the
 * viewport's full DPR within a pixel budget. Hosts without /function fall back
 * to /screenshot (bytes only, wide viewports at 1×) plus a separate, equally
 * settled /content load.
 */
class HostedCaptureDriver implements CaptureDriver
{
    /**
     * Tallest shot in device pixels. Chrome's screenshot surface tops out at
     * 16,384px; past it, full-page captures repeat or blank out.
     */
    public const MAX_SHOT_HEIGHT = 16_000;

    /**
     * Widest shot in device pixels: WebP holds at most 16,383px a side, and a
     * page that scrolls sideways can measure wider than its viewport.
     */
    public const MAX_SHOT_WIDTH = 16_000;

    /**
     * Shots up to this many pixels stay lossless PNG. Past it, WebP at
     * WEBP_QUALITY keeps text crisp at a fraction of the bytes, so a long
     * page keeps its density instead of being scaled down to fit the cap.
     */
    public const PNG_MAX_PIXELS = 4_000_000;

    public const WEBP_QUALITY = 92;

    /**
     * Crop slices: the whole page at no more than 1x, cut into pieces small
     * enough for PHP to decode (MarkCrop reads 4MP at most), so a mark
     * anywhere on a long page gets a picture.
     */
    public const SLICE_PIXELS = 4_000_000;

    public const MAX_SLICES = 8;

    /**
     * Time for the main shot, thumbnail, vision copy and slices, and their
     * encoding, in /function (ms).
     */
    protected const SHOOT_BUDGET_MS = 16_000;

    /**
     * Longest /function waits for network idle after the settle (ms). The
     * settle's own load gate has already waited for what's on screen.
     */
    protected const IDLE_BUDGET_MS = 3_000;

    /**
     * What each budget may be cut to when the plan's session cap is shorter
     * than they add up to, in the order they give way (ms).
     */
    protected const BUDGET_FLOORS = [
        'sweepMs' => 10_000,
        'loadMs' => 3_000,
        'idleMs' => 1_000,
        'shootMs' => 8_000,
    ];

    /**
     * Runs inside Browserless /function (Puppeteer). Kept free of PHP
     * interpolation; every value arrives through `context`.
     */
    protected const FUNCTION_CODE = <<<'JS'
export default async function ({ page, context }) {
  // Everything before the shots must be done by stopAt, so the session
  // always ends with a picture rather than being cut off mid-settle.
  const stopAt = Date.now() + context.sessionMs - context.reserveMs;
  const left = () => Math.max(0, stopAt - Date.now());

  let userAgent = context.userAgent;
  if (userAgent && !context.viewport.isMobile) {
    // Desktop reads as the Chrome it is, minus "Headless", at the version
    // this browser really runs so its client hints agree.
    const own = await page.browser().userAgent().catch(() => '');
    const major = (own.match(/Chrome\/(\d+)/) || [])[1];
    if (major) {
      userAgent = userAgent.replace(/Chrome\/[\d.]+/, 'Chrome/' + major + '.0.0.0');
    }
  }
  if (userAgent) {
    await page.setUserAgent(userAgent);
  }
  await page.setViewport(context.viewport);
  // A page that never goes quiet still has something to shoot.
  await page.goto(context.url, { waitUntil: context.waitUntil, timeout: context.timeout }).catch((e) => {
    if (!e || e.name !== 'TimeoutError') {
      throw e;
    }
  });
  if (context.waitMs > 0) {
    await new Promise((resolve) => setTimeout(resolve, Math.min(context.waitMs, left())));
  }
  await page.evaluate('(' + context.settle + ')(' + Math.max(1000, left() - context.idleMs - context.againMs) + ')');

  // The sweep and the load gate start fetches the first load never made.
  // Let them land and, if any did, bring the page to rest once more. Pages
  // with an open chat or analytics socket never go idle, so this is short.
  const idleMs = Math.min(context.idleMs, Math.max(0, left() - context.againMs));
  if (idleMs > 0) {
    const finished = () => page.evaluate(() => performance.getEntriesByType('resource').length);
    const before = await finished();
    await page.waitForNetworkIdle({ idleTime: 500, timeout: idleMs, concurrency: 2 }).catch(() => {});
    if ((await finished()) !== before && left() > 500) {
      await page.evaluate((ms) => (window.__rmSettleAgain ? window.__rmSettleAgain(ms) : null), left());
    }
  }

  // The whole document at the viewport's DPR, scaled down only as far as the
  // pixel budget and Chrome's screenshot size need. Past PNG_MAX_PIXELS it is
  // WebP, which holds the density in far fewer bytes; only a shot still over
  // the storage cap is scaled down further.
  const doc = await page.evaluate(() => ({
    width: Math.max(document.documentElement.scrollWidth, document.body ? document.body.scrollWidth : 0, window.innerWidth),
    height: Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0, window.innerHeight),
  }));
  const dpr = context.viewport.deviceScaleFactor || 1;
  const width = doc.width;
  const height = context.fullPage ? doc.height : context.viewport.height;
  const shoot = (type, clip, scale, extra) => page.screenshot({
    type,
    encoding: 'base64',
    captureBeyondViewport: true,
    clip: { x: 0, y: clip.y || 0, width: clip.width, height: clip.height, scale },
    ...extra,
  });
  const pixels = (s) => width * dpr * s * height * dpr * s;
  const encode = async (s) => {
    if (pixels(s) <= context.pngMaxPixels) {
      const png = await shoot('png', { width, height }, s, {});
      if (png.length * 0.75 <= context.maxBytes) {
        return { image: png, format: 'png' };
      }
    }
    try {
      return { image: await shoot('webp', { width, height }, s, { quality: context.webpQuality }), format: 'webp' };
    } catch (e) {
      return { image: await shoot('png', { width, height }, s, {}), format: 'png' };
    }
  };
  let scale = Math.min(1, Math.sqrt(context.maxPixels / pixels(1)), context.maxHeight / (height * dpr), context.maxWidth / (width * dpr));
  let shot = await encode(scale);
  while (shot.image.length * 0.75 > context.maxBytes && scale > 0.25) {
    scale *= 0.85;
    shot = await encode(scale);
  }
  const image = shot.image;
  const shootStarted = Date.now();

  // The rail thumbnail: the top of the page, already small, so the app
  // never decodes a tall capture to make one.
  const thumb = context.thumbWidth > 0
    ? await shoot('jpeg', { width, height: Math.min(height, Math.ceil(width * 1.25)) }, Math.min(1, context.thumbWidth / (width * dpr)), { quality: 75 })
    : null;

  // The vision copy: the top of the page at no more than 1x, small enough
  // that a vision model reads it as sent. Regions found on it map back to
  // the full shot through crop, the share of the shot's height it covers.
  let vision = null;
  if (context.visionEdge > 0) {
    const visionHeight = Math.min(height, Math.max(context.viewport.height, context.visionEdge));
    const fit = Math.min(1, context.visionEdge / Math.max(width, visionHeight), Math.sqrt(context.visionPixels / (width * visionHeight)));
    vision = {
      image: await shoot('jpeg', { width, height: visionHeight }, fit / dpr, { quality: 80 }),
      crop: visionHeight / height,
    };
  }

  // Crop slices: the whole page top to bottom at no more than 1x, in pieces
  // PHP can decode, so a crop for a mark far down a long page has a source.
  // Only needed when the main shot is too big to decode itself.
  const slices = [];
  if (context.slicePixels > 0 && pixels(scale) > context.slicePixels) {
    const sliceHeight = Math.max(
      Math.min(Math.max(Math.floor(context.slicePixels / width), 1600), 4000),
      Math.ceil(height / context.maxSlices),
    );
    const fit = Math.min(1, Math.sqrt(context.slicePixels / (width * sliceHeight)));
    for (let top = 0; top < height && slices.length < context.maxSlices; top += sliceHeight) {
      if (Date.now() - shootStarted > context.shootMs - 1500) {
        break;
      }
      const sliceTall = Math.min(sliceHeight, height - top);
      slices.push({
        image: await shoot('jpeg', { y: top, width, height: sliceTall }, fit / dpr, { quality: 82 }),
        top,
        height: sliceTall,
      });
    }
  }

  const state = await page.evaluate(() => ({
    elements: window.__rmElements || null,
    stable: window.__rmSettleStable === true,
    loaded: window.__rmLoadComplete === true,
    incomplete: window.__rmIncomplete || 0,
  }));
  const html = context.withHtml ? await page.content() : null;

  return {
    data: {
      image,
      format: shot.format,
      css: { width, height },
      thumb,
      vision,
      slices,
      scale,
      html,
      elements: context.collectElements ? state.elements : null,
      stable: state.stable,
      loaded: state.loaded,
      incomplete: state.incomplete,
    },
    type: 'application/json',
  };
}
JS;

    public function enabled(): bool
    {
        return (string) config('revisemy.capture.endpoint') !== '';
    }

    public function captureUrl(string $url, array $viewports): array
    {
        if ($this->functionEndpoint() !== null) {
            try {
                return $this->captureUrlVia('function', $url, $viewports);
            } catch (FunctionUnavailable) {
                // Logged where it was turned down; /screenshot does the lot.
            }
        }

        return $this->captureUrlVia('screenshot', $url, $viewports);
    }

    /**
     * Capture through one path only, never falling back: 'function' or
     * 'screenshot'. revisemy:probe-capture uses it to show what each path
     * does with a page.
     *
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device?: string}>  $viewports
     * @return list<array{binary: string, meta: array<string, mixed>, elements?: array<string, mixed>|null, html?: string|null, thumb?: string|null, vision?: array{binary: string, crop: float}|null}>
     *
     * @throws FunctionUnavailable when /function is missing or turns the request down
     */
    public function captureUrlVia(string $path, string $url, array $viewports): array
    {
        $fullPage = (bool) config('revisemy.capture.url_full_page', true);
        $sweep = $fullPage && CaptureSettleScript::enabled();
        $baseMeta = ['origin' => 'capture', 'page_url' => $url];

        if ($path !== 'function') {
            return $this->captureMeasuredScreenshots($url, $viewports, $baseMeta, $fullPage, $sweep);
        }

        $endpoint = $this->functionEndpoint() ?? throw new FunctionUnavailable(0, 'no /function endpoint is configured');
        $shots = [];

        foreach ($viewports as $index => $viewport) {
            ToolProgress::report('Capturing '.ToolProgress::viewport($viewport['label']));
            $shots[] = $this->captureViaFunction($endpoint, $url, $viewport, $baseMeta, $fullPage, $sweep, withHtml: $index === 0);
        }

        return $shots;
    }

    /**
     * /screenshot can't measure a page before shooting it, so the /content
     * load it needs anyway for the DOM runs first: its element map knows the
     * page's height, which lets a wide viewport shoot at its own DPR when the
     * page is short enough. The DOM and map ride on the first shot, so
     * PageCaptureService doesn't load the page a third time.
     *
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device?: string}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @return list<array{binary: string, meta: array<string, mixed>, elements?: array<string, mixed>|null, html?: string|null}>
     */
    protected function captureMeasuredScreenshots(string $url, array $viewports, array $baseMeta, bool $fullPage, bool $sweep): array
    {
        $html = null;
        $elements = null;

        try {
            $dom = $this->captureDom($url);
        } catch (\Throwable $e) {
            Log::warning('Hosted DOM capture failed', ['url' => $url, 'error' => $e->getMessage()]);
            $dom = null;
        }

        if ($dom !== null) {
            [$html, $elements] = PageCaptureService::extractEmbeddedElements($dom);
        }

        $measured = is_numeric($elements['docHeight'] ?? null) && $fullPage
            ? ['width' => (int) ($elements['viewport']['width'] ?? $this->primaryViewport()['width']), 'height' => (int) $elements['docHeight']]
            : null;

        $shots = $this->captureViaScreenshot(
            ['url' => $url],
            $viewports,
            $baseMeta,
            fullPage: $fullPage,
            sweep: $sweep,
            hideConsent: (bool) config('revisemy.capture.hide_consent', true),
            measured: $measured,
        );

        if ($shots !== [] && $html !== null) {
            $shots[0]['html'] = $html;

            if ($elements !== null) {
                $shots[0]['elements'] = $elements;
                $shots[0]['meta']['elements_source'] = 'separate_load';
            }
        }

        return $shots;
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
                        hideChat: (bool) config('revisemy.capture.hide_chat', true),
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
     * One viewport through Browserless /function.
     *
     * @param  array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device?: string}  $viewport
     * @param  array<string, mixed>  $baseMeta
     * @return array{binary: string, meta: array<string, mixed>, elements: array<string, mixed>|null, html: string|null, thumb: string|null, vision: array{binary: string, crop: float}|null}
     *
     * @throws FunctionUnavailable when the host has no /function or turns the request down
     */
    protected function captureViaFunction(string $endpoint, string $url, array $viewport, array $baseMeta, bool $fullPage, bool $sweep, bool $withHtml): array
    {
        $collect = (bool) config('revisemy.capture.collect_elements', true);
        $budget = $this->functionBudget($sweep, $collect);
        $httpTimeout = $budget['httpTimeout'];
        $dpr = $viewport['dpr'] ?? $this->urlDeviceScaleFactor();
        $label = $viewport['label'];
        $started = microtime(true);

        $context = [
            'url' => $url,
            'viewport' => [
                'width' => $viewport['width'],
                'height' => $viewport['height'],
                'deviceScaleFactor' => $dpr,
                'isMobile' => $viewport['mobile'],
                'hasTouch' => $viewport['mobile'],
            ],
            'userAgent' => CaptureDevice::userAgent($viewport),
            'waitUntil' => $this->gotoOptions()['waitUntil'],
            'timeout' => $this->gotoOptions()['timeout'],
            'waitMs' => $this->waitMs(),
            'settle' => CaptureSettleScript::functionBody(
                sweep: $sweep,
                hideConsent: (bool) config('revisemy.capture.hide_consent', true),
                collectElements: $collect,
                hideChat: (bool) config('revisemy.capture.hide_chat', true),
                sweepBudgetMs: $budget['sweepMs'],
                loadBudgetMs: $budget['loadMs'],
            ),
            'sessionMs' => $budget['sessionMs'],
            // Kept back from stopAt for the shots and the reply.
            'reserveMs' => $budget['shootMs'] + CaptureSettleScript::OUTER_HEADROOM_MS,
            'idleMs' => $budget['idleMs'],
            'againMs' => CaptureSettleScript::againBudgetMs($collect),
            'shootMs' => $budget['shootMs'],
            'fullPage' => $fullPage,
            'maxPixels' => max(1_000_000, (int) config('revisemy.capture.max_pixels', 41_000_000)),
            'maxHeight' => self::MAX_SHOT_HEIGHT,
            'maxWidth' => self::MAX_SHOT_WIDTH,
            'maxBytes' => ScreenshotStorage::MAX_CAPTURE_BYTES,
            'pngMaxPixels' => self::PNG_MAX_PIXELS,
            'webpQuality' => self::WEBP_QUALITY,
            'thumbWidth' => ScreenshotStorage::THUMB_WIDTH,
            'visionEdge' => ScreenshotStorage::VISION_EDGE,
            'visionPixels' => ScreenshotStorage::VISION_PIXELS,
            'slicePixels' => self::SLICE_PIXELS,
            'maxSlices' => self::MAX_SLICES,
            'collectElements' => $collect,
            'withHtml' => $withHtml,
        ];

        // Browserless ends a session at its own timeout, and turns the whole
        // request down when that is over the plan's cap; functionBudget()
        // has already fitted every wait inside it.
        $target = $endpoint.(str_contains($endpoint, '?') ? '&' : '?').'timeout='.$budget['sessionMs'];

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

            throw new FunctionUnavailable($response->status(), $this->providerSnippet($response));
        }

        $data = $response->successful() ? $response->json() : null;

        // The function returns {data, type}. Some Browserless versions send
        // back only `data`; others send the whole wrapper, so unwrap it.
        if (is_array($data) && ! isset($data['image']) && is_array($data['data'] ?? null)) {
            $data = $data['data'];
        }

        $binary = is_array($data) && is_string($data['image'] ?? null) ? base64_decode($data['image'], true) : false;

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'capture' => $this->failureMessage($label, $response),
            ]);
        }

        $thumb = is_string($data['thumb'] ?? null) ? base64_decode($data['thumb'], true) : false;
        $scale = is_numeric($data['scale'] ?? null) ? min(1.0, max(0.0, (float) $data['scale'])) : 1.0;
        $vision = is_string($data['vision']['image'] ?? null) ? base64_decode($data['vision']['image'], true) : false;
        $crop = is_numeric($data['vision']['crop'] ?? null) ? min(1.0, (float) $data['vision']['crop']) : 0.0;
        $cssHeight = is_numeric($data['css']['height'] ?? null) ? (int) $data['css']['height'] : 0;

        return [
            'binary' => $binary,
            'meta' => $baseMeta + [
                'viewport' => $label,
                'css_width' => $viewport['width'],
                // What the pixels hold per CSS px once the budget had its say.
                'dpr' => $scale >= 0.995 ? $dpr : round($dpr * $scale, 2),
                'requested_dpr' => $dpr,
                'mobile' => $viewport['mobile'],
                'settled' => (bool) ($data['stable'] ?? false),
                'loaded' => (bool) ($data['loaded'] ?? false),
                'incomplete_images' => (int) ($data['incomplete'] ?? 0),
                'capture_ms' => (int) round((microtime(true) - $started) * 1000),
            ] + (is_array($data['elements'] ?? null) ? ['elements_source' => 'same_load'] : [])
              + (is_numeric($data['css']['width'] ?? null) && $cssHeight > 0
                  ? ['doc_width' => (int) $data['css']['width'], 'doc_height' => $cssHeight]
                  : []),
            'elements' => is_array($data['elements'] ?? null) ? $data['elements'] : null,
            'html' => is_string($data['html'] ?? null) && $data['html'] !== '' ? $data['html'] : null,
            'thumb' => is_string($thumb) && $thumb !== '' ? $thumb : null,
            'vision' => is_string($vision) && $vision !== '' && $crop > 0 ? ['binary' => $vision, 'crop' => $crop] : null,
            'slices' => $this->decodeSlices($data['slices'] ?? null, $cssHeight),
        ];
    }

    /**
     * Crop slices from the reply, each with the share of the shot's height
     * it starts at and covers. Anything malformed is dropped.
     *
     * @return list<array{binary: string, top: float, height: float}>
     */
    protected function decodeSlices(mixed $slices, int $cssHeight): array
    {
        if (! is_array($slices) || $cssHeight < 1) {
            return [];
        }

        $decoded = [];

        foreach (array_slice($slices, 0, self::MAX_SLICES) as $slice) {
            $binary = is_string($slice['image'] ?? null) ? base64_decode($slice['image'], true) : false;

            if (! is_string($binary) || $binary === '' || ! is_numeric($slice['top'] ?? null) || ! is_numeric($slice['height'] ?? null)) {
                continue;
            }

            $decoded[] = [
                'binary' => $binary,
                'top' => round(max(0.0, min(1.0, (float) $slice['top'] / $cssHeight)), 5),
                'height' => round(max(0.0, min(1.0, (float) $slice['height'] / $cssHeight)), 5),
            ];
        }

        return $decoded;
    }

    /**
     * How long each part of a /function session may take, fitted inside the
     * plan's session cap. When the waits add up to more than the cap, the
     * scroll sweep gives way first, then the load gate, the network-idle
     * wait and the shots, each only down to its floor.
     *
     * @return array{sweepMs: int, loadMs: int, idleMs: int, shootMs: int, sessionMs: int, httpTimeout: int}
     */
    public function functionBudget(bool $sweep, bool $collect): array
    {
        $budget = [
            'sweepMs' => $sweep ? CaptureSettleScript::timeoutMs() : 0,
            'loadMs' => CaptureSettleScript::loadTimeoutMs(),
            'idleMs' => min(self::IDLE_BUDGET_MS, CaptureSettleScript::loadTimeoutMs()),
            'shootMs' => self::SHOOT_BUDGET_MS,
        ];

        // Navigation, settle, network idle, a second settle, then the shots.
        $needed = fn (): int => $this->gotoOptions()['timeout'] + $this->waitMs()
            + CaptureSettleScript::budgetMs($sweep, $collect, $budget['sweepMs'], $budget['loadMs'])
            + $budget['idleMs']
            + CaptureSettleScript::againBudgetMs($collect)
            + $budget['shootMs']
            + CaptureSettleScript::OUTER_HEADROOM_MS;

        $cap = (int) config('revisemy.capture.session_max_ms');
        $over = $cap > 0 ? $needed() - $cap : 0;

        foreach (self::BUDGET_FLOORS as $key => $floor) {
            if ($over <= 0) {
                break;
            }

            $cut = min($over, max(0, $budget[$key] - $floor));
            $budget[$key] -= $cut;
            $over -= $cut;
        }

        $sessionMs = $cap > 0 ? min($needed(), $cap) : $needed();

        return $budget + [
            'sessionMs' => $sessionMs,
            'httpTimeout' => (int) ceil($sessionMs / 1000) + 5,
        ];
    }

    /**
     * @param  array<string, string>  $source
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device?: string}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @param  array{width: int, height: int}|null  $measured  the page's CSS size from a load before the shots
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    protected function captureViaScreenshot(array $source, array $viewports, array $baseMeta, bool $fullPage, bool $sweep, bool $hideConsent, ?array $measured = null): array
    {
        $endpoint = $this->authenticatedUrl((string) config('revisemy.capture.endpoint'));
        // Browserless runs waitForTimeout before waitForFunction, so the
        // settle (and any scroll sweep) lives in waitForFunction.
        $budgetMs = CaptureSettleScript::budgetMs($sweep);
        $httpTimeout = $this->requestTimeout($budgetMs);
        $isUrl = isset($source['url']);
        $shots = [];

        foreach ($viewports as $viewport) {
            ToolProgress::report('Capturing '.ToolProgress::viewport($viewport['label']));

            // A full-page URL shot here can't be scaled to fit, so a wide
            // viewport only gets its own DPR when the measured page is short
            // enough (see CaptureDevice::measuredDpr).
            $dpr = $isUrl
                ? CaptureDevice::measuredDpr($viewport, $measured)
                : ($viewport['dpr'] ?? max(1, (int) config('revisemy.capture.device_scale_factor', 2)));
            $label = $viewport['label'];
            $userAgent = CaptureDevice::userAgent($viewport);

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
                    // Chat launchers only float over pages, never over email.
                    'fn' => CaptureSettleScript::functionBody(sweep: $sweep, hideConsent: $hideConsent, hideChat: $isUrl && (bool) config('revisemy.capture.hide_chat', true)),
                    'timeout' => $budgetMs + CaptureSettleScript::OUTER_HEADROOM_MS,
                ],
            ];

            // Browserless types userAgent from Puppeteer's setUserAgent: an
            // object ({userAgent: …}) on current versions, a string on older
            // ones. The string started coming back as a 400 ("userAgent" must
            // be object), which failed every mobile shot and so the whole
            // capture. Send the object; a server that wants the string gets
            // asked once more with it.
            if ($userAgent !== null) {
                $payload['userAgent'] = ['userAgent' => $userAgent];
            }

            $response = $this->send($endpoint, $payload, $httpTimeout, $label);

            if ($userAgent !== null && $response->status() === 400 && str_contains($response->body(), 'userAgent')) {
                $payload['userAgent'] = $userAgent;
                $response = $this->send($endpoint, $payload, $httpTimeout, $label);
            }

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
