<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Screenshot;
use App\Support\ElementMap;
use App\Support\OutboundUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreenshotStorage
{
    /** Ceiling for a caller-supplied screenshot, however it arrives. */
    public const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    /** Ceiling for a server-side capture before it is downscaled to fit. */
    public const MAX_CAPTURE_BYTES = 16 * 1024 * 1024;

    /** Width of a rail thumbnail. */
    public const THUMB_WIDTH = 320;

    /**
     * Longest edge of a vision copy. Anthropic shrinks anything past 1568px
     * or about 1.15MP before the model sees it, and turns down 8000px.
     */
    public const VISION_EDGE = 1568;

    /** Pixel budget for a vision copy. */
    public const VISION_PIXELS = 1_150_000;

    /**
     * Largest image GD may decode to shrink an oversized capture. A 40MP
     * truecolor image is about 160MB in GD, as much as Cloud's 256MB PHP can
     * spare; past it the capture is kept as it came.
     */
    public const MAX_SHRINK_PIXELS = 40_000_000;

    /**
     * The disk every review artifact (screenshots, thumbs, DOM snapshots)
     * lives on.
     */
    public static function diskName(): string
    {
        return (string) config('filesystems.revisemy_disk', config('filesystems.default', 'public'));
    }

    /**
     * Pass $binary when the caller already resolved this source (see
     * validateSource) — an https source is otherwise downloaded a second time.
     */
    public function store(Review $review, string|UploadedFile $image, int $sortOrder = 0, string $kind = Screenshot::KIND_SOURCE, ?string $binary = null): Screenshot
    {
        $binary ??= $this->resolveBinary($image);

        return $this->persist($review, $binary, $this->guessExtension($binary, $image), $sortOrder, $kind);
    }

    /**
     * Validate an image source before persisting a review row, returning the
     * resolved bytes so the caller can hand them back to store() instead of
     * paying for a second download/decode.
     */
    public function validateSource(string|UploadedFile $image): string
    {
        return $this->resolveBinary($image);
    }

    /**
     * Store server-originated image bytes (captures, rendered PDF pages).
     * Unlike user uploads these are never rejected for size — anything over
     * the 16MB cap is downscaled/re-encoded instead.
     *
     * $thumb is a rail thumbnail the capture browser already made, so a tall
     * 2× capture never has to be decoded here.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function storeRaw(Review $review, string $binary, int $sortOrder = 0, ?array $meta = null, ?string $thumb = null): Screenshot
    {
        [$binary, $scale] = $this->normalizeSize($binary);

        $extension = match (@getimagesizefromstring($binary)['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };

        if ($meta !== null) {
            $meta['format'] = $extension === 'jpg' ? 'jpeg' : $extension;

            // Shrunk here to fit the cap: the pixels now hold less per CSS px.
            if ($scale < 1.0 && is_numeric($meta['dpr'] ?? null)) {
                $meta['dpr'] = round((float) $meta['dpr'] * $scale, 2);
            }
        }

        return $this->persist($review, $binary, $extension, $sortOrder, Screenshot::KIND_SOURCE, $meta, $thumb);
    }

    /**
     * Whether this PHP's GD can decode an image of this MIME type. WebP
     * support depends on how GD was built.
     */
    public static function gdReads(?string $mime): bool
    {
        $type = match ($mime) {
            'image/png' => IMG_PNG,
            'image/jpeg' => IMG_JPG,
            'image/webp' => IMG_WEBP,
            'image/gif' => IMG_GIF,
            default => 0,
        };

        return $type !== 0 && (imagetypes() & $type) !== 0;
    }

    /**
     * Keep a shot's element map (see CaptureSettleScript::COLLECTOR) next to
     * its image, cleaned by ElementMap, with the compact pick list the review
     * canvas hit-tests against, and point the screenshot's meta at both.
     * Returns why the map was left out, or null when it was kept. Best-effort:
     * a failed write never blocks the review.
     *
     * @param  array<string, mixed>  $elements
     */
    public function storeElements(Screenshot $shot, array $elements, string $source = 'capture'): ?string
    {
        $css = $shot->cssSize();
        ['map' => $map, 'note' => $note] = ElementMap::sanitize($elements, $css);

        if ($map === null) {
            $shot->update(['meta' => [...($shot->meta ?? []), 'elements_skipped' => $note]]);

            return $note;
        }

        $base = preg_replace('/\.[a-z]+$/', '', $shot->path);
        $path = $base.'.elements.json';
        $pickPath = $base.'.elements.pick.json';
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        try {
            Storage::disk($shot->disk)->put($path, json_encode($map, $flags));
            Storage::disk($shot->disk)->put($pickPath, json_encode(ElementMap::pickList($map, $css), $flags));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $shot->update(['meta' => [
            ...($shot->meta ?? []),
            'elements_path' => $path,
            'elements_pick_path' => $pickPath,
            'element_count' => count($map['elements']),
            'elements_version' => $map['version'],
        ] + ($source === 'upload' ? ['elements_source' => 'upload'] : [])]);

        return null;
    }

    /**
     * Keep the vision-sized copy the capture browser made (the top of the
     * page, small enough for a vision model to read as sent) next to the
     * shot. $crop is the share of the shot's height it covers, so regions
     * found on it map back to the full shot. Best-effort: a failed write
     * leaves the vision pass on the full image.
     */
    public function storeVision(Screenshot $shot, string $binary, float $crop): void
    {
        $path = preg_replace('/\.[a-z]+$/', '', $shot->path).'.vision.jpg';

        try {
            Storage::disk($shot->disk)->put($path, $binary);
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        $shot->update(['meta' => [
            ...($shot->meta ?? []),
            'vision_path' => $path,
            'vision_crop' => round(max(0.0, min(1.0, $crop)), 4),
        ]]);
    }

    /**
     * Keep the crop slices the capture browser made (the whole page at no
     * more than 1x, in pieces GD can decode) next to the shot, so a mark
     * anywhere on a long page can be cropped for the agent. Each slice
     * carries the share of the shot's height it starts at and covers.
     * Best-effort: a slice that fails to write is left out.
     *
     * @param  list<array{binary: string, top: float, height: float}>  $slices
     */
    public function storeSlices(Screenshot $shot, array $slices): void
    {
        $base = preg_replace('/\.[a-z]+$/', '', $shot->path);
        $kept = [];

        foreach (array_values($slices) as $index => $slice) {
            $path = $base.'.slice-'.$index.'.jpg';

            try {
                Storage::disk($shot->disk)->put($path, $slice['binary']);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            $kept[] = ['path' => $path, 'top' => $slice['top'], 'height' => $slice['height']];
        }

        if ($kept === []) {
            return;
        }

        $shot->update(['meta' => [...($shot->meta ?? []), 'slices' => $kept]]);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function persist(Review $review, string $binary, string $extension, int $sortOrder, string $kind, ?array $meta = null, ?string $thumb = null): Screenshot
    {
        $disk = self::diskName();
        $path = 'reviews/'.$review->public_id.'/'.Str::ulid().'.'.$extension;

        Storage::disk($disk)->put($path, $binary);

        $thumbPath = null;

        $thumb = $thumb !== null && $thumb !== '' ? $thumb : $this->makeThumbnail($binary);

        if ($thumb !== null) {
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
        // Skip GD decode for huge captures — a 2× full-page marketing PNG can
        // exceed Cloud's 256MB limit inside imagecreatefromstring.
        $info = @getimagesizefromstring($binary);
        if (is_array($info) && (($info[0] ?? 0) * ($info[1] ?? 0)) > 4_000_000) {
            return null;
        }
        if (is_array($info) && ! self::gdReads($info['mime'] ?? null)) {
            return null;
        }

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

        $thumb = imagescale($flat, min(self::THUMB_WIDTH, $width));

        if ($thumb === false) {
            return null;
        }

        ob_start();
        imagejpeg($thumb, null, 72);
        $encoded = (string) ob_get_clean();

        return $encoded !== '' ? $encoded : null;
    }

    /** Bytes a server-side capture may keep before it is shrunk. */
    protected function captureCap(): int
    {
        return self::MAX_CAPTURE_BYTES;
    }

    /**
     * GD downscale for oversized server-side captures: shrink until the
     * encoded image fits the cap, trying PNG, then WebP, then JPEG at each
     * size. Returns the bytes and the scale they were shrunk by, so the
     * shot's DPR can say what its pixels now hold.
     *
     * @return array{0: string, 1: float}
     */
    protected function normalizeSize(string $binary): array
    {
        $cap = $this->captureCap();

        if (strlen($binary) <= $cap) {
            return [$binary, 1.0];
        }

        $info = @getimagesizefromstring($binary);

        if (is_array($info) && (($info[0] ?? 0) * ($info[1] ?? 0)) > self::MAX_SHRINK_PIXELS) {
            Log::warning('Capture over the byte cap is too big to shrink in GD; kept as it came', [
                'width' => $info[0] ?? null,
                'height' => $info[1] ?? null,
                'bytes' => strlen($binary),
            ]);

            return [$binary, 1.0];
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            throw ValidationException::withMessages([
                'capture' => 'The captured image was too large and could not be re-encoded.',
            ]);
        }

        $encoders = [
            fn (\GdImage $working) => imagepng($working, null, 6),
            ...((imagetypes() & IMG_WEBP) !== 0 ? [fn (\GdImage $working) => imagewebp($working, null, 90)] : []),
            fn (\GdImage $working) => imagejpeg($working, null, 88),
        ];

        $scale = 1.0;
        $best = [$binary, 1.0];

        do {
            $working = $scale < 1.0
                ? imagescale($image, (int) (imagesx($image) * $scale))
                : $image;

            if ($working === false) {
                break;
            }

            foreach ($encoders as $encode) {
                ob_start();
                $encode($working);
                $encoded = (string) ob_get_clean();

                if ($encoded === '') {
                    continue;
                }

                $best = [$encoded, $scale];

                if (strlen($encoded) <= $cap) {
                    if ($working !== $image) {
                        imagedestroy($working);
                    }
                    imagedestroy($image);

                    return $best;
                }
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
            if ($image->getSize() > self::MAX_IMAGE_BYTES) {
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

            if ($binary === false || strlen($binary) > self::MAX_IMAGE_BYTES) {
                throw ValidationException::withMessages([
                    'images' => 'Could not decode that screenshot (max 8MB).',
                ]);
            }

            $this->assertIsImage($binary);

            return $binary;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $sourceHint = $image;
            $binary = $this->downloadImage($image);

            $this->assertIsImage($binary, $sourceHint);

            return $binary;
        }

        $binary = base64_decode($image, true);

        if ($binary === false || strlen($binary) < 32) {
            throw ValidationException::withMessages([
                'images' => 'Pass a screenshot as a data URL, https URL, or base64 string.',
            ]);
        }

        if (strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'images' => 'Each screenshot needs to stay under 8MB.',
            ]);
        }

        $this->assertIsImage($binary);

        return $binary;
    }

    /**
     * Fetch a screenshot URL the caller supplied.
     *
     * The guard, redirect re-checking, and streamed size cap all live in
     * OutboundUrl::fetch() — one implementation, shared with PDF ingestion, so
     * a hardening fix cannot land on one source type and miss the other. Only
     * the image-specific parts stay here: the up-front reject reason and the
     * content-type check, both raised as agent-facing messages.
     */
    protected function downloadImage(string $url): string
    {
        if ($reason = OutboundUrl::reasonToReject($url)) {
            throw ValidationException::withMessages([
                'images' => 'Could not fetch that screenshot URL — '.$reason.'.',
            ]);
        }

        $binary = OutboundUrl::fetch(
            $url,
            self::MAX_IMAGE_BYTES,
            // Thrown, not returned false: a wrong content type deserves its own
            // message rather than a generic download failure.
            function ($response): bool {
                $contentType = strtolower((string) $response->header('Content-Type'));

                if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
                    throw ValidationException::withMessages([
                        'images' => 'That URL is not an image — use capture_url + page_url to screenshot a page.',
                    ]);
                }

                return true;
            },
        );

        if ($binary === null) {
            throw ValidationException::withMessages([
                'images' => 'Could not download that screenshot URL — it must be a reachable public https image under 8MB.',
            ]);
        }

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
