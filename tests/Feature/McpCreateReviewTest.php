<?php

namespace Tests\Feature;

use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\GetReviewTool;
use App\Models\Review;
use App\Models\User;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class McpCreateReviewTest extends TestCase
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

    protected function setUpUser(): User
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.capture.driver' => 'hosted',
            'revisemy.capture.endpoint' => 'https://capture.test/screenshot',
            'revisemy.capture.api_key' => 'cap-key',
        ]);
        Queue::fake();

        return app(TryTokenService::class)->create()['user'];
    }

    public function test_create_review_accepts_image_uploads(): void
    {
        $user = $this->setUpUser();

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Classic upload',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertHasNoErrors()->assertStructuredContent(
            fn ($json) => $json
                ->where('type', 'ui')
                ->where('status', 'pending')
                ->has('screenshots', 1)
                ->etc()
        );

        $this->assertSame(Review::SOURCE_IMAGE, Review::query()->firstOrFail()->sourceKind());
    }

    public function test_create_review_capture_url_renders_mobile_and_desktop_screenshots(): void
    {
        $user = $this->setUpUser();

        Http::fake([
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertHasNoErrors()->assertStructuredContent(
            fn ($json) => $json
                ->where('type', 'website')
                ->where('page_url', 'https://example.com')
                ->has('screenshots', 2)
                ->where('screenshots.0.meta.viewport', 'desktop-1280')
                ->where('screenshots.1.meta.viewport', 'mobile-375')
                ->where('screenshots.0.meta.origin', 'capture')
                ->etc()
        );

        Queue::assertNothingPushed();
        Http::assertSentCount(2);
    }

    public function test_create_review_html_source_renders_an_email_review(): void
    {
        $user = $this->setUpUser();

        Http::fake([
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Welcome email',
            'html' => '<table><tr><td>Hello</td></tr></table>',
        ])->assertHasNoErrors()->assertStructuredContent(
            fn ($json) => $json
                ->where('type', 'email')
                ->has('screenshots', 1)
                ->where('screenshots.0.meta.origin', 'html')
                ->etc()
        );
    }

    public function test_create_review_pdf_ingestion_renders_pages_or_reports_missing_imagick(): void
    {
        $user = $this->setUpUser();

        $pdf = base64_encode(
            "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\n%%EOF"
        );

        $response = ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Deck',
            'pdf' => $pdf,
        ]);

        if (! extension_loaded('imagick')) {
            $response->assertHasErrors(['Imagick']);

            return;
        }

        $response->assertHasNoErrors()->assertStructuredContent(
            fn ($json) => $json
                ->where('type', 'presentation')
                ->where('screenshots.0.meta.origin', 'pdf')
                ->where('screenshots.0.meta.page', 1)
                ->etc()
        );
    }

    public function test_create_review_rejects_multiple_sources(): void
    {
        $user = $this->setUpUser();

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Too many sources',
            'page_url' => 'https://example.com',
            'capture_url' => true,
            'images' => [$this->tinyPngDataUrl()],
        ])->assertHasErrors();
    }

    public function test_create_review_rejects_no_source(): void
    {
        $user = $this->setUpUser();

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Nothing to look at',
        ])->assertHasErrors(['exactly one source']);
    }

    public function test_create_review_capture_url_requires_page_url(): void
    {
        $user = $this->setUpUser();

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'No url',
            'capture_url' => true,
        ])->assertHasErrors();
    }

    public function test_create_review_capture_fails_cleanly_when_not_configured(): void
    {
        $user = $this->setUpUser();
        config(['revisemy.capture.driver' => null]);

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Capture off',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertHasErrors();
    }

    public function test_create_review_url_capture_stores_dom_snapshot(): void
    {
        $user = $this->setUpUser();
        config(['revisemy.capture.content_endpoint' => 'https://capture.test/content']);

        Http::fake([
            'capture.test/content*' => Http::response('<html><body><h1>Hero headline</h1></body></html>'),
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertHasNoErrors();

        $review = Review::query()->firstOrFail();

        $this->assertSame(Review::SOURCE_URL, $review->sourceKind());
        $this->assertNotNull($review->dom_path);
        $this->assertStringContainsString('Hero headline', (string) $review->domHtml());
    }

    public function test_create_review_dom_capture_failure_still_creates_the_review(): void
    {
        $user = $this->setUpUser();
        config(['revisemy.capture.content_endpoint' => 'https://capture.test/content']);

        Http::fake([
            'capture.test/content*' => Http::response('nope', 500),
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Landing page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertHasNoErrors();

        $review = Review::query()->firstOrFail();
        $this->assertNull($review->dom_path);
        $this->assertNull($review->domHtml());
    }

    public function test_create_review_html_stores_submitted_html_as_dom_snapshot(): void
    {
        $user = $this->setUpUser();

        Http::fake([
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);

        $html = '<table><tr><td>Hello</td></tr></table>';

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Welcome email',
            'html' => $html,
        ])->assertHasNoErrors();

        $review = Review::query()->firstOrFail();

        $this->assertSame(Review::SOURCE_HTML, $review->sourceKind());
        $this->assertSame($html, $review->domHtml());
    }

    public function test_create_review_rejects_page_url_in_images(): void
    {
        $user = $this->setUpUser();

        Http::fake([
            'example.com/*' => Http::response(
                '<html><body>not an image</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Page URL mistaken for image',
            'images' => ['https://example.com'],
        ])->assertHasErrors();

        $this->assertSame(0, Review::query()->count());
    }

    public function test_create_review_rejects_invalid_image_payload(): void
    {
        $user = $this->setUpUser();

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Not an image',
            'images' => [base64_encode('definitely not a png')],
        ])->assertHasErrors();

        $this->assertSame(0, Review::query()->count());
    }

    public function test_get_review_round_trips_the_created_payload(): void
    {
        $user = $this->setUpUser();

        $created = ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Round trip',
            'images' => [$this->tinyPngDataUrl()],
            'context' => 'Check the CTA',
        ]);

        $created->assertHasNoErrors();

        $id = Review::query()->firstOrFail()->public_id;

        ReviseMyServer::actingAs($user)->tool(GetReviewTool::class, [
            'id' => $id,
        ])->assertHasNoErrors()->assertStructuredContent(
            fn ($json) => $json
                ->where('id', $id)
                ->where('title', 'Round trip')
                ->where('context', 'Check the CTA')
                ->where('status', 'pending')
                ->etc()
        );
    }
}
