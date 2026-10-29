<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Live view: the page's code as captured, framed on the review so a
 * click picks the real element.
 */
class LiveViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        config(['filesystems.revisemy_disk' => 'public', 'revisemy.second_opinion_enabled' => false]);
    }

    /**
     * A 1200×800 shot with a map like ElementMarksTest's, and a stored DOM.
     */
    protected function review(bool $withDom = true): Review
    {
        $image = imagecreatetruecolor(1200, 800);
        ob_start();
        imagepng($image);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $try = app(TryTokenService::class)->create();
        $id = $this->withToken($try['token'])->postJson('/api/reviews', [
            'title' => 'Pricing',
            'images' => [$png],
            'image_details' => [['dpr' => 1, 'elements' => [
                'version' => 2, 'docWidth' => 1200, 'docHeight' => 800, 'viewport' => ['width' => 1200, 'height' => 800],
                'elements' => [
                    ['selector' => '#hero > h1', 'tag' => 'h1', 'kind' => 'Heading', 'text' => 'Plans for every team', 'box' => ['x' => 100, 'y' => 100, 'w' => 400, 'h' => 48], 'parent' => null],
                ],
            ]]],
        ])->assertCreated()->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();

        if ($withDom) {
            Storage::disk('public')->put('reviews/'.$review->public_id.'/dom.html', '<html><body><script>bad()</script><section id="hero"><h1>Plans for every team</h1></section></body></html>');
            $review->update(['dom_path' => 'reviews/'.$review->public_id.'/dom.html', 'page_url' => 'https://example.com/pricing']);
        }

        return $review->fresh();
    }

    public function test_the_live_page_is_sandboxed_and_runs_only_the_picker(): void
    {
        $review = $this->review();
        $shot = $review->screenshots->first();

        $response = $this->get("/r/{$review->token}/shots/{$shot->id}/live")->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('sandbox allow-scripts', $csp);
        $this->assertStringContainsString("connect-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'none'", $csp);
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $response->assertSee('<script nonce="'.$m[1].'">', false);
        $response->assertDontSee('bad()', false);
        $response->assertSee('Plans for every team');
    }

    public function test_only_the_review_tokens_open_it_and_a_review_without_code_has_none(): void
    {
        $review = $this->review();
        $shot = $review->screenshots->first();

        $this->get("/r/{$review->share_token}/shots/{$shot->id}/live")->assertOk();
        $this->get("/r/nope/shots/{$shot->id}/live")->assertNotFound();

        $review->update(['share_expires_at' => now()->subDay()]);
        $this->get("/r/{$review->share_token}/shots/{$shot->id}/live")->assertNotFound();

        $plain = $this->review(withDom: false);
        $this->get("/r/{$plain->token}/shots/{$plain->screenshots->first()->id}/live")->assertNotFound();
    }

    public function test_the_review_offers_live_only_when_there_is_code(): void
    {
        $review = $this->review();
        Livewire::test('review-page', ['token' => $review->token])->assertSeeHtml('\\/shots\\/'.$review->screenshots->first()->id.'\\/live');

        $plain = $this->review(withDom: false);
        Livewire::test('review-page', ['token' => $plain->token])->assertSeeHtml('liveUrl: null');
    }

    public function test_a_live_pick_becomes_a_mark_on_the_element_with_its_new_words(): void
    {
        $review = $this->review();

        $page = Livewire::test('review-page', ['token' => $review->token])
            ->call('markLiveElement', '#hero > h1', ['x' => 999, 'y' => 999, 'w' => 1, 'h' => 1], 'Plans for teams of any size');

        // Placed by the shot's map, not the frame's box.
        $this->assertEqualsWithDelta(300 / 1200, $page->get('pendingX'), 0.001);
        $this->assertEqualsWithDelta(124 / 800, $page->get('pendingY'), 0.001);
        $this->assertSame('#hero > h1', $page->get('pendingTarget')['selector'] ?? null);
        $this->assertSame('Plans for teams of any size', $page->get('draftSuggestedCopy'));
    }

    public function test_a_pick_the_map_does_not_know_falls_back_to_the_measured_box(): void
    {
        $review = $this->review();

        $page = Livewire::test('review-page', ['token' => $review->token])
            ->call('markLiveElement', 'main > p:nth-of-type(3)', ['x' => 600, 'y' => 400, 'w' => 240, 'h' => 80]);

        $this->assertEqualsWithDelta(0.6, $page->get('pendingX'), 0.001);
        $this->assertEqualsWithDelta(0.55, $page->get('pendingY'), 0.001);
        $this->assertSame('', $page->get('draftSuggestedCopy'));
    }
}
