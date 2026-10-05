<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Screenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenshotController extends Controller
{
    /**
     * Stream a review screenshot through the app so display does not depend
     * on a public /storage symlink (broken on Laravel Cloud with local disks).
     */
    public function show(Request $request, Screenshot $screenshot): StreamedResponse
    {
        return $this->stream($screenshot, $screenshot->path);
    }

    public function thumb(Request $request, Screenshot $screenshot): StreamedResponse
    {
        $path = $screenshot->thumb_path ?: $screenshot->path;

        return $this->stream($screenshot, $path);
    }

    /**
     * Review-token gate. The board uses these so an image does not 403 when a
     * signed URL is truncated, expired, or signed for a different host.
     */
    public function showForReview(string $token, Screenshot $screenshot): StreamedResponse
    {
        $this->assertReviewToken($token, $screenshot);

        return $this->stream($screenshot, $screenshot->path);
    }

    public function thumbForReview(string $token, Screenshot $screenshot): StreamedResponse
    {
        $this->assertReviewToken($token, $screenshot);

        return $this->stream($screenshot, $screenshot->thumb_path ?: $screenshot->path);
    }

    protected function assertReviewToken(string $token, Screenshot $screenshot): void
    {
        $screenshot->loadMissing('review');
        $review = $screenshot->review;

        if (! $review instanceof Review) {
            abort(404);
        }

        $matches = hash_equals((string) $review->token, $token)
            || hash_equals((string) $review->share_token, $token);

        if (! $matches) {
            abort(404);
        }
    }

    protected function stream(Screenshot $screenshot, string $path): StreamedResponse
    {
        $disk = Storage::disk($screenshot->disk);

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        return $disk->response($path, headers: [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
