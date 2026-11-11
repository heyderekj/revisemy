<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FeedbackRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_links_can_be_revoked_individually_and_cannot_open_other_reviews(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('shot.png', 'sample');
        $review = $this->createReview();
        $shot = $review->screenshots()->create(['path' => 'shot.png', 'disk' => 'public']);
        $otherShot = $this->createReview()->screenshots()->create(['path' => 'shot.png', 'disk' => 'public']);

        $owner = Livewire::test('review-page', ['token' => $review->token]);
        foreach (['Maya', 'Jordan'] as $name) {
            $owner->call('startFeedbackRequest')
                ->set('feedbackName', $name)
                ->set('feedbackQuestion', 'Is this clear?')
                ->call('createFeedbackRequest')
                ->assertHasNoErrors();
        }

        [$maya, $jordan] = $review->feedbackRequests()->orderBy('id')->get();
        $this->assertNull($maya->email);
        $this->assertNotSame($maya->token, $jordan->token);

        $this->get($maya->url())->assertOk()
            ->assertDontSee($review->token)
            ->assertDontSee($review->share_token)
            ->assertDontSee('jordan@example.test');
        $shotUrl = route('feedback.screenshot', [$maya->token, $shot]);
        $this->get($shotUrl)->assertOk();
        $this->get(route('feedback.screenshot', [$maya->token, $otherShot]))->assertNotFound();
        $this->get(route('reviews.show', $maya->token))->assertNotFound();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('revokeFeedbackRequest', $maya->id)->assertStatus(404);
        $owner->call('revokeFeedbackRequest', $maya->id)->assertHasNoErrors();
        $this->get($maya->url())->assertNotFound();
        $this->get($shotUrl)->assertNotFound();
        $this->get($jordan->url())->assertOk();

        $jordan->update(['expires_at' => now()->subSecond()]);
        $this->get($jordan->url())->assertNotFound();
    }

    public function test_review_request_can_be_answered_once(): void
    {
        $review = $this->createReview();
        Livewire::test('review-page', ['token' => $review->token])
            ->call('startFeedbackRequest')
            ->set('feedbackName', 'Maya')
            ->set('feedbackQuestion', 'Does the page make sense?')
            ->call('createFeedbackRequest')
            ->assertHasNoErrors();

        $feedback = $review->feedbackRequests()->sole();
        $this->post($feedback->url(), ['name' => 'Maya', 'response' => 'Yes, it does.'])->assertRedirect($feedback->url());
        $this->post($feedback->url(), ['name' => 'Other', 'response' => 'Duplicate.'])->assertRedirect($feedback->url());
        $this->assertSame('Yes, it does.', $feedback->fresh()->response);
        $this->assertDatabaseCount('annotation_comments', 0);
    }

    public function test_group_has_independent_links_and_tracks_replies_to_the_shared_question(): void
    {
        $review = $this->createReview();
        $owner = Livewire::test('review-page', ['token' => $review->token])
            ->call('startFeedbackRequest')
            ->set('feedbackAudience', 'group')
            ->set('feedbackQuestion', 'Is the next step clear?')
            ->set('feedbackGroupNames', "Maya\n maya ")
            ->call('createFeedbackRequest')
            ->assertHasErrors('feedbackGroupNames');

        $this->assertDatabaseCount('feedback_requests', 0);

        $owner->set('feedbackGroupNames', "Maya\r\n\r\nJordan")
            ->call('createFeedbackRequest')
            ->assertHasNoErrors()
            ->assertSee('0 of 2 replied');

        [$maya, $jordan] = $review->feedbackRequests()->orderBy('id')->get();
        $this->assertNotNull($maya->group_id);
        $this->assertSame($maya->group_id, $jordan->group_id);
        $this->assertSame($maya->question, $jordan->question);
        $this->assertNotSame($maya->url(), $jordan->url());

        $this->post($maya->url(), ['name' => 'Maya', 'response' => 'Yes, the label is clear.'])->assertRedirect($maya->url());
        $owner->call('loadReview')->assertSee('1 of 2 replied');
        $this->assertNull($jordan->fresh()->responded_at);
        $owner->call('revokeFeedbackRequest', $maya->id)->assertHasNoErrors();
        $this->get($maya->url())->assertNotFound();
        $this->get($jordan->url())->assertOk()->assertSee('Is the next step clear?');
    }

    private function createReview(): Review
    {
        return Workspace::create(['name' => 'Feedback testing'])->reviews()->create([
            'title' => 'Checkout',
            'comments_enabled' => true,
        ]);
    }
}
