<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Review;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewLinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();
        config(['filesystems.revisemy_disk' => 'public']);
    }

    /** A page-sized PNG, so the card has something to crop. */
    protected function pageDataUrl(int $width = 800, int $height = 1400): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 236, 228));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    protected function review(?string $image = null, string $type = Review::TYPE_WEBSITE): Review
    {
        $user = app(TryTokenService::class)->createWorkspaceUser();

        return app(ReviewService::class)->create(
            $user->workspace, 'Pricing page', null, [$image ?? $this->pageDataUrl()], type: $type,
        );
    }

    public function test_an_owner_link_previews_the_review(): void
    {
        $review = $this->review();
        $review->screenshots->first()->annotations()->createMany([
            ['x' => 0.2, 'y' => 0.2, 'severity' => 'must-fix', 'body' => 'Tighten', 'number' => 1],
            ['x' => 0.4, 'y' => 0.4, 'severity' => 'question', 'body' => 'Why?', 'number' => 2],
        ]);

        $this->get('/r/'.$review->token)
            ->assertOk()
            ->assertSee('<title>Pricing page — ReviseMy</title>', false)
            ->assertSee('<meta property="og:description" content="A website review. Mark what matters, then approve or ask for changes.">', false)
            ->assertSee('content="'.route('reviews.preview', $review->token), false)
            ->assertSee('<meta property="og:image:alt" content="The top of Pricing page">', false)
            ->assertSee('<meta name="twitter:label1" content="Marks">', false)
            ->assertSee('<meta name="twitter:data1" content="2 open">', false)
            ->assertSee('<meta name="twitter:data2" content="Waiting on you">', false)
            ->assertSee('noindex', false);
    }

    public function test_the_description_follows_the_review(): void
    {
        $review = $this->review(type: Review::TYPE_EMAIL);
        $review->update(['status' => Review::STATUS_APPROVED, 'pass' => 3]);

        $this->get('/r/'.$review->token)
            ->assertSee('content="An email review, pass 3. Approved."', false)
            ->assertSee('<meta name="twitter:data1" content="None open">', false)
            ->assertSee('<meta name="twitter:data2" content="Approved">', false);
    }

    public function test_a_guest_link_previews_as_an_invitation(): void
    {
        $review = $this->review();

        $this->get('/r/'.$review->share_token)
            ->assertOk()
            ->assertSee('content="Suggest marks on this website review. The person who shared it decides what goes to their agent."', false)
            ->assertSee('content="'.route('reviews.preview', $review->share_token), false)
            ->assertSee('<meta name="twitter:data2" content="Open for suggestions">', false);
    }

    public function test_an_expired_guest_link_shows_only_the_site_card(): void
    {
        $review = $this->review();
        $review->update(['share_expires_at' => now()->subDay()]);

        $this->get('/r/'.$review->share_token)
            ->assertOk()
            ->assertDontSee('Pricing page — ReviseMy', false)
            ->assertDontSee(route('reviews.preview', $review->share_token), false)
            ->assertDontSee('twitter:label1', false);

        $this->get(route('reviews.preview', $review->share_token))->assertNotFound();
    }

    public function test_the_card_is_the_top_of_the_first_shot_at_card_size(): void
    {
        $review = $this->review();

        $response = $this->get(route('reviews.preview', $review->token))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $size = getimagesizefromstring($response->getContent());
        $this->assertSame([1200, 630], [$size[0], $size[1]]);
        $this->assertSame('image/jpeg', $size['mime']);

        $cached = 'reviews/'.$review->public_id.'/preview-'.$review->screenshots->first()->id.'.jpg';
        Storage::disk('public')->assertExists($cached);

        // The second fetch reads what was kept.
        Storage::disk('public')->put($cached, 'kept');
        $this->assertSame('kept', $this->get(route('reviews.preview', $review->token))->getContent());
    }

    public function test_a_wide_short_shot_still_fills_the_card(): void
    {
        $review = $this->review($this->pageDataUrl(2000, 300));

        $size = getimagesizefromstring($this->get(route('reviews.preview', $review->token))->getContent());

        $this->assertSame([1200, 630], [$size[0], $size[1]]);
    }

    public function test_a_failed_capture_falls_back_to_the_site_card(): void
    {
        $review = $this->review();
        $shot = $review->screenshots->first();
        $shot->update(['meta' => [...($shot->meta ?? []), 'origin' => 'capture_failed']]);

        $this->get('/r/'.$review->token)
            ->assertOk()
            ->assertSee(url('/images/og.png'), false)
            ->assertDontSee(route('reviews.preview', $review->token), false);

        $this->get(route('reviews.preview', $review->token))->assertNotFound();
    }

    public function test_unknown_links_are_not_found(): void
    {
        $this->get('/r/'.str_repeat('x', 40))->assertNotFound();
        $this->get('/r/'.str_repeat('x', 40).'/board')->assertNotFound();
        $this->get('/r/'.str_repeat('x', 40).'/preview.jpg')->assertNotFound();
    }

    public function test_the_board_link_previews_the_same_way(): void
    {
        $review = $this->review();

        $this->get('/r/'.$review->token.'/board')
            ->assertOk()
            ->assertSee('<title>Pricing page — ReviseMy</title>', false)
            ->assertSee('content="'.route('reviews.preview', $review->token), false);
    }

    public function test_link_preview_bots_may_fetch_review_links(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString("User-agent: *\nAllow: /\nDisallow: /r/", $robots);
        $this->assertMatchesRegularExpression('/User-agent: Slackbot-LinkExpanding\n(User-agent: [^\n]+\n)*Allow: \//', $robots);
        $this->assertStringContainsString('User-agent: Twitterbot', $robots);
    }

    public function test_guests_can_start_their_own_review(): void
    {
        $review = $this->review();

        $this->get('/r/'.$review->share_token)
            ->assertSee('Start your own review')
            ->assertSee('href="'.route('reviews.new').'"', false);

        $this->get('/r/'.$review->token)->assertDontSee('Start your own review');

        $review->update(['share_expires_at' => now()->subDay()]);

        $this->get('/r/'.$review->share_token)
            ->assertSee('This guest link has expired')
            ->assertSee('start your own review');
    }

    public function test_open_marks_count_only_what_still_needs_work(): void
    {
        $review = $this->review();
        $shot = $review->screenshots->first();
        $shot->annotations()->create(['x' => 0.2, 'y' => 0.2, 'severity' => 'must-fix', 'body' => 'Open', 'number' => 1]);
        $shot->annotations()->create(['x' => 0.3, 'y' => 0.3, 'severity' => 'must-fix', 'body' => 'Done', 'number' => 2, 'status' => Annotation::STATUS_VERIFIED]);

        $this->get('/r/'.$review->token)->assertSee('<meta name="twitter:data1" content="1 open">', false);
    }
}
