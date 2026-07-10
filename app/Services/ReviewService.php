<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Screenshot;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(
        protected ScreenshotStorage $screenshots,
    ) {}

    /**
     * @param  list<string|UploadedFile>  $images
     */
    public function create(Workspace $workspace, string $title, ?string $context, array $images): Review
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
        ]);

        foreach (array_values($images) as $index => $image) {
            $this->screenshots->store($review, $image, $index);
        }

        return $review->fresh(['screenshots.annotations']) ?? $review;
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

        return $this->screenshots->store($review, $image, $sortOrder);
    }

    public function findForWorkspace(Workspace $workspace, string $publicId): ?Review
    {
        return $workspace->reviews()
            ->where('public_id', $publicId)
            ->with(['screenshots.annotations'])
            ->first();
    }
}
