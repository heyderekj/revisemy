<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Services\Capture\LiveArchive;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Live view stands on its own: stylesheets and fonts kept with the
 * review, images through a proxy that only fetches what the page used, and
 * a review that opens on Live only where it matches the screenshot.
 */
class LiveArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected const SITE = 'https://93.184.215.14';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
    }

    protected function review(string $html): Review
    {
        $workspace = app(TryTokenService::class)->create()['user']->workspace;
        $review = Review::query()->create(['workspace_id' => $workspace->id, 'title' => 'Pricing', 'page_url' => self::SITE.'/pricing']);
        $review->screenshots()->create(['path' => 'reviews/x/1.png', 'disk' => 'public', 'width' => 1200, 'height' => 800, 'sort_order' => 0, 'meta' => ['origin' => 'capture', 'css_width' => 1200]]);
        Storage::disk('public')->put('reviews/'.$review->public_id.'/dom.html', $html);
        $review->update(['dom_path' => 'reviews/'.$review->public_id.'/dom.html']);

        return $review->fresh('screenshots');
    }

    protected function fakeSite(): void
    {
        Http::fake([
            self::SITE.'/site.css' => Http::response('@import "/extra.css"; body{font-family:Brand} @font-face{font-family:Brand;src:url(/fonts/brand.woff2)} .hero{background:url("img/bg.jpg")}', 200, ['Content-Type' => 'text/css']),
            self::SITE.'/extra.css' => Http::response('h1{color:red}', 200, ['Content-Type' => 'text/css']),
            self::SITE.'/fonts/brand.woff2' => Http::response('WOFF2DATA', 200, ['Content-Type' => 'font/woff2']),
            self::SITE.'/hero.jpg' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg']),
            self::SITE.'/evil.html' => Http::response('<script>x</script>', 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_stylesheets_are_written_in_and_assets_point_at_the_archive(): void
    {
        $this->fakeSite();
        $review = $this->review('<html><head><link rel="stylesheet" href="/site.css" media="screen"></head><body><img src="/hero.jpg" srcset="/hero.jpg 1x, /hero@2x.jpg 2x"><img src="data:image/png;base64,AAAA"></body></html>');

        $this->assertTrue(app(LiveArchive::class)->prepare($review));

        $live = Storage::disk('public')->get(LiveArchive::livePath($review));
        $this->assertStringNotContainsString('<link', $live);
        $this->assertStringContainsString('<style data-rm-from="'.self::SITE.'/site.css" media="screen">', $live);
        $this->assertStringContainsString('h1{color:red}', $live, 'the @import is written in');
        $this->assertStringNotContainsString(self::SITE.'/hero.jpg', $live);
        $this->assertStringContainsString('/live-assets/'.$review->public_id.'/', $live);
        $this->assertStringContainsString('src="data:image/png;base64,AAAA"', $live);

        // The font was kept at once; the image waits for its first view.
        $fontKey = LiveArchive::key($review, self::SITE.'/fonts/brand.woff2');
        Storage::disk('public')->assertExists('reviews/'.$review->public_id.'/live-assets/'.$fontKey);
        $imageKey = LiveArchive::key($review, self::SITE.'/hero.jpg');
        Storage::disk('public')->assertMissing('reviews/'.$review->public_id.'/live-assets/'.$imageKey);

        // Live frames the archived page from now on.
        $this->assertStringContainsString('data-rm-from', (string) $review->liveHtml());
    }

    public function test_the_proxy_fetches_once_keeps_it_and_serves_only_what_the_page_used(): void
    {
        $this->fakeSite();
        $review = $this->review('<html><body><img src="'.self::SITE.'/hero.jpg"><img src="'.self::SITE.'/evil.html"></body></html>');
        app(LiveArchive::class)->prepare($review);

        $key = LiveArchive::key($review, self::SITE.'/hero.jpg');
        $this->get("/live-assets/{$review->public_id}/{$key}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertSee('JPEGDATA');

        Http::assertSentCount(1);
        $this->get("/live-assets/{$review->public_id}/{$key}")->assertOk();
        Http::assertSentCount(1);

        // Not an image, font or stylesheet: refused.
        $this->get("/live-assets/{$review->public_id}/".LiveArchive::key($review, self::SITE.'/evil.html'))->assertNotFound();

        // A URL the page never used has no key the proxy knows.
        $this->get("/live-assets/{$review->public_id}/".LiveArchive::key($review, 'https://93.184.215.14/other.jpg'))->assertNotFound();
        $this->get("/live-assets/{$review->public_id}/not-a-key")->assertNotFound();
    }

    public function test_a_review_opens_on_live_only_where_it_matches_the_screenshot(): void
    {
        config(['revisemy.live.match_threshold' => 0.87]);
        $review = $this->review('<html><body><h1>Hi</h1></body></html>');
        $shot = $review->screenshots->first();

        Livewire::test('review-page', ['token' => $review->token])->assertSeeHtml('live: false');

        $shot->update(['meta' => array_merge($shot->meta, ['live_match' => 0.95])]);
        Livewire::test('review-page', ['token' => $review->token])
            ->assertSeeHtml('live: true')
            ->assertSeeHtml('liveFaithful: true');

        $shot->update(['meta' => array_merge($shot->meta, ['live_match' => 0.6])]);
        Livewire::test('review-page', ['token' => $review->token])->assertSeeHtml('live: false');
    }
}
