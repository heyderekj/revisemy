<?php

namespace App\Livewire\Concerns;

use App\Models\Review;
use App\Services\ReviewService;

/**
 * The review's status menu: set the status, archive, restore. Shared by the
 * review page and the board so both answer the same. Owner only.
 *
 * @property Review $review
 */
trait ChangesReviewStatus
{
    abstract protected function managesReview(): bool;

    abstract protected function reloadReview(): void;

    public function setReviewStatus(string $status): void
    {
        if (! $this->managesReview()) {
            return;
        }

        app(ReviewService::class)->setStatus($this->review, $status);

        $this->reloadReview();
    }

    public function archiveReview(): void
    {
        $this->putAway(true);
    }

    public function restoreReview(): void
    {
        $this->putAway(false);
    }

    protected function putAway(bool $archived): void
    {
        if (! $this->managesReview()) {
            return;
        }

        app(ReviewService::class)->archive($this->review, $archived);

        $this->reloadReview();
        $this->dispatch('rm-toast', message: $archived ? 'Archived. It’s under Archived in Your reviews.' : 'Back in Your reviews.');
    }
}
