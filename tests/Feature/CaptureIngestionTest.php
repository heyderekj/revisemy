<?php

namespace Tests\Feature;

use App\Jobs\GenerateSecondOpinionJob;
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
        $this->assertSame('mobile-375', $shots[0]['meta']['viewport']);
        $this->assertSame('desktop-1280', $shots[1]['meta']['viewport']);
        $this->assertSame('capture', $shots[0]['meta']['origin']);

        Queue::assertPushed(GenerateSecondOpinionJob::class, 2);
        Http::assertSentCount(2);
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
        ])->assertUnprocessable();
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

    public function test_oversized_capture_is_downscaled_under_the_cap(): void
    {
        $token = $this->setUpEnv();

        // An uncompressed PNG (level 0) blows past the 8MB cap without needing
        // slow noise generation: 1800×1800 truecolor ≈ 13MB on disk.
        $image = imagecreatetruecolor(1800, 1800);
        ob_start();
        imagepng($image, null, 0);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        $this->assertGreaterThan(8 * 1024 * 1024, strlen($binary));

        Http::fake(['capture.test/*' => Http::response($binary)]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Huge capture',
            'html' => '<p>big</p>',
        ])->assertCreated();

        $review = Review::query()->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();
        $this->assertLessThanOrEqual(8 * 1024 * 1024, strlen(Storage::disk('public')->get($shot->path)));
    }
}
