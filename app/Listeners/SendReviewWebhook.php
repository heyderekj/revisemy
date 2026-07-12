<?php

namespace App\Listeners;

use App\Events\ReviewDecided;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Event-driven side of the checkup loop: when the human decides, POST the
 * agent payload to the review's webhook_url so pipelines can gate on approval
 * instead of polling get_review. The body is HMAC-signed with the review's
 * owner token — the same secret the creator already received — so receivers
 * can verify authenticity without extra key exchange.
 */
class SendReviewWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function handle(ReviewDecided $event): void
    {
        $review = $event->review;

        if (! $review->webhook_url) {
            return;
        }

        $body = json_encode([
            'event' => 'review.decided',
            'decided_at' => $review->decision_at?->toIso8601String(),
            'review' => $review->toAgentPayload(),
        ], JSON_UNESCAPED_SLASHES);

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-ReviseMy-Event' => 'review.decided',
                'X-ReviseMy-Review' => $review->public_id,
                'X-ReviseMy-Signature' => 'sha256='.hash_hmac('sha256', (string) $body, $review->token),
            ])
            ->withBody((string) $body, 'application/json')
            ->post($review->webhook_url);

        if ($response->failed()) {
            Log::warning('Review webhook delivery failed', [
                'review' => $review->public_id,
                'status' => $response->status(),
            ]);

            // Let the queue retry with backoff; give up after $tries.
            $response->throw();
        }
    }
}
