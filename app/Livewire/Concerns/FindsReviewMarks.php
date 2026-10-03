<?php

namespace App\Livewire\Concerns;

use App\Models\Annotation;
use App\Models\Review;

/**
 * A mark this review's owner may act on: one on this pass, or on the pass it
 * built on. Shared by the review page and the board so both answer the same.
 *
 * @property Review $review
 */
trait FindsReviewMarks
{
    protected function ownedAnnotation(int $annotationId): ?Annotation
    {
        $reviewIds = array_filter([$this->review->id, $this->review->parent_id]);

        return Annotation::query()
            ->whereKey($annotationId)
            ->whereHas('screenshot', fn ($q) => $q->whereIn('review_id', $reviewIds))
            ->first();
    }
}
