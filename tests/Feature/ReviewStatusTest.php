<?php

namespace Tests\Feature;

use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\DecideReviewTool;
use App\Mcp\Tools\ListReviewsTool;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_a_decided_review_can_be_reopened_and_switched(): void
    {
        [, $review] = $this->review(Review::STATUS_APPROVED);
        $service = app(ReviewService::class);

        $this->assertTrue($service->setStatus($review, Review::STATUS_PENDING));
        $this->assertSame(Review::STATUS_PENDING, $review->fresh()->status);
        $this->assertNull($review->fresh()->decision_at);

        $this->assertTrue($service->setStatus($review->fresh(), Review::STATUS_CHANGES_REQUESTED, 'Tighten it'));
        $this->assertTrue($service->setStatus($review->fresh(), Review::STATUS_APPROVED));
        $this->assertSame(Review::STATUS_APPROVED, $review->fresh()->status);
    }

    public function test_switching_to_approved_verifies_resolved_marks(): void
    {
        [, $review] = $this->review(Review::STATUS_CHANGES_REQUESTED);
        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
            'status' => Annotation::STATUS_RESOLVED, 'resolved_at' => now(),
        ]);

        app(ReviewService::class)->setStatus($review, Review::STATUS_APPROVED);

        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);
    }

    public function test_an_older_pass_and_an_expired_review_stay_as_they_are(): void
    {
        [, $parent] = $this->review(Review::STATUS_CHANGES_REQUESTED);
        $this->nextPass($parent);

        $this->assertFalse($parent->fresh()->canChangeStatus());
        $this->assertFalse(app(ReviewService::class)->setStatus($parent->fresh(), Review::STATUS_PENDING));

        [, $old] = $this->review(Review::STATUS_APPROVED);
        $old->update(['expires_at' => now()->subDay()]);

        $this->assertFalse(app(ReviewService::class)->setStatus($old->fresh(), Review::STATUS_PENDING));
    }

    public function test_the_owner_sets_status_from_the_review_page_but_a_guest_cannot(): void
    {
        [, $review] = $this->review(Review::STATUS_APPROVED);

        Livewire::test('review-page', ['token' => $review->share_token, 'mode' => 'guest'])
            ->call('setReviewStatus', Review::STATUS_PENDING);
        $this->assertSame(Review::STATUS_APPROVED, $review->fresh()->status);

        Livewire::test('review-page', ['token' => $review->token])
            ->assertSee('Reopen for review')
            ->call('setReviewStatus', Review::STATUS_PENDING);
        $this->assertSame(Review::STATUS_PENDING, $review->fresh()->status);
    }

    public function test_the_board_offers_the_same_menu(): void
    {
        [, $review] = $this->review(Review::STATUS_CHANGES_REQUESTED);

        Livewire::test('review-board', ['token' => $review->token])
            ->assertSee('Approve instead')
            ->call('setReviewStatus', Review::STATUS_APPROVED)
            ->call('archiveReview');

        $this->assertSame(Review::STATUS_APPROVED, $review->fresh()->status);
        $this->assertTrue($review->fresh()->isArchived());
    }

    public function test_archiving_puts_the_whole_chain_away(): void
    {
        [, $parent] = $this->review(Review::STATUS_CHANGES_REQUESTED);
        $child = $this->nextPass($parent);

        app(ReviewService::class)->archive($child);

        $this->assertNotNull($parent->fresh()->archived_at);
        $this->assertNotNull($child->fresh()->archived_at);

        app(ReviewService::class)->archive($parent->fresh(), false);

        $this->assertNull($child->fresh()->archived_at);
    }

    public function test_your_reviews_shows_one_row_per_chain_and_shelves_archived(): void
    {
        [$user, $parent] = $this->review(Review::STATUS_CHANGES_REQUESTED);
        $child = $this->nextPass($parent);
        [, $other] = $this->review(Review::STATUS_APPROVED, $user);

        $this->actingAs($user, 'web');

        $list = Livewire::test('recent-reviews');
        $this->assertSame([$other->public_id, $child->public_id], collect($list->get('reviews'))->pluck('id')->all());

        $list->call('archive', $other->public_id);
        $this->assertSame([$child->public_id], collect($list->get('reviews'))->pluck('id')->all());
        $this->assertSame(['open' => 1, 'archived' => 1], $list->get('shelfCounts'));

        $list->set('shelf', 'archived');
        $this->assertSame([$other->public_id], collect($list->get('reviews'))->pluck('id')->all());

        $list->call('restore', $other->public_id)->set('shelf', 'open')->set('statusFilter', Review::STATUS_APPROVED);
        $this->assertSame([$other->public_id], collect($list->get('reviews'))->pluck('id')->all());
    }

    public function test_deleting_from_your_reviews_removes_every_pass(): void
    {
        [$user, $parent] = $this->review(Review::STATUS_CHANGES_REQUESTED);
        $child = $this->nextPass($parent);

        $this->actingAs($user, 'web');
        Livewire::test('recent-reviews')->call('deleteReview', $child->public_id);

        $this->assertNull(Review::find($parent->id));
        $this->assertNull(Review::find($child->id));
    }

    public function test_another_workspace_cannot_touch_a_review_from_its_list(): void
    {
        [, $review] = $this->review(Review::STATUS_APPROVED);
        $this->actingAs(app(TryTokenService::class)->create()['user'], 'web');

        Livewire::test('recent-reviews')
            ->call('deleteReview', $review->public_id)
            ->call('archive', $review->public_id)
            ->call('setStatus', $review->public_id, Review::STATUS_PENDING);

        $this->assertNotNull($review->fresh());
        $this->assertFalse($review->fresh()->isArchived());
        $this->assertSame(Review::STATUS_APPROVED, $review->fresh()->status);
    }

    public function test_the_inline_review_can_reopen_and_list_reviews_skips_archived(): void
    {
        [$user, $review] = $this->review(Review::STATUS_APPROVED);
        [, $archived] = $this->review(Review::STATUS_PENDING, $user);
        app(ReviewService::class)->archive($archived);

        ReviseMyServer::actingAs($user)->tool(DecideReviewTool::class, [
            'review_id' => $review->public_id,
            'decision' => 'pending',
        ])->assertHasNoErrors();
        $this->assertSame(Review::STATUS_PENDING, $review->fresh()->status);

        ReviseMyServer::actingAs($user)->tool(ListReviewsTool::class, [])
            ->assertSee($review->public_id)
            ->assertDontSee($archived->public_id);

        ReviseMyServer::actingAs($user)->tool(ListReviewsTool::class, ['include_archived' => true])
            ->assertSee($archived->public_id);
    }

    public function test_the_rest_list_takes_the_same_filters(): void
    {
        [$user, $review] = $this->review(Review::STATUS_APPROVED);
        [, $pending] = $this->review(Review::STATUS_PENDING, $user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/reviews?status=approved')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('reviews.0.id', $review->public_id);

        app(ReviewService::class)->archive($pending);

        $this->withToken($token)->getJson('/api/reviews')->assertJsonPath('count', 1);
        $this->withToken($token)->getJson('/api/reviews?include_archived=1')->assertJsonPath('count', 2);
    }

    /**
     * @return array{0: User, 1: Review}
     */
    protected function review(string $status, ?User $user = null): array
    {
        $user ??= app(TryTokenService::class)->create()['user'];

        $review = Review::query()->create([
            'workspace_id' => $user->workspace->id,
            'title' => 'Hero',
            'status' => $status,
            'decision_at' => $status === Review::STATUS_PENDING ? null : now(),
        ]);
        $review->screenshots()->create(['path' => 'reviews/x/1.png', 'disk' => 'public', 'width' => 1, 'height' => 1, 'sort_order' => 0]);

        return [$user, $review->fresh()];
    }

    protected function nextPass(Review $parent): Review
    {
        $child = Review::query()->create([
            'workspace_id' => $parent->workspace_id,
            'parent_id' => $parent->id,
            'pass' => $parent->pass + 1,
            'title' => 'Pass '.($parent->pass + 1),
        ]);
        $child->screenshots()->create(['path' => 'reviews/y/1.png', 'disk' => 'public', 'width' => 1, 'height' => 1, 'sort_order' => 0]);

        return $child->fresh();
    }
}
