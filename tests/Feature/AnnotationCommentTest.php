<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AnnotationCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    protected function reviewWithMark(): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Comment review',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();

        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5,
            'y' => 0.5,
            'severity' => 'must-fix',
            'body' => 'Fix the CTA',
            'number' => 1,
            'status' => Annotation::STATUS_OPEN,
        ]);

        return [$review, $mark];
    }

    public function test_owner_can_comment_from_the_board_sheet(): void
    {
        [$review, $mark] = $this->reviewWithMark();
        $review->update(['status' => Review::STATUS_CHANGES_REQUESTED, 'decision_at' => now()]);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->set('commentAuthor', 'Derek')
            ->set('commentBody', 'Can we try a darker rose?')
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertSee('Can we try a darker rose?')
            ->assertSee('Derek');

        $this->assertDatabaseHas('annotation_comments', [
            'annotation_id' => $mark->id,
            'author' => 'Derek',
            'from_owner' => true,
            'body' => 'Can we try a darker rose?',
        ]);
    }

    public function test_board_comment_defaults_author_to_owner(): void
    {
        [$review, $mark] = $this->reviewWithMark();
        $review->update(['status' => Review::STATUS_CHANGES_REQUESTED, 'decision_at' => now()]);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->set('commentBody', 'Looks good after the contrast bump.')
            ->call('addComment')
            ->assertHasNoErrors();

        $this->assertSame('Owner', AnnotationComment::query()->where('annotation_id', $mark->id)->value('author'));
    }

    public function test_guest_can_comment_on_a_mark_from_the_review_page(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startMarkComment', $mark->id)
            ->set('guestName', 'Alex')
            ->set('markCommentBody', 'Agree — CTA needs more weight.')
            ->call('addMarkComment', $mark->id)
            ->assertHasNoErrors()
            ->assertSee('Agree — CTA needs more weight.')
            ->assertSee('Alex');

        $this->assertDatabaseHas('annotation_comments', [
            'annotation_id' => $mark->id,
            'author' => 'Alex',
            'from_owner' => false,
        ]);
    }

    public function test_guest_comment_requires_a_name(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startMarkComment', $mark->id)
            ->set('markCommentBody', 'Nameless feedback')
            ->call('addMarkComment', $mark->id)
            ->assertHasErrors(['guestName']);
    }

    public function test_guest_name_rejects_emoji_and_control_characters(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startMarkComment', $mark->id)
            ->set('guestName', "Alex\n🔥")
            ->set('markCommentBody', 'Still a useful note.')
            ->call('addMarkComment', $mark->id)
            ->assertHasErrors(['guestName']);

        $this->assertSame(0, $mark->comments()->count());
    }

    public function test_guest_comment_strips_control_characters_from_body(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startMarkComment', $mark->id)
            ->set('guestName', 'José')
            ->set('markCommentBody', "Looks good.\0\x01 More weight on the CTA.")
            ->call('addMarkComment', $mark->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('annotation_comments', [
            'annotation_id' => $mark->id,
            'author' => 'José',
            'body' => 'Looks good. More weight on the CTA.',
        ]);
    }

    public function test_guest_feedback_is_rate_limited(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        $page = Livewire::test('review-page', ['token' => $review->share_token]);

        for ($i = 0; $i < 20; $i++) {
            $page->call('startMarkComment', $mark->id)
                ->set('guestName', 'Alex')
                ->set('markCommentBody', "Note number {$i}")
                ->call('addMarkComment', $mark->id)
                ->assertHasNoErrors();
        }

        $page->call('startMarkComment', $mark->id)
            ->set('guestName', 'Alex')
            ->set('markCommentBody', 'One too many')
            ->call('addMarkComment', $mark->id)
            ->assertHasErrors(['guestName']);

        $this->assertSame(20, $mark->comments()->count());
    }

    public function test_new_reviews_default_guest_link_expiry_to_seven_days(): void
    {
        [$review] = $this->reviewWithMark();

        $this->assertNotNull($review->share_expires_at);
        $this->assertTrue($review->share_expires_at->between(now()->addDays(6), now()->addDays(8)));
    }

    public function test_regenerating_the_guest_link_resets_expiry_to_seven_days(): void
    {
        [$review] = $this->reviewWithMark();
        $review->update(['share_expires_at' => now()->addDays(14)->endOfDay()]);
        $oldToken = $review->share_token;

        Livewire::test('review-page', ['token' => $review->token])
            ->call('regenerateShareToken')
            ->assertOk();

        $fresh = $review->fresh();
        $this->assertNotSame($oldToken, $fresh->share_token);
        $this->assertTrue($fresh->share_expires_at->between(now()->addDays(6), now()->addDays(8)));
    }

    public function test_owner_can_disable_comments(): void
    {
        [$review, $mark] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('toggleComments')
            ->assertOk();

        $this->assertFalse($review->fresh()->allowsComments());

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startMarkComment', $mark->id)
            ->assertSet('activeCommentMarkId', null);

        Livewire::test('review-board', ['token' => $review->token])
            ->call('openMark', $mark->id)
            ->set('commentBody', 'Should not post')
            ->call('addComment')
            ->assertOk();

        $this->assertSame(0, $mark->comments()->count());
    }

    public function test_owner_can_expire_the_guest_link(): void
    {
        [$review] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('setShareExpiry', '7d')
            ->assertOk();

        $this->assertNotNull($review->fresh()->share_expires_at);
        $this->assertTrue($review->fresh()->allowsGuestAccess());
        $this->assertTrue($review->fresh()->share_expires_at->between(now()->addDays(6), now()->addDays(8)));

        $review->update(['share_expires_at' => now()->subMinute()]);

        $this->assertFalse($review->fresh()->allowsGuestAccess());

        $this->get('/r/'.$review->share_token)
            ->assertOk()
            ->assertSee('This guest link has expired');
    }

    public function test_owner_can_set_a_custom_guest_link_expiry_date(): void
    {
        [$review] = $this->reviewWithMark();
        $date = now()->addDays(10)->format('Y-m-d');

        Livewire::test('review-page', ['token' => $review->token])
            ->call('setShareExpiryDate', $date)
            ->assertHasNoErrors()
            ->assertOk();

        $this->assertSame($date, $review->fresh()->share_expires_at->timezone(config('app.timezone'))->format('Y-m-d'));
    }

    public function test_custom_guest_link_expiry_rejects_past_dates(): void
    {
        [$review] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('setShareExpiryDate', now()->subDay()->format('Y-m-d'))
            ->assertHasErrors(['shareExpiryDate']);
    }

    public function test_owner_can_clear_guest_link_expiry(): void
    {
        [$review] = $this->reviewWithMark();
        $review->update(['share_expires_at' => now()->addDay()]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('setShareExpiry', 'never')
            ->assertOk();

        $this->assertNull($review->fresh()->share_expires_at);
    }

    public function test_owner_can_set_fourteen_day_guest_link_expiry(): void
    {
        [$review] = $this->reviewWithMark();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('setShareExpiry', '14d')
            ->assertOk();

        $this->assertTrue($review->fresh()->share_expires_at->between(now()->addDays(13), now()->addDays(15)));
    }
}
