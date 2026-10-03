<?php

namespace App\Listeners;

use App\Events\ReviewDecided;
use App\Models\Review;
use App\Support\OutboundUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Event-driven side of the checkup loop: when the human decides, POST the
 * agent payload to the review's webhook_url so pipelines can gate on approval
 * instead of polling get_review. The body is HMAC-signed with the review's
 * owner token — the same secret the creator already received — so receivers
 * can verify authenticity without extra key exchange.
 *
 * Hardened the way Koati's doorbells are: the address is checked against
 * private networks again before every send (DNS can change after it was
 * saved), redirects are a failure rather than followed, and a webhook that
 * fails Review::WEBHOOK_PAUSE_AFTER deliveries in a row is paused, with the
 * reason kept, instead of being retried forever.
 */
class SendReviewWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function handle(ReviewDecided $event): void
    {
        $review = $event->review->fresh() ?? $event->review;

        if (! $review->webhook_url || $review->webhook_paused_at) {
            return;
        }

        if ($reason = OutboundUrl::reasonToReject($review->webhook_url)) {
            // Not worth a retry: the address itself is refused.
            $this->recordFailure($review, "Refused: {$reason}.");
            $this->delete();

            return;
        }

        $body = json_encode([
            'event' => 'review.decided',
            'decided_at' => $review->decision_at?->toIso8601String(),
            'review' => $review->toAgentPayload(),
        ], JSON_UNESCAPED_SLASHES);

        $response = Http::timeout(10)
            ->withoutRedirecting()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-ReviseMy-Event' => 'review.decided',
                'X-ReviseMy-Review' => $review->public_id,
                'X-ReviseMy-Signature' => 'sha256='.hash_hmac('sha256', (string) $body, $review->token),
            ])
            ->withBody((string) $body, 'application/json')
            ->post($review->webhook_url);

        if ($response->successful()) {
            $review->forceFill(['webhook_failures' => 0, 'webhook_last_error' => null])->save();

            return;
        }

        Log::warning('Review webhook delivery failed', [
            'review' => $review->public_id,
            'status' => $response->status(),
        ]);

        // Let the queue retry with backoff; failed() counts it once retries run out.
        throw new RuntimeException($response->redirect()
            ? "Answered with a redirect ({$response->status()}), which isn't followed."
            : "Answered {$response->status()}.");
    }

    public function failed(ReviewDecided $event, Throwable $e): void
    {
        $review = $event->review->fresh();

        if ($review) {
            $this->recordFailure($review, $e->getMessage());
        }
    }

    protected function recordFailure(Review $review, string $error): void
    {
        $failures = (int) $review->webhook_failures + 1;

        $review->forceFill([
            'webhook_failures' => $failures,
            'webhook_last_error' => Str::limit($error, 250),
            'webhook_paused_at' => $failures >= Review::WEBHOOK_PAUSE_AFTER ? now() : $review->webhook_paused_at,
        ])->save();
    }
}
