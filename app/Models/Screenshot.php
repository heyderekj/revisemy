<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Screenshot extends Model
{
    public const OPINION_IDLE = 'idle';

    public const OPINION_QUEUED = 'queued';

    public const OPINION_READY = 'ready';

    public const OPINION_FAILED = 'failed';

    public const KIND_SOURCE = 'source';

    public const KIND_AFTER = 'after';

    protected $fillable = [
        'review_id',
        'path',
        'thumb_path',
        'disk',
        'width',
        'height',
        'sort_order',
        'kind',
        'meta',
        'second_opinion_status',
        'second_opinion_error',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class)->orderBy('number');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('id');
    }

    /**
     * Signed app URL so the browser does not depend on a public /storage
     * symlink (missing on Laravel Cloud when using the local public disk).
     */
    public function url(): string
    {
        return URL::temporarySignedRoute(
            'screenshots.show',
            $this->signedUrlExpiry(),
            ['screenshot' => $this->id],
        );
    }

    /**
     * Small rail thumbnail; screenshots stored before thumbnails existed
     * fall back to the full image (the rail crops it client-side).
     */
    public function thumbUrl(): string
    {
        if (! $this->thumb_path) {
            return $this->url();
        }

        return URL::temporarySignedRoute(
            'screenshots.thumb',
            $this->signedUrlExpiry(),
            ['screenshot' => $this->id],
        );
    }

    protected function signedUrlExpiry(): \DateTimeInterface
    {
        $this->loadMissing('review');

        $expires = $this->review?->expires_at;

        if ($expires instanceof \DateTimeInterface && $expires > now()) {
            return $expires;
        }

        return now()->addDays(7);
    }

    /**
     * Element map recorded at capture time: {docWidth, docHeight, viewport,
     * elements: list<{selector, tag, kind, text, src, box: {x, y, w, h}}>},
     * boxes in CSS px from the top of the document. Null when not captured.
     *
     * @return array<string, mixed>|null
     */
    public function elementMap(): ?array
    {
        $path = $this->meta['elements_path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $map = json_decode((string) Storage::disk($this->disk)->get($path), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($map) ? $map : null;
    }

    /**
     * Short label for the thumbnail rail, derived from capture metadata:
     * viewport for URL/HTML captures, page number for PDFs, else the index.
     */
    public function railLabel(int $index): string
    {
        $meta = $this->meta ?? [];

        if (is_string($viewport = $meta['viewport'] ?? null) && $viewport !== '') {
            return Str::of($viewport)->before('-')->ucfirst()->toString();
        }

        if (is_numeric($page = $meta['page'] ?? null)) {
            return 'Page '.$page;
        }

        return 'Shot '.($index + 1);
    }

    public function secondOpinionIsPending(): bool
    {
        return $this->second_opinion_status === self::OPINION_QUEUED;
    }
}
