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
    /**
     * The disk every review artifact (screenshots, thumbs, DOM snapshots)
     * lives on.
     */
    public static function diskName(): string
    {
        return (string) config('filesystems.revisemy_disk', config('filesystems.default', 'public'));
    }

    public function store(Review $review, string|UploadedFile $image, int $sortOrder = 0, string $kind = Screenshot::KIND_SOURCE): Screenshot
    {
        $binary = $this->resolveBinary($image);

        return $this->persist($review, $binary, $this->guessExtension($binary, $image), $sortOrder, $kind);
    }

    /**
     * Validate an image source before persisting a review row.
     */
    public function validateSource(string|UploadedFile $image): void
    {
        $this->resolveBinary($image);
    }

    /**
     * Store server-originated image bytes (captures, rendered PDF pages).
     * Unlike user uploads these are never rejected for size — anything over
     * the 8MB cap is downscaled/re-encoded instead.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function storeRaw(Review $review, string $binary, int $sortOrder = 0, ?array $meta = null): Screenshot
    {
        $binary = $this->normalizeSize($binary);

        $extension = match (@getimagesizefromstring($binary)['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };

        return $this->persist($review, $binary, $extension, $sortOrder, Screenshot::KIND_SOURCE, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function persist(Review $review, string $binary, string $extension, int $sortOrder, string $kind, ?array $meta = null): Screenshot
    {
        $disk = self::diskName();
        $path = 'reviews/'.$review->public_id.'/'.Str::ulid().'.'.$extension;

        Storage::disk($disk)->put($path, $binary);

        $thumbPath = null;

        if ($thumb = $this->makeThumbnail($binary)) {
            $thumbPath = preg_replace('/\.[a-z]+$/', '', $path).'.thumb.jpg';
            Storage::disk($disk)->put($thumbPath, $thumb);
        }

        [$width, $height] = @getimagesizefromstring($binary) ?: [null, null];

        return $review->screenshots()->create([
            'path' => $path,
            'thumb_path' => $thumbPath,
            'disk' => $disk,
            'width' => $width,
            'height' => $height,
            'sort_order' => $sortOrder,
            'kind' => $kind,
            'meta' => $meta,
        ]);
    }

    /**
     * Small rail thumbnail: top-cropped so full-page captures don't encode
     * into unreadable strips, flattened on white so transparent PNGs survive
     * JPEG. Best-effort — returns null instead of throwing so a bad image
     * never blocks the screenshot itself.
     */
    public function makeThumbnail(string $binary): ?string
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            return null;
        }

        $cropHeight = min($height, (int) ceil($width * 1.25));
        $flat = imagecreatetruecolor($width, $cropHeight);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $source, 0, 0, 0, 0, $width, $cropHeight);

        $thumb = imagescale($flat, min(320, $width));

        if ($thumb === false) {
            return null;
        }

        ob_start();
        imagejpeg($thumb, null, 72);
        $encoded = (string) ob_get_clean();

        return $encoded !== '' ? $encoded : null;
    }

    /**
     * GD downscale for oversized server-side captures: shrink until the
     * encoded image fits the cap, preferring PNG before JPEG re-encode.
     */
    protected function normalizeSize(string $binary): string
    {
        $cap = 16 * 1024 * 1024;

        if (strlen($binary) <= $cap) {
            return $binary;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            throw ValidationException::withMessages([
                'capture' => 'The captured image was too large and could not be re-encoded.',
            ]);
        }

        $scale = 1.0;
        $best = $binary;

        do {
            $working = $scale < 1.0
                ? imagescale($image, (int) (imagesx($image) * $scale))
                : $image;

            if ($working === false) {
                break;
            }

            ob_start();
            imagepng($working, null, 6);
            $png = (string) ob_get_clean();

            if ($png !== '' && strlen($png) <= $cap) {
                if ($working !== $image) {
                    imagedestroy($working);
                }
                imagedestroy($image);

                return $png;
            }

            if ($png !== '') {
                $best = $png;
            }

            ob_start();
            imagejpeg($working, null, 88);
            $jpeg = (string) ob_get_clean();

            if ($jpeg !== '' && strlen($jpeg) <= $cap) {
                if ($working !== $image) {
                    imagedestroy($working);
                }
                imagedestroy($image);

                return $jpeg;
            }

            if ($jpeg !== '') {
                $best = $jpeg;
            }

            if ($working !== $image) {
                imagedestroy($working);
            }

            $scale *= 0.85;
        } while ($scale > 0.1);

        imagedestroy($image);

        return $best;
    }

    protected function resolveBinary(string|UploadedFile $image): string
    {
        $sourceHint = null;

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

            $this->assertIsImage($contents);

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

            $this->assertIsImage($binary);

            return $binary;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $sourceHint = $image;
            $response = Http::timeout(20)->get($image);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'images' => 'Could not download that screenshot URL.',
                ]);
            }

            $contentType = strtolower((string) $response->header('Content-Type'));

            if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
                throw ValidationException::withMessages([
                    'images' => 'That URL is not an image — use capture_url + page_url to screenshot a page.',
                ]);
            }

            $binary = $response->body();

            if (strlen($binary) > 8 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'images' => 'That screenshot URL is larger than 8MB.',
                ]);
            }

            $this->assertIsImage($binary, $sourceHint);

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

        $this->assertIsImage($binary);

        return $binary;
    }

    protected function assertIsImage(string $binary, ?string $sourceUrl = null): void
    {
        if (@getimagesizefromstring($binary) !== false) {
            return;
        }

        if ($sourceUrl !== null && filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'images' => 'That URL is not an image — use capture_url + page_url to screenshot a page.',
            ]);
        }

        throw ValidationException::withMessages([
            'images' => 'That payload is not a valid image.',
        ]);
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
