<?php

namespace App\Models;

use App\Support\ElementMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
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

    /**
     * Meta keys that point at the app's own storage. The agent can't fetch
     * them, so its payload leaves them out.
     */
    public const INTERNAL_META = ['elements_path', 'elements_pick_path', 'vision_path', 'slices'];

    /** @var array{path: string, map: array<string, mixed>|null}|null */
    protected ?array $elementMapCache = null;

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
     * App URL so the browser does not depend on a public /storage symlink
     * (missing on Laravel Cloud when using the local public disk).
     *
     * Gated by the review's guest token, never the owner one. Guests see
     * these URLs in an <img src>, and the owner token there would let anyone
     * holding the guest link approve the review.
     */
    public function url(): string
    {
        $this->loadMissing('review');

        if ($this->review?->share_token) {
            return url('/r/'.$this->review->share_token.'/shots/'.$this->id);
        }

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

        $this->loadMissing('review');

        if ($this->review?->share_token) {
            return url('/r/'.$this->review->share_token.'/shots/'.$this->id.'/thumb');
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
     * Element map recorded with the shot, cleaned by ElementMap:
     * {version, docWidth, docHeight, viewport, elements: list<{selector, tag,
     * kind, text, label?, role?, src?, box: {x, y, w, h}, parent, fixed?,
     * styles?}>}, boxes in CSS px from the top of the document. Null when
     * there is none.
     *
     * @return array<string, mixed>|null
     */
    public function elementMap(): ?array
    {
        $path = $this->meta['elements_path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        // Read once per request: resolving a mark and its picker both ask.
        if (($this->elementMapCache['path'] ?? null) === $path) {
            return $this->elementMapCache['map'];
        }

        try {
            $map = json_decode((string) Storage::disk($this->disk)->get($path), true);
        } catch (\Throwable) {
            $map = null;
        }

        $this->elementMapCache = ['path' => $path, 'map' => is_array($map) ? $map : null];

        return $this->elementMapCache['map'];
    }

    /**
     * Where the review canvas fetches the shot's element list, gated like
     * url(). Null when the shot has no element map.
     */
    public function elementsUrl(): ?string
    {
        if (! is_string($this->meta['elements_path'] ?? null)) {
            return null;
        }

        $this->loadMissing('review');

        if ($this->review?->share_token) {
            return url('/r/'.$this->review->share_token.'/shots/'.$this->id.'/elements');
        }

        return URL::temporarySignedRoute('screenshots.elements', $this->signedUrlExpiry(), ['screenshot' => $this->id]);
    }

    /**
     * The compact element list the canvas hit-tests (ElementMap::pickList):
     * the one stored with the map, or made now for a shot from before there
     * was one.
     *
     * @return array<string, mixed>|null
     */
    public function pickList(): ?array
    {
        $path = $this->meta['elements_pick_path'] ?? null;

        if (is_string($path)) {
            $list = rescue(fn () => json_decode((string) Storage::disk($this->disk)->get($path), true), report: false);

            if (is_array($list)) {
                return $list;
            }
        }

        $map = $this->elementMap();

        if ($map === null) {
            return null;
        }

        $css = $this->cssSize();
        $clean = ElementMap::sanitize($map, $css)['map'];

        return $clean === null ? null : ElementMap::pickList($clean, $css);
    }

    /**
     * Meta as the agent sees it: everything but INTERNAL_META.
     *
     * @return array<string, mixed>|null
     */
    public function agentMeta(): ?array
    {
        return is_array($this->meta) ? Arr::except($this->meta, self::INTERNAL_META) : null;
    }

    /**
     * The CSS size of the page the image shows: what /function measured, or
     * the image's own size over its pixel ratio.
     *
     * @return array{w: float, h: float}
     */
    public function cssSize(): array
    {
        $width = $this->meta['doc_width'] ?? null;
        $height = $this->meta['doc_height'] ?? null;

        if (is_numeric($width) && is_numeric($height) && $width > 0 && $height > 0) {
            return ['w' => (float) $width, 'h' => (float) $height];
        }

        $ratio = $this->pixelRatio();

        return ['w' => (int) $this->width / $ratio, 'h' => (int) $this->height / $ratio];
    }

    /**
     * Image pixels per CSS px: 2 for a 2× capture, 1 for an upload we know
     * nothing about. The board and the inline review show a shot at
     * width / pixelRatio(), the size the page was drawn at, so 2× stays sharp
     * and a phone shot isn't blown up.
     */
    public function pixelRatio(): float
    {
        $dpr = $this->meta['dpr'] ?? null;

        return is_numeric($dpr) && $dpr > 0 ? (float) $dpr : 1.0;
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
