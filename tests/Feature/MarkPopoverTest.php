<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarkPopoverTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    /** @return array{Review, Annotation} */
    protected function reviewWithMark(int $shots = 1): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Popover review',
            'images' => array_fill(0, $shots, $this->tinyPngDataUrl()),
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();

        $mark = $review->screenshots()->orderBy('id')->get()->last()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'The button text is hard to read',
            'suggested_copy' => 'Start free', 'number' => 1,
        ]);

        return [$review, $mark];
    }

    public function test_opening_a_mark_shows_it_in_the_popover(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->assertDontSee('data-mark-popover', false)
            ->call('openMark', $mark->id)
            ->assertSet('openMarkId', $mark->id)
            ->assertSee('data-mark-popover="'.$mark->id.'"', false)
            ->assertSee('Suggested copy')
            ->assertSee('Start free')
            ->call('closeMark')
            ->assertSet('openMarkId', null)
            ->assertDontSee('data-mark-popover', false);
    }

    public function test_the_answer_to_a_question_shows_when_open(): void
    {
        [$review, $mark] = $this->reviewWithMark();
        $mark->update(['severity' => Annotation::SEVERITY_QUESTION, 'question_answer' => 'Yes, keep the old logo']);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->assertSee('Yes, keep the old logo');
    }

    public function test_opening_a_mark_on_another_shot_switches_to_it(): void
    {
        [$review, $mark] = $this->reviewWithMark(shots: 2);

        Livewire::test('review-page', ['token' => $review->token])
            ->assertSet('activeScreenshotIndex', 0)
            ->call('openMark', $mark->id)
            ->assertSet('activeScreenshotIndex', 1)
            ->assertSet('openMarkId', $mark->id);
    }

    public function test_a_mark_from_another_review_does_not_open(): void
    {
        [$review] = $this->reviewWithMark();
        [, $other] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('openMark', $other->id)
            ->assertSet('openMarkId', null);
    }

    public function test_guests_can_open_a_mark_too(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('openMark', $mark->id)
            ->assertSet('openMarkId', $mark->id)
            ->assertSee('The button text is hard to read')
            ->assertDontSee('wire:click="deletePin('.$mark->id.')"', false);
    }

    public function test_removing_the_open_mark_closes_it(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->call('deletePin', $mark->id)
            ->assertSet('openMarkId', null);
    }

    public function test_starting_a_new_mark_closes_the_open_one(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->call('startPin', 0.2, 0.2)
            ->assertSet('openMarkId', null)
            ->assertDontSee('data-mark-popover', false);
    }

    public function test_a_link_can_open_a_mark(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        $this->get('/r/'.$review->token.'?mark='.$mark->id)
            ->assertOk()
            ->assertSee('data-mark-popover="'.$mark->id.'"', false);

        $this->get('/r/'.$review->token.'?mark=999999')
            ->assertOk()
            ->assertDontSee('data-mark-popover', false);
    }

    public function test_commenting_from_the_popover_keeps_the_box_there(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        $html = Livewire::test('review-page', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->call('startMarkComment', $mark->id)
            ->html();

        // One comment box, in the popover rather than the card.
        $this->assertSame(1, substr_count($html, 'wire:model="markCommentBody"'));
        $popover = substr($html, strpos($html, 'data-mark-popover'));
        $this->assertStringContainsString('wire:model="markCommentBody"', $popover);
    }
}
