<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Screenshot;
use App\Services\ReviewPreview;
use App\Support\LiveSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /**
     * The link-preview card for a review (ReviewPreview). Public caching:
     * the apps that fetch it send no cookies, and the token is in the URL.
     */
    public function previewForReview(string $token, ReviewPreview $preview): Response
    {
        $review = Review::forLink($token);

        if (! $review || (! hash_equals((string) $review->token, $token) && ! $review->allowsGuestAccess())) {
            abort(404);
        }

        $jpeg = $preview->image($review);

        abort_if($jpeg === null, 404);

        return response($jpeg, headers: [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * The shot's element list for the review canvas to hit-test (see
     * ElementMap::pickList). It holds only what the shot already shows, so
     * it is gated the same way as the image.
     */
    public function elements(Request $request, Screenshot $screenshot): JsonResponse
    {
        return $this->pickList($screenshot);
    }

    public function elementsForReview(string $token, Screenshot $screenshot): JsonResponse
    {
        $this->assertReviewToken($token, $screenshot);

        return $this->pickList($screenshot);
    }

    protected function pickList(Screenshot $screenshot): JsonResponse
    {
        $list = $screenshot->pickList();

        abort_if($list === null, 404);

        return response()->json($list, headers: ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * The page's code as captured, made safe to frame (LiveSnapshot), for the
     * review's Live view. Only reviews with a stored DOM (a captured page or
     * an email) have one. The CSP sandboxes it into an opaque origin, lets
     * only our picker run, and stops it fetching, posting or framing.
     */
    public function liveForReview(string $token, Screenshot $screenshot): Response
    {
        $this->assertReviewToken($token, $screenshot);
        $review = $screenshot->review;

        if (! hash_equals((string) $review->token, $token) && ! $review->allowsGuestAccess()) {
            abort(404);
        }

        $html = $review->domHtml();
        abort_if($html === null || trim($html) === '', 404);

        $nonce = Str::random(24);
        $script = (string) file_get_contents(resource_path('js/live-pick.js'));
        $base = $review->sourceKind() === Review::SOURCE_URL ? $review->page_url : null;

        return response(LiveSnapshot::prepare($html, $base, $nonce, $script), headers: [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => implode('; ', [
                'sandbox allow-scripts',
                "default-src 'none'",
                "script-src 'nonce-{$nonce}'",
                "style-src * data: 'unsafe-inline'",
                'img-src * data: blob:',
                'font-src * data:',
                'media-src * data: blob:',
                "connect-src 'none'",
                "form-action 'none'",
                "frame-src 'none'",
                "frame-ancestors 'self'",
            ]),
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, no-store',
        ]);
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
