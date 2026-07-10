<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
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
            ->with(['screenshots.annotations'])
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
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required'],
        ]);

        $review = $reviews->create(
            $request->user()->workspace,
            $data['title'],
            $data['context'] ?? null,
            $data['images'],
        );

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

        return response()->json($review->fresh(['screenshots.annotations'])?->toAgentPayload());
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
