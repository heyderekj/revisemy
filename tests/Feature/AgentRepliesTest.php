<?php

namespace Tests\Feature;

use App\Events\MarkUpdated;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\ReplyToMarksTool;
use App\Mcp\Tools\ResolveMarksTool;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\Review;
use App\Models\User;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The agent answers in a mark's thread with reply_to_marks, and everything it
 * did carries the Agent tag, for the owner and for guests.
 */
class AgentRepliesTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));
    }

    /**
     * @return array{0: User, 1: Review}
     */
    protected function setUpReview(string $status = Review::STATUS_PENDING): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $try = app(TryTokenService::class)->create();
        $review = app(ReviewService::class)->create($try['workspace'], 'Pass 1', null, [$this->tinyPngDataUrl()]);

        if ($status !== Review::STATUS_PENDING) {
            $review->update(['status' => $status, 'decision_at' => now()]);
        }

        return [$try['user'], $review->fresh()];
    }

    protected function mark(Review $review, array $attributes = []): Annotation
    {
        return $review->screenshots()->firstOrFail()->annotations()->create($attributes + [
            'x' => 0.5, 'y' => 0.5, 'severity' => 'question', 'body' => 'Is this the final logo?', 'number' => 1,
        ]);
    }

    public function test_a_reply_lands_in_the_thread_and_leaves_the_mark_alone(): void
    {
        [$user, $review] = $this->setUpReview();
        $mark = $this->mark($review);

        $response = ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => $mark->id, 'body' => 'Yes, that is the final logo.']],
        ]);

        $response->assertHasNoErrors()->assertSee(['Posted 1 reply', '"author_type":"agent"', 'Yes, that is the final logo.']);

        $comment = $mark->comments()->sole();
        $this->assertTrue($comment->isAgent());
        $this->assertFalse($comment->from_owner);
        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
        $this->assertNull($mark->fresh()->status_by);
    }

    public function test_the_agent_can_reply_after_changes_are_requested(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_CHANGES_REQUESTED);
        $mark = $this->mark($review);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => $mark->id, 'body' => 'Widening the gap now.']],
        ])->assertHasNoErrors()->assertSee('Posted 1 reply');
    }

    public function test_replies_are_skipped_for_unknown_and_foreign_marks(): void
    {
        [$user, $review] = $this->setUpReview();
        $other = app(ReviewService::class)->create($review->workspace, 'Unrelated', null, [$this->tinyPngDataUrl()]);
        $foreign = $this->mark($other);
        $own = $this->mark($review);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [
                ['id' => $own->id, 'body' => 'Answered.'],
                ['id' => $foreign->id, 'body' => 'Wrong review.'],
                ['id' => 999999, 'body' => 'Nothing here.'],
            ],
        ])->assertHasNoErrors()->assertSee(['2 reply(ies) were NOT posted', 'wrong_review', 'not_found']);

        $this->assertSame(1, $own->comments()->count());
        $this->assertSame(0, $foreign->comments()->count());
    }

    public function test_replies_respect_comments_being_off(): void
    {
        [$user, $review] = $this->setUpReview();
        $review->update(['comments_enabled' => false]);
        $mark = $this->mark($review);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => $mark->id, 'body' => 'Hello?']],
        ])->assertHasErrors(['comments_off']);

        $this->assertSame(0, $mark->comments()->count());
    }

    public function test_an_approved_review_takes_no_replies(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_APPROVED);
        $mark = $this->mark($review);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => $mark->id, 'body' => 'Late.']],
        ])->assertHasErrors(['thread is closed']);
    }

    public function test_the_agent_can_reply_on_the_pass_before(): void
    {
        [$user, $parent] = $this->setUpReview(Review::STATUS_CHANGES_REQUESTED);
        $parentMark = $this->mark($parent);
        $child = app(ReviewService::class)->create($parent->workspace, 'Pass 2', null, [$this->tinyPngDataUrl()], parentPublicId: $parent->public_id);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $child->public_id,
            'replies' => [['id' => $parentMark->id, 'body' => 'Fixed on this pass.']],
        ])->assertHasNoErrors();

        $this->assertSame(1, $parentMark->comments()->count());
    }

    public function test_a_reply_refreshes_open_pages(): void
    {
        [$user, $review] = $this->setUpReview();
        $mark = $this->mark($review);
        Event::fake([MarkUpdated::class]);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => $mark->id, 'body' => 'On it.']],
        ])->assertHasNoErrors();

        Event::assertDispatched(MarkUpdated::class, fn (MarkUpdated $e) => $e->annotation->is($mark));
    }

    public function test_status_by_records_who_moved_a_mark(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_CHANGES_REQUESTED);
        $mark = $this->mark($review, ['severity' => 'must-fix']);

        ReviseMyServer::actingAs($user)->tool(ResolveMarksTool::class, [
            'id' => $review->public_id,
            'marks' => [['id' => $mark->id, 'note' => 'Swapped the logo.']],
        ])->assertHasNoErrors()->assertSee('"status_by":"agent"');

        $this->assertTrue($mark->fresh()->changedByAgent());

        app(MarkLifecycleService::class)->verify($mark->fresh());

        $this->assertSame(Annotation::STATUS_BY_OWNER, $mark->fresh()->status_by);
        $this->assertFalse($mark->fresh()->changedByAgent());
    }

    public function test_guests_and_the_owner_see_the_agent_tag(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_CHANGES_REQUESTED);
        $mark = $this->mark($review, ['severity' => 'must-fix']);

        app(MarkLifecycleService::class)->applyAgentUpdates($review, [['id' => $mark->id, 'note' => 'Swapped the logo.']]);
        app(MarkLifecycleService::class)->replyAsAgent($review, [['id' => $mark->id, 'body' => 'The old one was a placeholder.']]);

        foreach ([$review->token, $review->share_token] as $token) {
            Livewire::test('review-page', ['token' => $token])
                ->assertSee('The old one was a placeholder.')
                ->assertSee('Swapped the logo.')
                ->assertSeeHtml('>Agent</span>')
                ->assertDontSee('Agent:');
        }

        Livewire::test('review-board', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->assertSee('The old one was a placeholder.')
            ->assertSeeHtml('>Agent</span>');
    }

    public function test_human_comments_keep_their_author_type(): void
    {
        [, $review] = $this->setUpReview();
        $mark = $this->mark($review);

        Livewire::test('review-page', ['token' => $review->share_token])
            ->set('guestName', 'Sam')
            ->call('startMarkComment', $mark->id)
            ->set('markCommentBody', 'Looks fine to me.')
            ->call('addMarkComment', $mark->id);

        $this->assertSame(AnnotationComment::AUTHOR_GUEST, $mark->comments()->sole()->author_type);
    }
}
