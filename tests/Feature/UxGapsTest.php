<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UxGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function review(array $attributes = []): Review
    {
        $workspace = app(TryTokenService::class)->create()['user']->workspace;
        $review = Review::query()->create(['workspace_id' => $workspace->id, 'title' => 'Hero'] + $attributes);
        $review->screenshots()->create(['path' => 'reviews/x/1.png', 'disk' => 'public', 'width' => 1, 'height' => 1, 'sort_order' => 0]);

        return $review->fresh();
    }

    public function test_an_older_pass_points_owner_and_guest_to_the_newest(): void
    {
        $parent = $this->review(['status' => Review::STATUS_CHANGES_REQUESTED]);
        $child = Review::query()->create(['workspace_id' => $parent->workspace_id, 'parent_id' => $parent->id, 'pass' => 2, 'title' => 'Hero']);
        $child->screenshots()->create(['path' => 'reviews/y/1.png', 'disk' => 'public', 'width' => 1, 'height' => 1, 'sort_order' => 0]);

        Livewire::test('review-page', ['token' => $parent->token])
            ->assertSee('Pass 2 is ready')
            ->assertSeeHtml($child->reviewUrl());

        Livewire::test('review-page', ['token' => $parent->share_token, 'mode' => 'guest'])
            ->assertSeeHtml($child->shareUrl());

        Livewire::test('review-page', ['token' => $child->token])->assertDontSee('is ready');
    }

    public function test_an_open_review_says_until_when(): void
    {
        $review = $this->review(['expires_at' => now()->addDays(5)]);

        Livewire::test('review-page', ['token' => $review->token])
            ->assertSee('open until '.$review->expires_at->timezone(config('app.timezone'))->format('M j'));
    }

    public function test_the_next_pass_keeps_how_the_last_was_shared(): void
    {
        $parent = $this->review(['comments_enabled' => false, 'share_expires_at' => now()->addDays(30)]);

        $sharing = (new \ReflectionMethod(ReviewService::class, 'guestSharingFrom'))->invoke(app(ReviewService::class), $parent);

        $this->assertFalse($sharing['comments_enabled']);
        $this->assertTrue($sharing['share_expires_at']->equalTo($parent->share_expires_at));
    }

    public function test_your_reviews_shows_credits_and_a_way_to_start(): void
    {
        $try = app(TryTokenService::class)->create();
        $this->actingAs($try['user'], 'web');

        Livewire::test('recent-reviews')
            ->assertSee('credits left')
            ->assertSee('Billing')
            ->assertSee('Start a review');
    }
}
