<?php

namespace App\Events;

use App\Models\Annotation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A mark's lifecycle status changed. Broadcast on the review it belongs to and
 * that review's direct children, so the review page, the board, and any child
 * "previous pass" panel all refresh live.
 *
 * Channels are keyed by the review's unguessable capability tokens — the same
 * secret that already gates the page — so no separate channel auth is needed.
 */
class MarkUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Annotation $annotation) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $review = $this->annotation->loadMissing('screenshot.review.children')->screenshot?->review;

        if (! $review) {
            return [];
        }

        return collect([$review])
            ->merge($review->children)
            ->flatMap(fn ($r) => [
                new Channel('review.'.$r->token),
                new Channel('review.'.$r->share_token),
            ])
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'MarkUpdated';
    }

    /**
     * Thin payload — listeners re-fetch the review; tokens never go over the wire.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->annotation->id,
            'status' => $this->annotation->status,
        ];
    }
}
