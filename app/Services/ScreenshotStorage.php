<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Screenshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreenshotStorage
{
    public function store(Review $review, string|UploadedFile $image, int $sortOrder = 0): Screenshot
    {
        $disk = (string) config('filesystems.revisemy_disk', config('filesystems.default', 'public'));
        $binary = $this->resolveBinary($image);
        $extension = $this->guessExtension($binary, $image);
        $path = 'reviews/'.$review->public_id.'/'.Str::ulid().'.'.$extension;

        Storage::disk($disk)->put($path, $binary);

        [$width, $height] = @getimagesizefromstring($binary) ?: [null, null];

        return $review->screenshots()->create([
            'path' => $path,
            'disk' => $disk,
            'width' => $width,
            'height' => $height,
            'sort_order' => $sortOrder,
        ]);
    }

    protected function resolveBinary(string|UploadedFile $image): string
    {
        if ($image instanceof UploadedFile) {
            if ($image->getSize() > 8 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'images' => 'Each screenshot needs to stay under 8MB.',
                ]);
            }

            $contents = file_get_contents($image->getRealPath());

            if ($contents === false) {
                throw ValidationException::withMessages([
                    'images' => 'Could not read that screenshot.',
                ]);
            }

            return $contents;
        }

        $image = trim($image);

        if (str_starts_with($image, 'data:image/')) {
            if (! preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $image, $matches)) {
                throw ValidationException::withMessages([
                    'images' => 'That data URL does not look like a supported image.',
                ]);
            }

            $payload = substr($image, strpos($image, ',') + 1);
            $binary = base64_decode($payload, true);

            if ($binary === false || strlen($binary) > 8 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'images' => 'Could not decode that screenshot (max 8MB).',
                ]);
            }

            return $binary;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(20)->get($image);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'images' => 'Could not download that screenshot URL.',
                ]);
            }

            $binary = $response->body();

            if (strlen($binary) > 8 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'images' => 'That screenshot URL is larger than 8MB.',
                ]);
            }

            return $binary;
        }

        $binary = base64_decode($image, true);

        if ($binary === false || strlen($binary) < 32) {
            throw ValidationException::withMessages([
                'images' => 'Pass a screenshot as a data URL, https URL, or base64 string.',
            ]);
        }

        if (strlen($binary) > 8 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'images' => 'Each screenshot needs to stay under 8MB.',
            ]);
        }

        return $binary;
    }

    protected function guessExtension(string $binary, string|UploadedFile $image): string
    {
        if ($image instanceof UploadedFile) {
            return match (strtolower((string) $image->extension())) {
                'jpg', 'jpeg' => 'jpg',
                'webp' => 'webp',
                'gif' => 'gif',
                default => 'png',
            };
        }

        $info = @getimagesizefromstring($binary);

        return match ($info['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
    }
}
