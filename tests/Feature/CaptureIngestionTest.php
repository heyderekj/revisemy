<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Services\DocumentIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaptureIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngBinary(): string
    {
        return hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        );
    }

    protected function tinyPngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode($this->tinyPngBinary());
    }

    protected function setUpEnv(): string
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.capture.driver' => 'hosted',
            'revisemy.capture.endpoint' => 'https://capture.test/screenshot',
            'revisemy.capture.api_key' => 'cap-key',
        ]);
        Queue::fake();

        return $this->postJson('/api/try-token')->json('token');
    }

    /**
     * @return array<string, mixed>
     */
    protected function functionResult(?string $html = '<html><body><h1>Hero headline</h1></body></html>'): array
    {
        return [
            'image' => base64_encode($this->tinyPngBinary()),
            'format' => 'png',
            'css' => ['width' => 1280, 'height' => 2400],
            'elements' => [
                'version' => 2,
                'docWidth' => 1280,
                'docHeight' => 2400,
                'viewport' => ['width' => 1280, 'height' => 800],
                'elements' => [
                    ['selector' => '#hero > h1', 'tag' => 'h1', 'kind' => 'Heading', 'text' => 'Hero headline', 'src' => null, 'box' => ['x' => 40, 'y' => 120, 'w' => 600, 'h' => 64]],
                ],
            ],
            'html' => $html,
            'stable' => true,
            'loaded' => true,
            'incomplete' => 0,
            'scale' => 1,
        ];
    }

    public function test_capture_url_settles_each_viewport_in_one_page_session(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/function*' => Http::response($this->functionResult()),
            'capture.test/*' => Http::response('unexpected', 500),
        ]);

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $response->assertJsonPath('type', 'website');

        $shots = $response->json('screenshots');
        $this->assertCount(3, $shots);
        $this->assertSame('desktop-1280', $shots[0]['meta']['viewport']);
        $this->assertSame('mobile-375', $shots[1]['meta']['viewport']);
        $this->assertSame('tablet-768', $shots[2]['meta']['viewport']);
        $this->assertSame('capture', $shots[0]['meta']['origin']);
        $this->assertSame('same_load', $shots[0]['meta']['elements_source']);
        $this->assertTrue($shots[0]['meta']['settled']);
        $this->assertTrue($shots[0]['meta']['loaded']);
        $this->assertSame(0, $shots[0]['meta']['incomplete_images']);
        $this->assertSame(2, $shots[0]['meta']['dpr']);
        $this->assertSame(2, $shots[0]['meta']['requested_dpr']);

        Queue::assertNothingPushed();
        // One request per viewport; the DOM rides along, no separate /content.
        Http::assertSentCount(3);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $context = $body['context'] ?? [];
            $settle = (string) ($context['settle'] ?? '');

            return str_contains($request->url(), 'capture.test/function?token=cap-key&timeout=')
                && str_contains((string) ($body['code'] ?? ''), 'page.screenshot')
                && ($context['url'] ?? null) === 'https://example.com'
                && str_contains((string) ($body['code'] ?? ''), 'waitForNetworkIdle')
                && ($context['viewport']['width'] ?? null) === 1280
                && ($context['viewport']['deviceScaleFactor'] ?? null) === 2
                && ($context['viewport']['isMobile'] ?? null) === false
                // Desktop reads as Chrome, not HeadlessChrome, which bot walls turn away.
                && str_contains((string) ($context['userAgent'] ?? ''), 'Chrome/')
                && ! str_contains((string) ($context['userAgent'] ?? ''), 'Headless')
                && ($context['fullPage'] ?? null) === true
                // 2× desktop holds to 8,000 CSS px; past 4MP it's WebP, not a shrunk PNG.
                && ($context['maxPixels'] ?? null) === 41_000_000
                && ($context['pngMaxPixels'] ?? null) === 4_000_000
                && ($context['webpQuality'] ?? null) === 92
                && ($context['maxWidth'] ?? null) === 16_000
                && ($context['slicePixels'] ?? null) === 4_000_000
                && str_contains((string) ($body['code'] ?? ''), "'webp'")
                // The session fits the plan's two-minute cap, with the shots held back.
                && str_ends_with($request->url(), '&timeout=120000')
                && ($context['sessionMs'] ?? null) === 120_000
                && ($context['reserveMs'] ?? 0) > ($context['shootMs'] ?? 0)
                && ($context['maxHeight'] ?? null) === 16_000
                && ($context['maxBytes'] ?? null) === 16 * 1024 * 1024
                && ($context['thumbWidth'] ?? null) === 320
                && ($context['visionEdge'] ?? null) === 1568
                && ($context['visionPixels'] ?? null) === 1_150_000
                && str_contains((string) ($body['code'] ?? ''), 'visionHeight')
                && ($context['idleMs'] ?? 0) > 0
                && ($context['withHtml'] ?? null) === true
                && ($context['waitMs'] ?? null) === (int) config('revisemy.capture.wait_ms')
                && ($context['waitUntil'] ?? null) === (string) config('revisemy.capture.wait_until', 'networkidle2')
                && str_contains($settle, '__rmSettleDone')
                && str_contains($settle, '__rmSettleAgain')
                && str_contains($settle, 'loadGate')
                && str_contains($settle, 'iframe[loading="lazy"]')
                && str_contains($settle, 'scrollbar-width:none')
                && str_contains($settle, '"loadBudgetMs":8000')
                && str_contains($settle, 'getAnimations')
                && str_contains($settle, '"sweep":true')
                && str_contains($settle, '"freeze":true')
                && str_contains($settle, '"hideConsent":true')
                && str_contains($settle, '"hideChat":true')
                && str_contains($settle, '#intercom-container')
                && str_contains($settle, 'version: 2')
                && str_contains($settle, 'hardStop')
                && str_contains($settle, '"collectElements":true');
        });

        // The phone renders as a phone: touch, isMobile, mobile UA, 2×.
        Http::assertSent(function ($request) {
            $context = $request->data()['context'] ?? [];

            return ($context['viewport']['width'] ?? null) === 375
                && ($context['viewport']['deviceScaleFactor'] ?? null) === 2
                && ($context['viewport']['isMobile'] ?? null) === true
                && ($context['viewport']['hasTouch'] ?? null) === true
                && str_contains((string) ($context['userAgent'] ?? ''), 'iPhone')
                && ($context['withHtml'] ?? null) === false;
        });

        // The tablet renders as an iPad at 2×, not a stretched phone.
        Http::assertSent(function ($request) {
            $context = $request->data()['context'] ?? [];

            return ($context['viewport']['width'] ?? null) === 768
                && ($context['viewport']['deviceScaleFactor'] ?? null) === 2
                && str_contains((string) ($context['userAgent'] ?? ''), 'iPad');
        });

        $review = Review::query()->firstOrFail();
        $this->assertStringContainsString('Hero headline', (string) $review->domHtml());

        $desktop = $review->screenshots()->orderBy('sort_order')->firstOrFail();
        $this->assertSame(1, $desktop->meta['element_count']);
        $this->assertSame(2, $desktop->meta['elements_version']);
        $this->assertSame(['doc_width' => 1280, 'doc_height' => 2400], array_intersect_key($desktop->meta, ['doc_width' => 1, 'doc_height' => 1]));
        $this->assertSame('png', $desktop->meta['format']);
        Storage::disk('public')->assertExists($desktop->meta['elements_path']);
        Storage::disk('public')->assertExists($desktop->meta['elements_pick_path']);
        $this->assertSame('#hero > h1', $desktop->elementMap()['elements'][0]['selector']);

        // The agent sees what was captured, never where the app keeps it.
        $this->assertArrayNotHasKey('elements_path', $shots[0]['meta']);
        $this->assertArrayNotHasKey('elements_pick_path', $shots[0]['meta']);
        $this->assertSame(1, $shots[0]['meta']['element_count']);
    }

    public function test_capture_url_falls_back_to_screenshot_when_function_is_missing(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/function*' => Http::response('Not Found', 404),
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $this->assertCount(3, $response->json('screenshots'));
        // One /function probe, then /screenshot per viewport.
        Http::assertSentCount(4);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $viewport = $body['viewport'] ?? [];
            $goto = $body['gotoOptions'] ?? [];
            $options = $body['options'] ?? [];
            $fn = (string) ($body['waitForFunction']['fn'] ?? '');

            return str_contains($request->url(), '/screenshot')
                && ($viewport['deviceScaleFactor'] ?? null) === 1
                && ! array_key_exists('isMobile', $viewport)
                && ($options['fullPage'] ?? null) === true
                && ($body['waitForTimeout'] ?? null) === (int) config('revisemy.capture.wait_ms')
                && ($goto['waitUntil'] ?? null) === (string) config('revisemy.capture.wait_until', 'networkidle2')
                && str_contains($fn, '__rmSettleDone')
                && str_contains($fn, 'getAnimations')
                && str_contains($fn, '"sweep":true')
                && ($body['waitForFunction']['timeout'] ?? 0) > 45_000;
        });

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/screenshot')
                && ($body['viewport']['width'] ?? null) === 375
                && ($body['viewport']['isMobile'] ?? null) === true
                && ($body['viewport']['deviceScaleFactor'] ?? null) === 2
                && str_contains((string) ($body['userAgent']['userAgent'] ?? ''), 'iPhone');
        });

        // /screenshot can't measure the page first, so the tablet stays at 1×.
        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/screenshot')
                && ($body['viewport']['width'] ?? null) === 768
                && ($body['viewport']['deviceScaleFactor'] ?? null) === 1
                && str_contains((string) ($body['userAgent']['userAgent'] ?? ''), 'iPad');
        });
    }

    public function test_function_capture_reads_a_reply_still_wrapped_in_data(): void
    {
        $token = $this->setUpEnv();

        // Production's Browserless sends the function's whole {data, type}
        // back instead of unwrapping it; that used to fail every capture.
        Http::fake([
            'capture.test/function*' => Http::response([
                'data' => [
                    'image' => base64_encode($this->tinyPngBinary()),
                    'loaded' => true,
                    'stable' => true,
                    'elements' => ['elements' => []],
                ],
                'type' => 'application/json',
            ]),
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Wrapped reply',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $desktop = Review::query()->firstOrFail()->screenshots()->orderBy('sort_order')->firstOrFail();

        $this->assertSame('capture', $desktop->meta['origin']);
        $this->assertTrue($desktop->meta['loaded']);
        $this->assertSame('same_load', $desktop->meta['elements_source']);
    }

    public function test_function_capture_keeps_the_thumbnail_the_browser_made(): void
    {
        $token = $this->setUpEnv();

        // Over 4MP, so GD would skip it and the rail would load the full image.
        $big = imagecreatetruecolor(2560, 2000);
        ob_start();
        imagepng($big, null, 9);
        $png = (string) ob_get_clean();
        $thumb = 'browser-made-thumbnail';

        Http::fake([
            'capture.test/function*' => Http::response([
                'image' => base64_encode($png),
                'thumb' => base64_encode($thumb),
                'scale' => 0.75,
                'loaded' => false,
                'incomplete' => 2,
                'stable' => true,
            ]),
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Tall page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $desktop = Review::query()->firstOrFail()->screenshots()->orderBy('sort_order')->firstOrFail();

        $this->assertNotNull($desktop->thumb_path);
        $this->assertSame($thumb, Storage::disk('public')->get($desktop->thumb_path));
        // Asked for 2×, scaled to fit the budget: 1.5 pixels per CSS px.
        $this->assertSame(1.5, $desktop->meta['dpr']);
        $this->assertSame(2, $desktop->meta['requested_dpr']);
        $this->assertSame(1.5, $desktop->pixelRatio());
        $this->assertFalse($desktop->meta['loaded']);
        $this->assertSame(2, $desktop->meta['incomplete_images']);
    }

    public function test_function_session_timeout_is_clamped_to_the_plan(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.session_max_ms' => 60_000]);

        Http::fake(['capture.test/function*' => Http::response($this->functionResult())]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '&timeout=60000'));
    }

    /** Current Browserless wants an object; an older one that wants the string is asked again. */
    public function test_mobile_capture_retries_with_a_string_user_agent_for_older_browserless(): void
    {
        $token = $this->setUpEnv();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/function')) {
                return Http::response('{"error":"Invalid function code"}', 400);
            }

            if (is_array($request->data()['userAgent'] ?? null)) {
                return Http::response('POST Body validation failed: "userAgent" must be string', 400);
            }

            return Http::response($this->tinyPngBinary());
        });

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated()->assertJsonCount(3, 'screenshots');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/screenshot')
            && is_string($request->data()['userAgent'] ?? null)
            && str_contains($request->data()['userAgent'], 'iPhone'));
    }

    /** What production's Browserless answered on 2026-10-10 to a string userAgent. */
    public function test_mobile_capture_sends_the_user_agent_as_an_object(): void
    {
        $token = $this->setUpEnv();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/function')) {
                return Http::response('{"error":"Invalid function code"}', 400);
            }

            if (is_string($request->data()['userAgent'] ?? null)) {
                return Http::response('POST Body validation failed: "userAgent" must be object', 400);
            }

            return Http::response($this->tinyPngBinary());
        });

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated()->assertJsonCount(3, 'screenshots');

        Http::assertNotSent(fn ($request) => is_string($request->data()['userAgent'] ?? null));
    }

    public function test_capture_url_falls_back_to_screenshot_when_function_rejects_the_payload(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/function*' => Http::response('{"error":"Invalid function code"}', 400),
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $this->assertCount(3, $response->json('screenshots'));
        Http::assertSentCount(4);
    }

    public function test_freezing_animations_can_be_turned_off(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.freeze_animations' => false]);

        Http::fake(['capture.test/function*' => Http::response($this->functionResult())]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        Http::assertSent(fn ($request) => str_contains((string) ($request->data()['context']['settle'] ?? ''), '"freeze":false'));
    }

    public function test_html_source_renders_an_email_review(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Welcome email',
            'html' => '<table><tr><td>Hello</td></tr></table>',
        ])->assertCreated();

        $response->assertJsonPath('type', 'email');
        $this->assertCount(1, $response->json('screenshots'));
        $this->assertSame('html', $response->json('screenshots.0.meta.origin'));

        // Email HTML still settles (fonts, CSS animation) but has no
        // scroll-triggered reveals or consent banners — no sweep, no hiding.
        Http::assertSent(function ($request) {
            $body = $request->data();
            $fn = (string) ($body['waitForFunction']['fn'] ?? '');

            return ($body['html'] ?? null) !== null
                && str_contains($request->url(), '/screenshot')
                && str_contains($fn, 'getAnimations')
                && str_contains($fn, '"sweep":false')
                && str_contains($fn, '"hideConsent":false')
                && str_contains($fn, '"collectElements":false');
        });
    }

    public function test_multiple_sources_are_rejected(): void
    {
        $token = $this->setUpEnv();

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Too many sources',
            'page_url' => 'https://example.com',
            'capture_url' => true,
            'images' => [$this->tinyPngDataUrl()],
        ])->assertUnprocessable();
    }

    public function test_no_source_is_rejected(): void
    {
        $token = $this->setUpEnv();

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Nothing to look at',
        ])->assertUnprocessable();
    }

    public function test_capture_url_requires_page_url(): void
    {
        $token = $this->setUpEnv();

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'No url',
            'capture_url' => true,
        ])->assertUnprocessable();
    }

    /** A failed capture still opens the review, so the human gets a link. */
    public function test_capture_off_still_opens_a_review(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.driver' => null]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Capture off',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $review = Review::query()->firstOrFail();
        $this->assertStringContainsString('[capture_not_configured]', (string) $review->context);
        $this->assertSame('capture_failed', $review->screenshots()->firstOrFail()->meta['origin'] ?? null);
    }

    public function test_capture_provider_failure_is_named_on_the_review(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/*' => Http::response('nope', 502),
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Provider down',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $context = (string) Review::query()->firstOrFail()->context;
        $this->assertStringContainsString('[capture_provider_failed]', $context);
        $this->assertStringContainsString('HTTP 502', $context);
        $this->assertStringContainsString('Provider said: nope', $context);
    }

    public function test_plain_image_uploads_still_work(): void
    {
        $token = $this->setUpEnv();

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Classic upload',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->assertJsonPath('type', 'ui');
    }

    public function test_pdf_ingestion_renders_pages_or_reports_missing_imagick(): void
    {
        $token = $this->setUpEnv();

        $pdf = base64_encode(
            "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\n%%EOF"
        );

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Deck',
            'pdf' => $pdf,
        ]);

        // Not every host can rasterise a PDF: the extension may be missing, or
        // present with the PDF coder revoked by ImageMagick policy (the
        // Debian/Ubuntu default, and what CI runners ship). Branch on what
        // actually happened rather than predicting the environment — the
        // contract is that an unsupported host says so actionably.
        if (! DocumentIngestionService::supportsPdf() || $response->status() === 422) {
            $response->assertUnprocessable();
            $message = (string) collect($response->json('errors'))->flatten()->first();

            $this->assertTrue(
                str_contains($message, 'Imagick') || str_contains($message, 'policy'),
                "PDF failure should name Imagick or the policy, got: {$message}"
            );

            return;
        }

        $response->assertCreated();
        $this->assertSame('presentation', $response->json('type'));
        $this->assertSame('pdf', $response->json('screenshots.0.meta.origin'));
        $this->assertSame(1, $response->json('screenshots.0.meta.page'));
    }

    public function test_function_capture_keeps_the_vision_copy_the_browser_made(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/function*' => Http::response([
                'image' => base64_encode($this->tinyPngBinary()),
                'vision' => ['image' => base64_encode('browser-made-vision-copy'), 'crop' => 0.25],
                'scale' => 1,
                'stable' => true,
            ]),
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Tall page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $desktop = Review::query()->firstOrFail()->screenshots()->orderBy('sort_order')->firstOrFail();

        $this->assertStringEndsWith('.vision.jpg', $desktop->meta['vision_path']);
        $this->assertSame('browser-made-vision-copy', Storage::disk('public')->get($desktop->meta['vision_path']));
        $this->assertSame(0.25, $desktop->meta['vision_crop']);
    }

    public function test_function_capture_without_a_vision_copy_stores_none(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/function*' => Http::response([
                'image' => base64_encode($this->tinyPngBinary()),
                'vision' => ['image' => base64_encode('no-crop'), 'crop' => 0],
                'stable' => true,
            ]),
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Old host',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $desktop = Review::query()->firstOrFail()->screenshots()->orderBy('sort_order')->firstOrFail();

        $this->assertArrayNotHasKey('vision_path', $desktop->meta);
    }

    public function test_screenshots_get_rail_thumbnails(): void
    {
        $token = $this->setUpEnv();

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Thumbnails',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        $shot = Review::query()->firstOrFail()->screenshots()->firstOrFail();

        $this->assertNotNull($shot->thumb_path);
        Storage::disk('public')->assertExists($shot->thumb_path);
        $this->assertStringContainsString('/shots/'.$shot->id.'/thumb', $shot->thumbUrl());

        // Legacy screenshots without a stored thumb fall back to the original.
        $shot->update(['thumb_path' => null]);
        $this->assertSame($shot->url(), $shot->thumbUrl());

        // Images are opened with the guest token. The owner token never
        // appears in an <img src> a guest can read.
        $review = $shot->review;
        $this->assertStringContainsString('/r/'.$review->share_token.'/shots/'.$shot->id, $shot->url());
        $this->assertStringNotContainsString($review->token, $shot->url());
    }

    public function test_url_capture_stores_dom_snapshot(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.content_endpoint' => 'https://capture.test/content']);

        $embedded = json_encode(['docWidth' => 1280, 'docHeight' => 900, 'elements' => [
            ['selector' => 'h1', 'tag' => 'h1', 'kind' => 'Heading', 'text' => 'Hero headline', 'src' => null, 'box' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 40]],
        ]]);

        // No /function on this host: the DOM and element map come from a
        // separate, equally settled /content load.
        Http::fake([
            'capture.test/function*' => Http::response('Not Found', 404),
            'capture.test/content*' => Http::response('<html><head><style data-rm-capture="">*{}</style></head><body><h1>Hero headline</h1><script type="application/json" id="__rm-elements">'.$embedded.'</script></body></html>'),
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $review = Review::query()->firstOrFail();

        $this->assertSame(Review::SOURCE_URL, $review->sourceKind());
        $this->assertNotNull($review->dom_path);
        $this->assertStringContainsString('Hero headline', (string) $review->domHtml());
        $this->assertStringNotContainsString('__rm-elements', (string) $review->domHtml());
        $this->assertStringNotContainsString('data-rm-capture', (string) $review->domHtml());

        $desktop = $review->screenshots()->orderBy('sort_order')->firstOrFail();
        $this->assertSame('separate_load', $desktop->meta['elements_source']);
        $this->assertSame('h1', $desktop->elementMap()['elements'][0]['selector']);

        Http::assertSent(function ($request) {
            $fn = (string) ($request->data()['waitForFunction']['fn'] ?? '');

            return str_contains($request->url(), '/content')
                && str_contains($fn, '"embedElements":true')
                && ($request->data()['viewport']['width'] ?? null) === 1280;
        });
    }

    public function test_dom_capture_failure_still_creates_the_review(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.content_endpoint' => 'https://capture.test/content']);

        Http::fake([
            'capture.test/function*' => Http::response('Not Found', 404),
            'capture.test/content*' => Http::response('nope', 500),
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $review = Review::query()->firstOrFail();
        $this->assertNull($review->dom_path);
        $this->assertNull($review->domHtml());
    }

    public function test_html_review_stores_submitted_html_as_dom_snapshot(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $html = '<table><tr><td>Hello</td></tr></table>';

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Welcome email',
            'html' => $html,
        ])->assertCreated();

        $review = Review::query()->firstOrFail();

        $this->assertSame(Review::SOURCE_HTML, $review->sourceKind());
        $this->assertSame($html, $review->domHtml());
    }

    public function test_plain_uploads_resolve_to_the_image_source_kind(): void
    {
        $token = $this->setUpEnv();

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Classic upload',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        $review = Review::query()->firstOrFail();

        $this->assertSame(Review::SOURCE_IMAGE, $review->sourceKind());
        $this->assertNull($review->dom_path);
    }

    public function test_oversized_capture_is_downscaled_under_the_cap(): void
    {
        // Decoding a >16MB capture is inherently memory-heavy, and by the time
        // this runs in the full suite the framework baseline is ~90MB — the
        // 128MB CLI default left only a few MB of headroom, so the process
        // intermittently died mid-test. Match Cloud's 256MB worker limit here.
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');
        $this->beforeApplicationDestroyed(fn () => ini_set('memory_limit', $previousLimit));

        $token = $this->setUpEnv();

        // An uncompressed PNG (level 0) blows past the 16MB cap without needing
        // slow noise generation: 2400×2400 truecolor ≈ 17.3MB on disk. Encode
        // via a temp file so the output buffer doesn't hold extra copies.
        $image = imagecreatetruecolor(2400, 2400);
        $path = tempnam(sys_get_temp_dir(), 'oversized-capture');
        imagepng($image, $path, 0);
        imagedestroy($image);
        $binary = (string) file_get_contents($path);
        unlink($path);

        $this->assertGreaterThan(16 * 1024 * 1024, strlen($binary));

        Http::fake(['capture.test/*' => Http::response($binary)]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Huge capture',
            'html' => '<p>big</p>',
        ])->assertCreated();

        $review = Review::query()->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();
        $this->assertLessThanOrEqual(16 * 1024 * 1024, strlen(Storage::disk('public')->get($shot->path)));
    }

    public function test_moderate_capture_keeps_png_without_jpeg_reencode(): void
    {
        $token = $this->setUpEnv();

        // Under the 16MB cap — should persist the original PNG bytes unchanged.
        $image = imagecreatetruecolor(1200, 1200);
        ob_start();
        imagepng($image, null, 0);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        $this->assertGreaterThan(1024 * 1024, strlen($binary));
        $this->assertLessThanOrEqual(16 * 1024 * 1024, strlen($binary));

        Http::fake(['capture.test/*' => Http::response($binary)]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Sharp capture',
            'html' => '<p>ok</p>',
        ])->assertCreated();

        $shot = Review::query()->firstOrFail()->screenshots()->firstOrFail();
        $stored = Storage::disk('public')->get($shot->path);

        $this->assertSame($binary, $stored);
        $this->assertStringEndsWith('.png', $shot->path);
    }
}
