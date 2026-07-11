<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    protected function reviewWithMark(string $status = Annotation::STATUS_OPEN): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Board review',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $review->update(['status' => Review::STATUS_CHANGES_REQUESTED, 'decision_at' => now()]);

        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix the CTA', 'number' => 1, 'status' => $status,
        ]);

        return [$review, $mark];
    }

    public function test_board_route_renders_for_owner_with_columns(): void
    {
        [$review] = $this->reviewWithMark();

        $this->get('/r/'.$review->token.'/board')
            ->assertOk()
            ->assertSee('Board review')
            ->assertSee('Open')
            ->assertSee('In progress')
            ->assertSee('Resolved')
            ->assertSee('Verified')
            ->assertSee('Fix the CTA');
    }

    public function test_board_rejects_the_guest_share_token(): void
    {
        [$review] = $this->reviewWithMark();

        // The board is owner-only; the share token must not open it.
        $this->get('/r/'.$review->share_token.'/board')->assertNotFound();
    }

    public function test_keep_marks_are_not_shown_on_the_board(): void
    {
        [$review] = $this->reviewWithMark();
        $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.2, 'y' => 0.2, 'severity' => 'keep', 'body' => 'Love this spacing', 'number' => 2,
        ]);

        Livewire::test('review-board', ['token' => $review->token])
            ->assertOk()
            ->assertSee('Fix the CTA')
            ->assertDontSee('Love this spacing');
    }

    public function test_human_can_verify_a_resolved_mark_by_dropping_into_verified(): void
    {
        [$review, $mark] = $this->reviewWithMark(Annotation::STATUS_RESOLVED);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('moveMark', $mark->id, Annotation::STATUS_VERIFIED)
            ->assertOk();

        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);
    }

    public function test_human_can_reopen_by_dropping_into_open(): void
    {
        [$review, $mark] = $this->reviewWithMark(Annotation::STATUS_RESOLVED);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('moveMark', $mark->id, Annotation::STATUS_OPEN)
            ->assertOk();

        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
    }

    public function test_human_cannot_drag_a_mark_into_in_progress_or_resolved(): void
    {
        [$review, $mark] = $this->reviewWithMark(Annotation::STATUS_OPEN);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('moveMark', $mark->id, Annotation::STATUS_RESOLVED)
            ->assertOk();

        // Those columns are agent-driven (resolve_marks), so the drop is a no-op.
        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
    }

    public function test_verifying_an_unresolved_mark_is_a_noop(): void
    {
        [$review, $mark] = $this->reviewWithMark(Annotation::STATUS_OPEN);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('moveMark', $mark->id, Annotation::STATUS_VERIFIED)
            ->assertOk();

        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
    }
}
