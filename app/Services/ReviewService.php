<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Screenshot;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(
        protected ScreenshotStorage $screenshots,
        protected SecondOpinionService $opinions,
    ) {}

    /**
     * @param  list<string|UploadedFile>  $images
     */
    public function create(Workspace $workspace, string $title, ?string $context, array $images, ?string $pageUrl = null): Review
    {
        if ($images === []) {
            throw ValidationException::withMessages([
                'images' => 'Add at least one screenshot so there is something to look at.',
            ]);
        }

        if (count($images) > 5) {
            throw ValidationException::withMessages([
                'images' => 'Keep it to 5 screenshots per review for now.',
            ]);
        }

        $review = $workspace->reviews()->create([
            'title' => $title,
            'context' => $context,
            'page_url' => $pageUrl,
        ]);

        foreach (array_values($images) as $index => $image) {
            $shot = $this->screenshots->store($review, $image, $index);
            $this->opinions->queue($shot);
        }

        return $review->fresh(['screenshots.annotations', 'screenshots.findings']) ?? $review;
    }

    public function addScreenshot(Review $review, string|UploadedFile $image): Screenshot
    {
        if (! $review->isOpenForFeedback()) {
            throw ValidationException::withMessages([
                'review' => 'This review is closed — start a fresh one if you need another pass.',
            ]);
        }

        if ($review->screenshots()->count() >= 5) {
            throw ValidationException::withMessages([
                'images' => 'This review already has 5 screenshots.',
            ]);
        }

        $sortOrder = (int) $review->screenshots()->max('sort_order') + 1;

        $shot = $this->screenshots->store($review, $image, $sortOrder);
        $this->opinions->queue($shot);

        return $shot;
    }

    public function findForWorkspace(Workspace $workspace, string $publicId): ?Review
    {
        return $workspace->reviews()
            ->where('public_id', $publicId)
            ->with(['screenshots.annotations', 'screenshots.findings'])
            ->first();
    }
}
