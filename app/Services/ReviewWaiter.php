<?php

namespace App\Services;

use App\Models\Review;
use Generator;
use Illuminate\Support\Carbon;

/**
 * Holds a request until the person acts on a review, for wait_for_review and
 * its REST mirror. Checks Review::activityStamp() every wait_poll_ms and
 * gives up after Review::waitSeconds().
 */
class ReviewWaiter
{
    /**
     * Yields the seconds waited at each heartbeat; returns whether anything
     * moved past $since. A review that can no longer change (approved,
     * expired) answers at once.
     *
     * @return Generator<int, int, mixed, bool>
     */
    public function watch(Review $review, Carbon $since): Generator
    {
        $started = microtime(true);
        $deadline = $started + Review::waitSeconds();
        $pause = max(50, (int) config('revisemy.wait_poll_ms', 2000)) * 1000;
        $beat = max(100, (int) config('revisemy.wait_heartbeat_ms', 10000)) / 1000;
        $nextBeat = $started + $beat;

        while (true) {
            $stamp = $review->activityStamp();

            if ($stamp !== null && $stamp->getTimestamp() > $since->getTimestamp()) {
                return true;
            }

            if (! in_array($review->refresh()->effectiveStatus(), [Review::STATUS_PENDING, Review::STATUS_CHANGES_REQUESTED], true)) {
                return false;
            }

            $now = microtime(true);

            if ($now >= $deadline) {
                return false;
            }

            if ($now >= $nextBeat) {
                $nextBeat += $beat;

                yield (int) round($now - $started);
            }

            usleep((int) min($pause, max(0, ($deadline - $now) * 1_000_000), max(0, ($nextBeat - $now) * 1_000_000) + 1000));
        }
    }

    /**
     * Wait without heartbeats.
     */
    public function until(Review $review, Carbon $since): bool
    {
        $watch = $this->watch($review, $since);

        foreach ($watch as $ignored) {
            // Nobody is listening for a heartbeat.
        }

        return $watch->getReturn();
    }

    /**
     * Where to start waiting: what the agent last saw, or now.
     */
    public function since(Review $review, ?string $since): Carbon
    {
        return $since !== null && $since !== '' ? Carbon::parse($since) : ($review->activityStamp() ?? now());
    }
}
