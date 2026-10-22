<?php

namespace App\Events;

use App\Models\Review;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An agent took a review that was started on the site. Broadcast on the owner
 * link only, so an open review page swaps its handoff panel. Kept apart from
 * ReviewDecided, which fires the review's webhook.
 */
class ReviewHandedOff implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Review $review) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('review.'.$this->review->token)];
    }

    public function broadcastAs(): string
    {
        return 'ReviewHandedOff';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->review->public_id];
    }
}
