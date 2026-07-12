<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\SecondOpinionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        $reviews = $workspace->reviews()
            ->latest()
            ->limit(20)
            ->with(['screenshots.annotations', 'screenshots.findings'])
            ->get()
            ->map(fn (Review $review) => $review->toAgentPayload());

        return response()->json(['reviews' => $reviews]);
    }

    public function store(Request $request, ReviewService $reviews): JsonResponse
    {
        $this->throttle($request, 'create-review', 30);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'context' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'in:ui,website,presentation,email'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'webhook_url' => ['nullable', 'string', 'max:2048'],
            'parent_id' => ['nullable', 'string'],
            'images' => ['nullable', 'array', 'min:1', 'max:5'],
            'images.*' => ['required'],
            'capture_url' => ['nullable', 'boolean'],
            'pdf' => ['nullable', 'string'],
            'html' => ['nullable', 'string', 'max:500000'],
        ]);

        $review = $reviews->createFromRequest($request->user()->workspace, $data);

        return response()->json($review->toAgentPayload(), 201);
    }

    public function show(Request $request, string $publicId, ReviewService $reviews): JsonResponse
    {
        $review = $reviews->findForWorkspace($request->user()->workspace, $publicId);

        if (! $review) {
            return response()->json(['message' => 'Review not found for this try token.'], 404);
        }

        return response()->json($review->toAgentPayload());
    }

    public function addScreenshot(Request $request, string $publicId, ReviewService $reviews): JsonResponse
    {
        $this->throttle($request, 'add-screenshot', 60);

        $review = $reviews->findForWorkspace($request->user()->workspace, $publicId);

        if (! $review) {
            return response()->json(['message' => 'Review not found for this try token.'], 404);
        }

        $data = $request->validate([
            'image' => ['required'],
        ]);

        try {
            $reviews->addScreenshot($review, $data['image']);
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json($review->fresh(['screenshots.annotations', 'screenshots.findings'])?->toAgentPayload());
    }

    public function addFindings(Request $request, string $publicId, ReviewService $reviews, SecondOpinionService $opinions): JsonResponse
    {
        $this->throttle($request, 'add-findings', 60);

        $review = $reviews->findForWorkspace($request->user()->workspace, $publicId);

        if (! $review) {
            return response()->json(['message' => 'Review not found for this try token.'], 404);
        }

        $data = $request->validate([
            'findings' => ['required', 'array', 'min:1', 'max:20'],
            'findings.*.body' => ['required', 'string', 'max:2000'],
            'findings.*.severity' => ['nullable', 'string', 'in:suggestion,a11y,polish'],
            'findings.*.screenshot_index' => ['nullable', 'integer', 'min:0', 'max:4'],
            'findings.*.related_pin' => ['nullable', 'integer', 'min:1'],
            'findings.*.area' => ['nullable', 'array'],
        ]);

        $opinions->addAgentFindings($review, $data['findings']);

        return response()->json($review->fresh(['screenshots.annotations', 'screenshots.findings'])?->toAgentPayload(), 201);
    }

    public function resolveMarks(Request $request, string $publicId, ReviewService $reviews, MarkLifecycleService $lifecycle): JsonResponse
    {
        $this->throttle($request, 'resolve-marks', 120);

        $workspace = $request->user()->workspace;
        $review = $reviews->findForWorkspace($workspace, $publicId);

        if (! $review) {
            return response()->json(['message' => 'Review not found for this try token.'], 404);
        }

        if ($review->effectiveStatus() !== Review::STATUS_CHANGES_REQUESTED) {
            return response()->json([
                'message' => 'You can only resolve marks after the human requests changes.',
                'status' => $review->effectiveStatus(),
            ], 422);
        }

        $data = $request->validate([
            'marks' => ['required', 'array', 'min:1', 'max:50'],
            'marks.*.id' => ['required', 'integer'],
            'marks.*.status' => ['nullable', 'string', 'in:in_progress,resolved'],
            'marks.*.note' => ['nullable', 'string', 'max:2000'],
            'marks.*.after_image' => ['nullable', 'string'],
        ]);

        $updated = $lifecycle->applyAgentUpdates($workspace, $data['marks']);

        if ($updated->isEmpty()) {
            return response()->json([
                'message' => 'None of those mark ids belong to a review on this try token. Check work_packets.pins[].id.',
            ], 422);
        }

        return response()->json([
            'updated' => $updated->count(),
            'review' => $review->fresh(['screenshots.annotations', 'screenshots.findings', 'parent.screenshots.annotations'])?->toAgentPayload(),
        ]);
    }

    public function requestSecondOpinion(Request $request, string $publicId, ReviewService $reviews, SecondOpinionService $opinions): JsonResponse
    {
        $this->throttle($request, 'second-opinion', 30);

        $review = $reviews->findForWorkspace($request->user()->workspace, $publicId);

        if (! $review) {
            return response()->json(['message' => 'Review not found for this try token.'], 404);
        }

        $data = $request->validate([
            'screenshot_index' => ['nullable', 'integer', 'min:0', 'max:4'],
        ]);

        $count = $opinions->requestForReview(
            $review,
            array_key_exists('screenshot_index', $data) ? (int) $data['screenshot_index'] : null,
        );

        return response()->json([
            'queued' => $count,
            'review' => $review->fresh(['screenshots.annotations', 'screenshots.findings'])?->toAgentPayload(),
        ]);
    }

    protected function throttle(Request $request, string $action, int $maxAttempts): void
    {
        $key = $action.':'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            abort(429, 'Slow down — too many reviews from this token right now.');
        }

        RateLimiter::hit($key, 60);
    }
}
