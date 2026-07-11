<?php

namespace Tests\Feature;

use App\Events\MarkUpdated;
use App\Events\ReviewDecided;
use App\Models\Annotation;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BroadcastingTest extends TestCase
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
            'title' => 'Broadcast', 'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $review->update(['status' => Review::STATUS_CHANGES_REQUESTED, 'decision_at' => now()]);
        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
        ]);

        return [$token, $review, $mark];
    }

    public function test_resolving_a_mark_broadcasts_mark_updated_on_the_token_channels(): void
    {
        Event::fake([MarkUpdated::class]);
        [$token, $review, $mark] = $this->reviewWithMark();

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Done.']],
        ])->assertOk();

        Event::assertDispatched(MarkUpdated::class, function (MarkUpdated $event) use ($mark, $review) {
            $channels = collect($event->broadcastOn())->map->name;

            return $event->annotation->id === $mark->id
                && $event->broadcastWith() === ['id' => $mark->id, 'status' => Annotation::STATUS_RESOLVED]
                && $channels->contains('review.'.$review->token)
                && $channels->contains('review.'.$review->share_token);
        });
    }

    public function test_broadcast_payload_never_leaks_the_tokens(): void
    {
        Event::fake([MarkUpdated::class]);
        [$token, $review, $mark] = $this->reviewWithMark();

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Done.']],
        ])->assertOk();

        Event::assertDispatched(MarkUpdated::class, function (MarkUpdated $event) use ($review) {
            $json = json_encode($event->broadcastWith());

            return ! str_contains($json, $review->token)
                && ! str_contains($json, $review->share_token);
        });
    }

    public function test_deciding_a_review_broadcasts_review_decided(): void
    {
        Event::fake([ReviewDecided::class]);
        [, $review] = $this->reviewWithMark();
        $review->update(['status' => Review::STATUS_PENDING]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('approve')
            ->assertOk();

        Event::assertDispatched(ReviewDecided::class, function (ReviewDecided $event) use ($review) {
            $channels = collect($event->broadcastOn())->map->name;

            return $event->review->is($review)
                && $channels->contains('review.'.$review->token);
        });
    }
}
