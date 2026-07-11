<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

class MarkLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    protected function setUpReview(): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Lifecycle',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $review->update(['status' => Review::STATUS_CHANGES_REQUESTED, 'decision_at' => now()]);

        return [$token, $review];
    }

    public function test_new_marks_default_open_and_keeps_are_verified(): void
    {
        [, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();

        $must = $shot->annotations()->create(['x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1]);
        $keep = $shot->annotations()->create(['x' => 0.3, 'y' => 0.3, 'severity' => 'keep', 'body' => 'Leave', 'number' => 2]);

        $this->assertSame(Annotation::STATUS_OPEN, $must->fresh()->status);
        $this->assertSame(Annotation::STATUS_VERIFIED, $keep->fresh()->status);
    }

    public function test_agent_resolves_marks_over_rest_and_payload_reflects_it(): void
    {
        [$token, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();

        $mark = $shot->annotations()->create(['x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1]);

        $response = $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [
                ['id' => $mark->id, 'status' => 'resolved', 'note' => 'Bumped contrast to AA.'],
            ],
        ])->assertOk();

        $this->assertSame(1, $response->json('updated'));

        $mark->refresh();
        $this->assertSame(Annotation::STATUS_RESOLVED, $mark->status);
        $this->assertSame('Bumped contrast to AA.', $mark->resolution_note);
        $this->assertNotNull($mark->resolved_at);

        $packets = $response->json('review.work_packets');
        $this->assertEmpty($packets['must_fix']);                       // no longer outstanding
        $this->assertCount(1, $packets['awaiting_verification']);       // waiting on the human
        $this->assertCount(1, $packets['pins']);                        // still listed in full
        $this->assertSame(0, $response->json('review.loop.outstanding_count'));
        $this->assertSame(1, $response->json('review.loop.resolved_count'));
    }

    public function test_next_action_flips_to_open_next_pass_when_all_resolved(): void
    {
        [$token, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();
        $mark = $shot->annotations()->create(['x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1]);

        $this->assertSame('apply_pins_then_next_pass', $review->fresh()->toAgentPayload()['next_action']['action']);

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Done.']],
        ])->assertOk();

        $this->assertSame('open_next_pass', $review->fresh()->toAgentPayload()['next_action']['action']);
    }

    public function test_agent_cannot_verify_a_mark(): void
    {
        [$token, $review] = $this->setUpReview();
        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
        ]);

        // "verified" is not an allowed status for the resolve endpoint.
        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'verified']],
        ])->assertStatus(422);

        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
    }

    public function test_resolve_is_blocked_unless_changes_requested(): void
    {
        [$token, $review] = $this->setUpReview();
        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
        ]);
        $review->update(['status' => Review::STATUS_PENDING]);

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'x']],
        ])->assertStatus(422);

        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
    }

    public function test_marks_are_scoped_to_the_workspace(): void
    {
        [, $reviewA] = $this->setUpReview();
        $markA = $reviewA->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
        ]);

        // A second, separate try token / workspace. (actingAs switches the
        // Sanctum user reliably mid-test where a fresh withToken does not.)
        $tokenB = $this->postJson('/api/try-token')->json('token');
        $userB = PersonalAccessToken::findToken($tokenB)->tokenable;

        $idB = $this->actingAs($userB, 'sanctum')->postJson('/api/reviews', [
            'title' => 'Other', 'images' => [$this->tinyPngDataUrl()],
        ])->json('id');
        $reviewB = Review::query()->where('public_id', $idB)->firstOrFail();
        $reviewB->update(['status' => Review::STATUS_CHANGES_REQUESTED, 'decision_at' => now()]);

        // Workspace B tries to resolve A's mark by id — it must not take.
        $this->actingAs($userB, 'sanctum')->postJson('/api/reviews/'.$reviewB->public_id.'/marks/resolve', [
            'marks' => [['id' => $markA->id, 'status' => 'resolved', 'note' => 'nope']],
        ])->assertStatus(422);

        $this->assertSame(Annotation::STATUS_OPEN, $markA->fresh()->status);
    }

    public function test_owner_verifies_and_reopens_via_component_and_approve_promotes_resolved(): void
    {
        [$token, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();
        $mark = $shot->annotations()->create(['x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1]);

        // Agent resolves it.
        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Done.']],
        ])->assertOk();

        // Put the review back to pending so the owner can act, then verify.
        $review->update(['status' => Review::STATUS_PENDING]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('verifyMark', $mark->id)
            ->assertOk();
        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);

        // Reopen sends it back to open.
        Livewire::test('review-page', ['token' => $review->token])
            ->call('reopenMark', $mark->id)
            ->assertOk();
        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
    }

    public function test_approve_promotes_resolved_marks_to_verified(): void
    {
        [$token, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();
        $mark = $shot->annotations()->create(['x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1]);

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Done.']],
        ])->assertOk();

        $review->update(['status' => Review::STATUS_PENDING]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('approve')
            ->assertOk();

        $this->assertSame(Review::STATUS_APPROVED, $review->fresh()->status);
        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);
    }

    public function test_previous_pass_marks_surface_on_the_child_payload(): void
    {
        [$token, $parent] = $this->setUpReview();
        $mark = $parent->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
        ]);

        $this->withToken($token)->postJson('/api/reviews/'.$parent->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Done.']],
        ])->assertOk();

        $child = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Pass 2',
            'parent_id' => $parent->public_id,
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        $this->assertNotNull($child['previous_pass']);
        $this->assertSame($parent->public_id, $child['previous_pass']['id']);
        $this->assertSame(1, $child['previous_pass']['resolved_count']);
        $this->assertSame(Annotation::STATUS_RESOLVED, $child['previous_pass']['marks'][0]['status']);

        // The child review page renders the previous-pass panel for the owner.
        $childReview = Review::query()->where('public_id', $child['id'])->firstOrFail();
        Livewire::test('review-page', ['token' => $childReview->token])
            ->assertOk()
            ->assertSee('Previous pass marks')
            ->assertSee('Done.');
    }
}
