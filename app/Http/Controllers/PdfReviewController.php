<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\PdfReviewService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfReviewController extends Controller
{
    public function asset(string $group, string $name): BinaryFileResponse
    {
        $path = base_path("node_modules/pdfjs-dist/{$group}/{$name}");
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => match (pathinfo($name, PATHINFO_EXTENSION)) {
                'wasm' => 'application/wasm',
                'js' => 'text/javascript; charset=utf-8',
                default => 'application/octet-stream',
            },
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function original(string $token, PdfReviewService $pdf): Response
    {
        $review = $this->review($token);

        return $this->download($review, $pdf->original($review));
    }

    public function annotated(string $token, PdfReviewService $pdf): Response
    {
        $review = $this->review($token);
        abort_unless(hash_equals((string) $review->token, $token), 404);

        return $this->download($review, $pdf->export($review), '-annotated');
    }

    protected function review(string $token): Review
    {
        $review = Review::forLink($token);
        abort_unless($review?->pdf_path && ! $review->isExpired(), 404);
        abort_unless(hash_equals((string) $review->token, $token) || $review->allowsGuestAccess(), 404);

        return $review;
    }

    protected function download(Review $review, string $bytes, string $suffix = ''): Response
    {
        $name = Str::limit(Str::slug($review->title) ?: 'review', 80, '').$suffix.'.pdf';

        return response($bytes, headers: [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
