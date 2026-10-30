<?php

namespace App\Services\Capture;

use App\Models\Review;
use App\Models\Screenshot;
use App\Support\LiveSnapshot;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Throwable;

/**
 * How closely the Live view matches the screenshot, per viewport. The
 * archived page is rendered in the capture browser at the shot's width,
 * and the top of both is compared, small and softened so anti-aliasing and
 * a stray pixel don't count. The score (0–1) is kept as meta.live_match; a
 * review opens on Live where it's at least revisemy.live.match_threshold,
 * and says nothing about differences, since there aren't any worth saying.
 */
class LiveFidelity
{
    /** How much of the top of the page is compared, in CSS px. */
    public const REGION_HEIGHT = 1400;

    public const COMPARE_WIDTH = 320;

    public function __construct(protected PageCaptureService $capture) {}

    public function check(Review $review): void
    {
        $html = $review->liveHtml();

        if ($html === null || trim($html) === '') {
            return;
        }

        $base = $review->sourceKind() === Review::SOURCE_URL ? $review->page_url : null;

        foreach ($review->screenshots as $shot) {
            try {
                $score = $this->score($shot, $html, $base);
            } catch (Throwable $e) {
                Log::info('Live fidelity check skipped', ['review' => $review->public_id, 'shot' => $shot->id, 'error' => $e->getMessage()]);
                $score = null;
            }

            if ($score !== null) {
                $shot->update(['meta' => array_merge($shot->meta ?? [], ['live_match' => round($score, 3)])]);
            }
        }
    }

    protected function score(Screenshot $shot, string $html, ?string $base): ?float
    {
        $css = $shot->cssSize();
        $width = (int) round($css['w']);

        if ($width < 200) {
            return null;
        }

        // The shot's own top, from its small vision copy when there is one.
        $source = new Imagick;
        $visionPath = $shot->meta['vision_path'] ?? null;
        $disk = Storage::disk($shot->disk);
        $source->readImageBlob((string) $disk->get(is_string($visionPath) && $disk->exists($visionPath) ? $visionPath : $shot->path));
        $scale = $source->getImageWidth() / $width;
        $region = (int) min(self::REGION_HEIGHT, floor($source->getImageHeight() / $scale), (int) round($css['h']));

        if ($region < 200) {
            return null;
        }

        $rendered = $this->capture->renderHtml(LiveSnapshot::prepare($html, $base, Str::random(16), ''), $width, $region);

        if ($rendered === null) {
            return null;
        }

        $live = new Imagick;
        $live->readImageBlob($rendered);

        $a = $this->normalise($source, $scale, $region, $width);
        $b = $this->normalise($live, $live->getImageWidth() / $width, $region, $width);

        [, $distortion] = $a->compareImages($b, Imagick::METRIC_ROOTMEANSQUAREDERROR);

        return max(0.0, min(1.0, 1 - (float) $distortion));
    }

    /** The top $region CSS px of a $width-wide page, small, grey and softened. */
    protected function normalise(Imagick $image, float $scale, int $region, int $width): Imagick
    {
        $image->setImageBackgroundColor('white');
        $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $image->cropImage($image->getImageWidth(), (int) min($image->getImageHeight(), round($region * $scale)), 0, 0);
        $image->setImagePage(0, 0, 0, 0);
        $image->resizeImage(self::COMPARE_WIDTH, (int) max(1, round(self::COMPARE_WIDTH * $region / $width)), Imagick::FILTER_TRIANGLE, 1);
        $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $image->blurImage(0, 1.2);

        return $image;
    }
}
