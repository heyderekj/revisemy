<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class YourReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_the_homepage_offers_your_reviews_only_to_a_connected_browser(): void
    {
        $button = "fathom.trackEvent('Your reviews')";

        $this->get('/')->assertOk()->assertDontSee($button, false);

        $try = app(TryTokenService::class)->create();
        $this->actingAs($try['user'], 'web');

        $this->get('/')->assertOk()->assertSee($button, false);
    }

    public function test_the_review_header_links_back_to_your_reviews_for_the_owning_browser(): void
    {
        $try = app(TryTokenService::class)->create();
        $review = $this->reviewFor($try['token']);

        Livewire::test('review-page', ['token' => $review->token])
            ->assertDontSeeHtml('aria-label="Your reviews"');

        $this->actingAs($try['user'], 'web');

        Livewire::test('review-page', ['token' => $review->token])
            ->assertSeeHtml('aria-label="Your reviews"');
    }

    public function test_another_workspace_does_not_get_the_link(): void
    {
        $owner = app(TryTokenService::class)->create();
        $review = $this->reviewFor($owner['token']);

        $this->actingAs(app(TryTokenService::class)->create()['user'], 'web');

        Livewire::test('review-page', ['token' => $review->token])
            ->assertDontSeeHtml('aria-label="Your reviews"');
    }

    public function test_forgetting_the_browser_signs_it_out(): void
    {
        $try = app(TryTokenService::class)->create();
        $this->actingAs($try['user'], 'web');

        Livewire::test('recent-reviews')
            ->assertSee('Forget this browser')
            ->call('forgetBrowser')
            ->assertRedirect('/reviews');

        $this->assertGuest('web');
    }

    protected function reviewFor(string $token): Review
    {
        $png = 'data:image/png;base64,'.base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Hero',
            'images' => [$png],
        ])->assertCreated();

        return Review::latest('id')->firstOrFail();
    }
}
