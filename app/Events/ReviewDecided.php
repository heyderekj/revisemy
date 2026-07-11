<?php

namespace App\Events;

use App\Models\Review;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The human approved or requested changes on a review. Broadcast on the review
 * (and its parent, whose "previous pass" view may change) so every open page flips.
 */
class ReviewDecided implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Review $review) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return collect([$this->review, $this->review->parent])
            ->filter()
            ->flatMap(fn (Review $r) => [
                new Channel('review.'.$r->token),
                new Channel('review.'.$r->share_token),
            ])
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'ReviewDecided';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->review->public_id,
            'status' => $this->review->status,
        ];
    }
}
