<?php

namespace Tests\Feature;

use App\Models\Review;
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

    public function test_capture_url_renders_mobile_and_desktop_screenshots(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        $response->assertJsonPath('type', 'website');

        $shots = $response->json('screenshots');
        $this->assertCount(2, $shots);
        $this->assertSame('desktop-1280', $shots[0]['meta']['viewport']);
        $this->assertSame('mobile-375', $shots[1]['meta']['viewport']);
        $this->assertSame('capture', $shots[0]['meta']['origin']);

        Queue::assertNothingPushed();
        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $viewport = $body['viewport'] ?? [];
            $goto = $body['gotoOptions'] ?? [];
            $options = $body['options'] ?? [];

            return ($viewport['deviceScaleFactor'] ?? null) === 1
                && ($options['fullPage'] ?? null) === true
                && ($body['waitForTimeout'] ?? null) === (int) config('revisemy.capture.wait_ms', 2500)
                && ($goto['waitUntil'] ?? null) === (string) config('revisemy.capture.wait_until', 'networkidle2');
        });
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

    public function test_capture_fails_cleanly_when_not_configured(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.driver' => null]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Capture off',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['capture'])
            ->assertJsonFragment(['capture' => ['[capture_not_configured] Server-side capture is off. Set REVISEMY_CAPTURE_DRIVER=hosted plus REVISEMY_CAPTURE_ENDPOINT/KEY (Browserless) on Cloud, or browsershot locally. Fallback: call create_review with images as desktop+mobile data URLs instead of capture_url.']]);
    }

    public function test_capture_reports_provider_http_failure(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'capture.test/*' => Http::response('nope', 502),
        ]);

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Provider down',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['capture']);

        $message = (string) data_get($response->json(), 'errors.capture.0');
        $this->assertStringContainsString('[capture_provider_failed]', $message);
        $this->assertStringContainsString('HTTP 502', $message);
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

        if (! extension_loaded('imagick')) {
            $response->assertUnprocessable();
            $this->assertStringContainsString('Imagick', collect($response->json('errors'))->flatten()->first());

            return;
        }

        $response->assertCreated();
        $this->assertSame('presentation', $response->json('type'));
        $this->assertSame('pdf', $response->json('screenshots.0.meta.origin'));
        $this->assertSame(1, $response->json('screenshots.0.meta.page'));
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
        $this->assertStringContainsString('/shots/'.$shot->id.'?', $shot->url());
    }

    public function test_url_capture_stores_dom_snapshot(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.content_endpoint' => 'https://capture.test/content']);

        Http::fake([
            'capture.test/content*' => Http::response('<html><body><h1>Hero headline</h1></body></html>'),
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
    }

    public function test_dom_capture_failure_still_creates_the_review(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.content_endpoint' => 'https://capture.test/content']);

        Http::fake([
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
        $token = $this->setUpEnv();

        // An uncompressed PNG (level 0) blows past the 16MB cap without needing
        // slow noise generation: 2600×2600 truecolor ≈ 17MB on disk.
        $image = imagecreatetruecolor(2600, 2600);
        ob_start();
        imagepng($image, null, 0);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

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
