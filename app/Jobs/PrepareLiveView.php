<?php

namespace App\Jobs;

use App\Models\Review;
use App\Services\Capture\LiveArchive;
use App\Services\Capture\LiveFidelity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * After a capture: keep the page's stylesheets, fonts and images with the
 * review (LiveArchive), then score how closely Live matches the screenshot
 * (LiveFidelity). Until it's done, Live frames the page as captured and the
 * review opens on the screenshot.
 */
class PrepareLiveView implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $reviewId) {}

    public function handle(LiveArchive $archive, LiveFidelity $fidelity): void
    {
        $review = Review::query()->with('screenshots')->find($this->reviewId);

        if (! $review || ! $review->dom_path) {
            return;
        }

        try {
            $archive->prepare($review);
            $fidelity->check($review);
        } catch (Throwable $e) {
            Log::warning('PrepareLiveView failed', ['review' => $review->public_id, 'error' => $e->getMessage()]);
        }
    }
}
