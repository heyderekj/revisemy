<?php

namespace Tests\Feature;

use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\AddCommentTool;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\ReplyToMarksTool;
use App\Mcp\Tools\ResolveMarksTool;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\AnnotationEvent;
use App\Models\Review;
use App\Models\User;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use App\Support\MarkTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One conversation per mark: the agent replies, every step records who took
 * it, and the board, review page, guest link and payload all read the same
 * timeline.
 */
class MarkTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));
    }

    /**
     * @return array{0: User, 1: Review, 2: Annotation}
     */
    protected function setUpReview(string $status = Review::STATUS_CHANGES_REQUESTED): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $try = app(TryTokenService::class)->create();
        $review = app(ReviewService::class)->create($try['workspace'], 'Pricing page', null, [$this->tinyPngDataUrl()]);

        if ($status !== Review::STATUS_PENDING) {
            $review->update(['status' => $status, 'decision_at' => now()]);
        }

        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'question', 'body' => '20% off or 2 months free?', 'number' => 1,
        ]);

        return [$try['user'], $review->fresh(), $mark];
    }

    public function test_the_agent_replies_without_moving_the_mark(): void
    {
        [$user, $review, $mark] = $this->setUpReview();

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => $mark->id, 'body' => 'Your homepage says 2 months free. Use that?']],
        ])->assertHasNoErrors()->assertSee('Posted 1 reply(ies)');

        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
        $this->assertDatabaseHas('annotation_comments', [
            'annotation_id' => $mark->id,
            'author' => 'Your agent',
            'author_type' => AnnotationEvent::ACTOR_AGENT,
            'from_owner' => false,
        ]);

        $entries = MarkTimeline::for($mark->fresh());
        $last = end($entries);
        $this->assertSame('comment', $last['kind']);
        $this->assertSame('agent', $last['actor']);
        $this->assertSame('replied', $last['verb']);
    }

    public function test_replies_name_what_did_not_land(): void
    {
        [$user, $review, $mark] = $this->setUpReview();
        $other = app(ReviewService::class)->create($review->workspace, 'Elsewhere', null, [$this->tinyPngDataUrl()]);
        $foreign = $other->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.1, 'y' => 0.1, 'severity' => 'nit', 'body' => 'Other', 'number' => 1,
        ]);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [
                ['id' => $mark->id, 'body' => 'On it.'],
                ['id' => $foreign->id, 'body' => 'Wrong place.'],
                ['id' => 999999, 'body' => 'Nowhere.'],
            ],
        ])->assertHasNoErrors()->assertSee(['2 reply(ies) were NOT posted', 'wrong_review', 'not_found']);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => 999999, 'body' => 'Nowhere.']],
        ])->assertHasErrors(['No replies were posted']);
    }

    public function test_replies_close_with_the_review(): void
    {
        [$user, $review, $mark] = $this->setUpReview(Review::STATUS_APPROVED);

        ReviseMyServer::actingAs($user)->tool(ReplyToMarksTool::class, [
            'id' => $review->public_id,
            'replies' => [['id' => $mark->id, 'body' => 'Late thought.']],
        ])->assertHasErrors(['the thread is closed']);
    }

    public function test_resolve_marks_records_the_agent_and_its_reply(): void
    {
        [$user, $review, $mark] = $this->setUpReview();

        ReviseMyServer::actingAs($user)->tool(ResolveMarksTool::class, [
            'id' => $review->public_id,
            'marks' => [['id' => $mark->id, 'note' => 'Used 2 months free.', 'after_image' => $this->tinyPngDataUrl(), 'reply' => 'Matched the homepage.']],
        ])->assertHasNoErrors();

        $mark = $mark->fresh();
        $this->assertSame(AnnotationEvent::ACTOR_AGENT, $mark->resolvedBy());

        $event = $mark->events()->sole();
        $this->assertSame(AnnotationEvent::TYPE_RESOLVED, $event->type);
        $this->assertSame('Used 2 months free.', $event->body);
        $this->assertNotNull($event->after_screenshot_id);

        $entries = MarkTimeline::for($mark);
        $this->assertSame(['marked', 'resolved', 'comment'], array_column($entries, 'kind'));
        $this->assertSame('resolved it, with an after image', $entries[1]['verb']);
    }

    public function test_repeating_in_progress_does_not_stack_steps(): void
    {
        [, $review, $mark] = $this->setUpReview();
        $lifecycle = app(MarkLifecycleService::class);

        $lifecycle->applyAgentUpdates($review, [['id' => $mark->id, 'status' => 'in_progress']]);
        $lifecycle->applyAgentUpdates($review, [['id' => $mark->id, 'status' => 'in_progress']]);

        $this->assertSame(1, $mark->events()->count());
    }

    public function test_a_person_speaking_last_tells_the_agent_to_reply(): void
    {
        [$user, $review, $mark] = $this->setUpReview();
        app(MarkLifecycleService::class)->addComment($mark, AnnotationComment::AUTHOR_OWNER, 'Which do you recommend?');

        ReviseMyServer::actingAs($user)->tool(GetReviewTool::class, ['id' => $review->public_id])
            ->assertHasNoErrors()
            ->assertStructuredContent(fn ($json) => $json
                ->where('loop.awaiting_reply_count', 1)
                ->where('work_packets.pins.0.awaiting_reply', true)
                ->where('work_packets.pins.0.comments.0.author_type', 'owner')
                ->where('work_packets.pins.0.timeline.1.kind', 'comment')
                ->where('next_action.summary', fn ($summary) => str_contains($summary, 'M1 has a comment waiting on your reply'))
                ->etc());

        app(MarkLifecycleService::class)->replyAsAgent($review, [['id' => $mark->id, 'body' => '2 months free.']]);

        ReviseMyServer::actingAs($user)->tool(GetReviewTool::class, ['id' => $review->public_id])
            ->assertStructuredContent(fn ($json) => $json
                ->where('loop.awaiting_reply_count', 0)
                ->where('work_packets.pins.0.awaiting_reply', false)
                ->etc());
    }

    public function test_owner_steps_on_the_board_read_as_yours(): void
    {
        [, $review, $mark] = $this->setUpReview();

        Livewire::test('review-board', ['token' => $review->token])
            ->call('moveMark', $mark->id, Annotation::STATUS_RESOLVED)
            ->call('openMark', $mark->id)
            ->assertSee('What happened')
            ->assertSee('resolved it')
            ->set('commentBody', 'Fixed it in Figma.')
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertSee('Fixed it in Figma.');

        $mark = $mark->fresh();
        $this->assertSame(AnnotationEvent::ACTOR_OWNER, $mark->resolvedBy());
        $this->assertSame(AnnotationEvent::ACTOR_OWNER, $mark->comments()->sole()->author_type);
    }

    public function test_approving_records_who_verified(): void
    {
        [, $review, $mark] = $this->setUpReview();
        app(MarkLifecycleService::class)->applyAgentUpdates($review, [['id' => $mark->id, 'note' => 'Done']]);

        app(MarkLifecycleService::class)->verifyResolvedForReview($review);

        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);
        $this->assertSame(
            [AnnotationEvent::TYPE_RESOLVED, AnnotationEvent::TYPE_VERIFIED],
            $mark->events()->pluck('type')->all(),
        );
        $this->assertSame(AnnotationEvent::ACTOR_OWNER, $mark->events()->get()->last()->actor);
    }

    public function test_marks_from_before_events_rebuild_their_steps(): void
    {
        [, , $mark] = $this->setUpReview();
        $mark->update([
            'status' => Annotation::STATUS_VERIFIED,
            'resolution_note' => 'Old fix',
            'resolved_at' => now()->subDay(),
            'verified_at' => now(),
        ]);

        $entries = MarkTimeline::for($mark->fresh());

        $this->assertSame(['marked', 'resolved', 'verified'], array_column($entries, 'kind'));
        $this->assertSame(['owner', 'agent', 'owner'], array_column($entries, 'actor'));
        $this->assertSame('Old fix', $entries[1]['body']);
    }

    public function test_the_inline_app_posts_the_owners_comment(): void
    {
        [$user, $review, $mark] = $this->setUpReview();

        ReviseMyServer::actingAs($user)->tool(AddCommentTool::class, [
            'review_id' => $review->public_id,
            'mark_id' => $mark->id,
            'body' => 'Go with 2 months free.',
        ])->assertHasNoErrors();

        $this->assertDatabaseHas('annotation_comments', [
            'annotation_id' => $mark->id,
            'author_type' => AnnotationEvent::ACTOR_OWNER,
            'body' => 'Go with 2 months free.',
        ]);

        $review->update(['comments_enabled' => false]);

        ReviseMyServer::actingAs($user)->tool(AddCommentTool::class, [
            'review_id' => $review->public_id,
            'mark_id' => $mark->id,
            'body' => 'Again.',
        ])->assertHasErrors(['Comments are off']);
    }

    public function test_guests_see_the_agent_label_and_the_owner_by_role(): void
    {
        [, $review, $mark] = $this->setUpReview();
        $lifecycle = app(MarkLifecycleService::class);
        $lifecycle->addComment($mark, AnnotationComment::AUTHOR_OWNER, 'Owner thought.');
        $lifecycle->replyAsAgent($review, [['id' => $mark->id, 'body' => 'Agent answer.']]);

        Livewire::test('review-page', ['token' => $review->share_token])
            ->assertSee('Agent answer.')
            ->assertSee('Agent')
            ->assertSee('Owner')
            ->assertSee('replied');
    }

    public function test_the_api_mirrors_reply_to_marks(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public', 'revisemy.second_opinion_enabled' => false]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'API', 'images' => [$this->tinyPngDataUrl()],
        ])->json('id');
        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'nit', 'body' => 'Gap', 'number' => 1,
        ]);

        $this->withToken($token)->postJson("/api/reviews/{$id}/marks/reply", [
            'replies' => [['id' => $mark->id, 'body' => 'Tightening it now.']],
        ])->assertOk()->assertJsonPath('posted', 1)->assertJsonPath('skipped', []);

        $this->assertSame('Tightening it now.', $mark->comments()->sole()->body);
    }
}
