<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Screenshot;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * What a review link shows when it's pasted into Slack, iMessage or a post:
 * the review's own title and state, and the top of its first shot as the
 * card. Anyone who sees the card already holds the link, so it shows
 * nothing the link doesn't. An expired guest link shows only the site's.
 */
class ReviewPreview
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public function __construct(protected MarkCrop $crops) {}

    /**
     * Head tags for the page behind a review link, in x-layouts.app's props.
     *
     * @return array{title: string, description: string, og_image: ?string, og_image_alt: ?string, labels: array<string, string>}
     */
    public function meta(Review $review, string $token): array
    {
        $guest = ! hash_equals((string) $review->token, $token);

        if ($guest && ! $review->allowsGuestAccess()) {
            return [
                'title' => 'Review — ReviseMy',
                'description' => 'This guest link has expired. Ask whoever shared it for a new one.',
                'og_image' => null,
                'og_image_alt' => null,
                'labels' => [],
            ];
        }

        $kind = $this->kind($review);
        $pass = (int) $review->pass > 1 ? ', pass '.$review->pass : '';

        $description = $guest
            ? 'Suggest marks on this '.$kind.'. The person who shared it decides what goes to their agent.'
            : ucfirst($this->article($kind)).' '.$kind.$pass.'. '.match ($review->effectiveStatus()) {
                Review::STATUS_CHANGES_REQUESTED => 'Changes asked for — the agent is working through the marks.',
                Review::STATUS_APPROVED => 'Approved.',
                Review::STATUS_EXPIRED => 'This review has expired.',
                default => 'Mark what matters, then approve or ask for changes.',
            };

        $open = $review->outstandingMarks()->count();

        return [
            'title' => $review->title.' — ReviseMy',
            'description' => $description,
            'og_image' => $this->candidates($review) !== [] ? route('reviews.preview', $token) : null,
            'og_image_alt' => 'The top of '.$review->title,
            'labels' => [
                'Marks' => $open > 0 ? $open.' open' : 'None open',
                'Status' => $this->status($review, $guest),
            ],
        ];
    }

    /**
     * The card as a JPEG, made once per first shot and kept beside the
     * review's files, so pruning the review takes it too.
     */
    public function image(Review $review): ?string
    {
        $shot = $this->firstShot($review);

        if (! $shot) {
            return null;
        }

        $disk = Storage::disk($shot->disk ?: ScreenshotStorage::diskName());
        $path = 'reviews/'.$review->public_id.'/preview-'.$shot->id.'.jpg';

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $source = $this->source($review);

        if (! $source) {
            return null;
        }

        $jpeg = $this->card($source);
        $disk->put($path, $jpeg);

        return $jpeg;
    }

    /**
     * The first shot decoded: the shot itself when GD can hold it, else the
     * top slice of a long capture, else the vision copy.
     */
    protected function source(Review $review): ?GdImage
    {
        $shot = $this->firstShot($review);

        if (! $shot) {
            return null;
        }

        $disk = Storage::disk($shot->disk ?: ScreenshotStorage::diskName());

        foreach ($this->candidates($review) as $path) {
            if ($image = $this->crops->decode($disk, $path)) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Stored images the card could come from, best first, without reading
     * any of them: the page meta asks this on every view.
     *
     * @return list<string>
     */
    protected function candidates(Review $review): array
    {
        $shot = $this->firstShot($review);

        if (! $shot) {
            return [];
        }

        $pixels = (int) $shot->width * (int) $shot->height;

        return array_values(array_filter([
            $pixels > 0 && $pixels <= MarkCrop::MAX_DECODE_PIXELS ? $shot->path : null,
            $shot->meta['slices'][0]['path'] ?? null,
            $shot->meta['vision_path'] ?? null,
        ], 'is_string'));
    }

    protected function firstShot(Review $review): ?Screenshot
    {
        $shot = $review->screenshots()->first();

        // A capture that failed leaves a 1×1 placeholder: nothing to show.
        if (! $shot || ($shot->meta['origin'] ?? null) === 'capture_failed') {
            return null;
        }

        return $shot;
    }

    /**
     * Cover-crop from the top to the card's shape, on white for any
     * transparency, scaled up or down to fill it.
     */
    protected function card(GdImage $source): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = self::WIDTH / self::HEIGHT;

        $cropWidth = $width;
        $cropHeight = (int) min($height, round($width / $ratio));

        if ($cropHeight < round($width / $ratio)) {
            $cropWidth = (int) min($width, round($height * $ratio));
        }

        $card = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($card, 0, 0, imagecolorallocate($card, 255, 255, 255));
        imagecopyresampled($card, $source, 0, 0, (int) (($width - $cropWidth) / 2), 0, self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);

        ob_start();
        imagejpeg($card, null, 82);

        return (string) ob_get_clean();
    }

    protected function kind(Review $review): string
    {
        return match ($review->type) {
            Review::TYPE_WEBSITE => 'website review',
            Review::TYPE_PRESENTATION => 'slide review',
            Review::TYPE_EMAIL => 'email review',
            default => 'UI review',
        };
    }

    protected function article(string $kind): string
    {
        return str_starts_with($kind, 'email') ? 'an' : 'a';
    }

    protected function status(Review $review, bool $guest): string
    {
        return match ($review->effectiveStatus()) {
            Review::STATUS_CHANGES_REQUESTED => 'Changes asked for',
            Review::STATUS_APPROVED => 'Approved',
            Review::STATUS_EXPIRED => 'Expired',
            default => $guest ? 'Open for suggestions' : 'Waiting on you',
        };
    }
}
