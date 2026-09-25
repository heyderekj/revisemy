<?php

namespace Tests\Feature;

use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\ResolveMarksTool;
use App\Mcp\Tools\VerifyMarkTool;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\User;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * States where the agent used to get wrong or missing instructions, and
 * lifecycle guards that let an agent (or a stale UI) undo the human's work.
 */
class AgentLoopGapsTest extends TestCase
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
    protected function setUpReview(string $status = Review::STATUS_CHANGES_REQUESTED): array
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

    protected function childOf(Review $parent, string $status = Review::STATUS_PENDING): Review
    {
        $child = app(ReviewService::class)->create(
            $parent->workspace,
            'Pass 2',
            null,
            [$this->tinyPngDataUrl()],
            parentPublicId: $parent->public_id,
        );

        if ($status !== Review::STATUS_PENDING) {
            $child->update(['status' => $status, 'decision_at' => now()]);
        }

        return $child->fresh();
    }

    protected function mark(Review $review, array $attributes = []): Annotation
    {
        return $review->screenshots()->firstOrFail()->annotations()->create($attributes + [
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
        ]);
    }

    // ── resolve_marks guards ───────────────────────────────────────────

    public function test_resolve_skips_marks_from_another_review_in_the_same_workspace(): void
    {
        [$user, $review] = $this->setUpReview();
        $other = app(ReviewService::class)->create($review->workspace, 'Unrelated', null, [$this->tinyPngDataUrl()]);
        $foreign = $this->mark($other);
        $own = $this->mark($review);

        ReviseMyServer::actingAs($user)->tool(ResolveMarksTool::class, [
            'id' => $review->public_id,
            'marks' => [['id' => $own->id, 'note' => 'Done'], ['id' => $foreign->id, 'note' => 'Sneaky']],
        ])->assertHasNoErrors()->assertSee(['1 mark(s) were NOT updated', 'wrong_review']);

        $this->assertSame(Annotation::STATUS_RESOLVED, $own->fresh()->status);
        $this->assertSame(Annotation::STATUS_OPEN, $foreign->fresh()->status);
    }

    public function test_resolve_can_reach_carried_over_parent_marks(): void
    {
        [, $parent] = $this->setUpReview();
        $parentMark = $this->mark($parent, ['status' => Annotation::STATUS_OPEN]);
        $child = $this->childOf($parent, Review::STATUS_CHANGES_REQUESTED);

        $result = app(MarkLifecycleService::class)->applyAgentUpdates($child, [['id' => $parentMark->id]]);

        $this->assertSame([], $result['skipped']);
        $this->assertSame(Annotation::STATUS_RESOLVED, $parentMark->fresh()->status);
    }

    public function test_resolve_cannot_move_verified_or_keep_marks_backwards(): void
    {
        [, $review] = $this->setUpReview();
        $verified = $this->mark($review, ['status' => Annotation::STATUS_VERIFIED, 'verified_at' => now()]);
        $keep = $this->mark($review, ['severity' => 'keep', 'number' => 2]);

        $result = app(MarkLifecycleService::class)->applyAgentUpdates($review, [
            ['id' => $verified->id, 'status' => Annotation::STATUS_IN_PROGRESS],
            ['id' => $keep->id],
        ]);

        $this->assertTrue($result['updated']->isEmpty());
        $this->assertSame(['already_verified', 'keep'], array_column($result['skipped'], 'reason'));
        $this->assertSame(Annotation::STATUS_VERIFIED, $verified->fresh()->status);
        $this->assertSame(Annotation::STATUS_VERIFIED, $keep->fresh()->status);
    }

    public function test_a_bad_after_image_skips_only_that_mark(): void
    {
        [, $review] = $this->setUpReview();
        $first = $this->mark($review);
        $bad = $this->mark($review, ['number' => 2]);
        $last = $this->mark($review, ['number' => 3]);

        $result = app(MarkLifecycleService::class)->applyAgentUpdates($review, [
            ['id' => $first->id, 'after_image' => $this->tinyPngDataUrl()],
            ['id' => $bad->id, 'after_image' => 'data:image/png;base64,'.base64_encode('not an image')],
            ['id' => $last->id],
        ]);

        $this->assertCount(2, $result['updated']);
        $this->assertSame('invalid_after_image', $result['skipped'][0]['reason']);
        $this->assertSame($bad->id, $result['skipped'][0]['id']);
        $this->assertSame(Annotation::STATUS_OPEN, $bad->fresh()->status);
        $this->assertNotNull($first->fresh()->after_screenshot_id);
        $this->assertSame(Annotation::STATUS_RESOLVED, $last->fresh()->status);
    }

    public function test_resolve_on_a_pending_review_says_to_wait(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_PENDING);
        $mark = $this->mark($review);

        ReviseMyServer::actingAs($user)->tool(ResolveMarksTool::class, [
            'id' => $review->public_id,
            'marks' => [['id' => $mark->id]],
        ])->assertHasErrors(['The human has not decided yet']);
    }

    // ── verify_mark (MCP app) ──────────────────────────────────────────

    public function test_verify_mark_reaches_previous_pass_marks(): void
    {
        [$user, $parent] = $this->setUpReview();
        $parentMark = $this->mark($parent, ['status' => Annotation::STATUS_RESOLVED, 'resolved_at' => now()]);
        $child = $this->childOf($parent);

        ReviseMyServer::actingAs($user)->tool(VerifyMarkTool::class, [
            'review_id' => $child->public_id, 'mark_id' => $parentMark->id, 'action' => 'verify',
        ])->assertHasNoErrors();

        $this->assertSame(Annotation::STATUS_VERIFIED, $parentMark->fresh()->status);
    }

    public function test_verify_mark_refuses_an_approved_review(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_APPROVED);
        $mark = $this->mark($review, ['status' => Annotation::STATUS_VERIFIED, 'verified_at' => now()]);

        ReviseMyServer::actingAs($user)->tool(VerifyMarkTool::class, [
            'review_id' => $review->public_id, 'mark_id' => $mark->id, 'action' => 'reopen',
        ])->assertHasErrors(['can no longer be verified or reopened']);

        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);
    }

    public function test_board_refuses_to_move_marks_on_an_approved_review(): void
    {
        [, $review] = $this->setUpReview(Review::STATUS_APPROVED);
        $mark = $this->mark($review, ['status' => Annotation::STATUS_VERIFIED, 'verified_at' => now()]);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('moveMark', $mark->id, Annotation::STATUS_OPEN)
            ->assertOk();

        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);
    }

    // ── next_action ────────────────────────────────────────────────────

    public function test_changes_requested_with_only_a_note_applies_the_note(): void
    {
        [, $review] = $this->setUpReview();
        $review->update(['decision_note' => 'Make the header sticky.']);

        $next = $review->fresh()->toAgentPayload()['next_action'];

        $this->assertSame('apply_decision_note', $next['action']);
        $this->assertSame('Make the header sticky.', $next['decision_note']);
        $this->assertStringContainsString('Make the header sticky.', $next['summary']);
        $this->assertStringNotContainsString('Every mark is resolved', $next['summary']);
    }

    public function test_decision_note_rides_along_with_marks(): void
    {
        [, $review] = $this->setUpReview();
        $review->update(['decision_note' => 'Also bump the logo.']);
        $this->mark($review);

        $next = $review->fresh()->nextAction();

        $this->assertSame('apply_pins_then_next_pass', $next['action']);
        $this->assertStringContainsString('Also bump the logo.', $next['summary']);
    }

    public function test_reopened_parent_marks_are_carried_over(): void
    {
        [, $parent] = $this->setUpReview();
        $reopened = $this->mark($parent, ['number' => 2, 'status' => Annotation::STATUS_OPEN]);
        $this->mark($parent, ['number' => 1, 'status' => Annotation::STATUS_VERIFIED, 'verified_at' => now()]);
        $child = $this->childOf($parent, Review::STATUS_CHANGES_REQUESTED);
        $this->mark($child, ['number' => 1, 'status' => Annotation::STATUS_RESOLVED]);

        $payload = $child->fresh()->toAgentPayload();

        $this->assertSame('apply_pins_then_next_pass', $payload['next_action']['action']);
        $this->assertSame(1, $payload['next_action']['outstanding_marks']);
        $this->assertSame(1, $payload['next_action']['carried_over_marks']);
        $this->assertStringContainsString('M2 (previous pass)', $payload['next_action']['summary']);
        $this->assertSame(1, $payload['loop']['carried_over_count']);
        $this->assertSame(1, $payload['loop']['outstanding_count']);
        $this->assertSame([$reopened->id], array_column($payload['work_packets']['carried_over'], 'id'));
    }

    public function test_approved_with_open_marks_says_so(): void
    {
        [, $review] = $this->setUpReview(Review::STATUS_APPROVED);
        $this->mark($review, ['severity' => 'nit']);

        $next = $review->fresh()->nextAction();

        $this->assertSame('done', $next['action']);
        $this->assertSame(1, $next['open_marks']);
        $this->assertStringContainsString('do not fix them unless asked', $next['summary']);
    }

    public function test_unanswered_questions_are_listed(): void
    {
        [, $review] = $this->setUpReview();
        $open = $this->mark($review, ['severity' => 'question', 'number' => 1]);
        $this->mark($review, ['severity' => 'question', 'number' => 2, 'question_answer' => 'Yes, keep it.']);

        $next = $review->fresh()->nextAction();

        $this->assertSame([['id' => $open->id, 'number' => 1]], $next['awaiting_answers']);
        $this->assertStringContainsString('M1 is a question with no answer yet', $next['summary']);
    }

    public function test_pending_payload_carries_poll_hint_and_updated_at(): void
    {
        [, $review] = $this->setUpReview(Review::STATUS_PENDING);

        $payload = $review->fresh()->toAgentPayload();

        $this->assertSame(Review::POLL_AFTER_SECONDS, $payload['next_action']['poll_after_seconds']);
        $this->assertNotNull($payload['updated_at']);

        $this->travel(5)->minutes();
        $this->mark($review);

        $this->assertNotSame($payload['updated_at'], $review->fresh()->toAgentPayload()['updated_at']);
    }
}
