<?php

namespace App\Http\Controllers;

use App\Models\Screenshot;
use App\Support\ElementAnchor;
use Illuminate\Http\JsonResponse;
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
     * The capture's element map, normalized for snapping marks to elements.
     */
    public function elements(Request $request, Screenshot $screenshot): JsonResponse
    {
        return response()->json(['elements' => ElementAnchor::forCanvas($screenshot)])
            ->header('Cache-Control', 'private, max-age=86400');
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
