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
    public function create(
        Workspace $workspace,
        string $title,
        ?string $context,
        array $images,
        ?string $pageUrl = null,
        ?string $parentPublicId = null,
    ): Review {
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

        $parent = null;
        $pass = 1;

        if ($parentPublicId) {
            $parent = $this->findForWorkspace($workspace, $parentPublicId);

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'No parent review with that id for this try token.',
                ]);
            }

            $pass = ((int) $parent->pass) + 1;
            $context ??= $parent->context;
            $pageUrl ??= $parent->page_url;
        }

        $review = $workspace->reviews()->create([
            'parent_id' => $parent?->id,
            'title' => $title,
            'context' => $context,
            'page_url' => $pageUrl,
            'pass' => $pass,
        ]);

        foreach (array_values($images) as $index => $image) {
            $shot = $this->screenshots->store($review, $image, $index);
            $this->opinions->queue($shot);
        }

        return $review->fresh(['screenshots.annotations', 'screenshots.findings', 'parent']) ?? $review;
    }

    public function addScreenshot(Review $review, string|UploadedFile $image): Screenshot
    {
        if (! $review->isOpenForFeedback()) {
            throw ValidationException::withMessages([
                'review' => 'This review is closed — start a fresh pass with create_review (and parent_id if this is a follow-up).',
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
            ->with(['screenshots.annotations', 'screenshots.findings', 'parent'])
            ->first();
    }
}
