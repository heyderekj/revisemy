<?php

namespace App\Services\Capture;

use App\Models\Review;
use App\Services\ScreenshotStorage;
use App\Support\OutboundUrl;
use Dom\Element;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes the Live view stand on its own instead of on someone else's
 * server. A stylesheet is fetched and written into the page, with its
 *
 * @imports and fonts, so a later deploy can't change or break it, and fonts
 * come from us with CORS open. Images and the rest are pointed at
 * /live-assets, which fetches each one the first time it's asked for and
 * keeps it: only the sizes a viewer actually needs are stored, and once
 * stored they never change.
 *
 * Writes reviews/{id}/live.html (what LiveSnapshot frames) and
 * live-assets.json (key => source URL, the only URLs the proxy will fetch).
 */
class LiveArchive
{
    public const MAX_CSS_BYTES = 3 * 1024 * 1024;

    public const MAX_ASSET_BYTES = 8 * 1024 * 1024;

    /** Stylesheets and fonts fetched up front, at most. */
    public const MAX_EAGER = 80;

    public const MAX_EAGER_BYTES = 24 * 1024 * 1024;

    protected const FONT_EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf', 'eot'];

    /** @var array<string, string> key => source URL */
    protected array $manifest = [];

    protected int $eagerCount = 0;

    protected int $eagerBytes = 0;

    public static function livePath(Review $review): string
    {
        return 'reviews/'.$review->public_id.'/live.html';
    }

    public static function manifestPath(Review $review): string
    {
        return 'reviews/'.$review->public_id.'/live-assets.json';
    }

    public static function key(Review $review, string $url): string
    {
        return substr(hash_hmac('sha256', $review->public_id.'|'.$url, (string) config('app.key')), 0, 40);
    }

    /**
     * Archive the review's captured page. False when there's nothing to
     * archive or it couldn't be read; the Live view then frames dom.html.
     */
    public function prepare(Review $review): bool
    {
        $html = $review->domHtml();

        if ($html === null || trim($html) === '') {
            return false;
        }

        $this->manifest = [];
        $this->eagerCount = 0;
        $this->eagerBytes = 0;

        $base = $review->sourceKind() === Review::SOURCE_URL && is_string($review->page_url) ? $review->page_url : null;
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        foreach (iterator_to_array($document->querySelectorAll('link[rel~="stylesheet"][href]')) as $link) {
            $this->inlineStylesheet($review, $document, $link, $base);
        }

        foreach (iterator_to_array($document->querySelectorAll('style:not([data-rm-from])')) as $style) {
            $style->textContent = $this->rewriteCss($review, (string) $style->textContent, $base, 0);
        }

        foreach (iterator_to_array($document->querySelectorAll('[style*="url("]')) as $element) {
            $element->setAttribute('style', $this->rewriteCss($review, (string) $element->getAttribute('style'), $base, 0));
        }

        foreach (iterator_to_array($document->querySelectorAll('img, source, video, input[type="image"]')) as $element) {
            $this->rewriteMedia($review, $element, $base);
        }

        $disk = Storage::disk(ScreenshotStorage::diskName());
        $disk->put(self::livePath($review), $document->saveHtml());
        $disk->put(self::manifestPath($review), json_encode($this->manifest, JSON_UNESCAPED_SLASHES));

        return true;
    }

    /**
     * One asset through the proxy: the stored copy, or fetched now and kept.
     * Only URLs the archive recorded for this review are ever fetched.
     *
     * @return array{body: string, type: string}|null
     */
    public function asset(Review $review, string $key): ?array
    {
        $disk = Storage::disk(ScreenshotStorage::diskName());
        $path = 'reviews/'.$review->public_id.'/live-assets/'.$key;

        if ($disk->exists($path) && $disk->exists($path.'.type')) {
            return ['body' => (string) $disk->get($path), 'type' => (string) $disk->get($path.'.type')];
        }

        $manifest = rescue(fn () => json_decode((string) $disk->get(self::manifestPath($review)), true), [], report: false);
        $url = is_array($manifest) ? ($manifest[$key] ?? null) : null;

        return is_string($url) ? $this->store($review, $key, $url) : null;
    }

    protected function inlineStylesheet(Review $review, HTMLDocument $document, Element $link, ?string $base): void
    {
        $url = $this->resolve((string) $link->getAttribute('href'), $base);

        if ($url === null || ! $this->eagerRoom()) {
            return;
        }

        $css = $this->fetchCss($url);

        if ($css === null) {
            return;
        }

        $style = $document->createElement('style');
        $style->setAttribute('data-rm-from', $url);

        if ($link->hasAttribute('media')) {
            $style->setAttribute('media', (string) $link->getAttribute('media'));
        }

        $style->textContent = $this->rewriteCss($review, $css, $url, 1);
        $link->replaceWith($style);
    }

    /**
     * url()s point at the archive (fonts fetched now), @imports are written
     * in, two levels deep.
     */
    protected function rewriteCss(Review $review, string $css, ?string $from, int $depth): string
    {
        $css = (string) preg_replace_callback(
            '/@import\s+(?:url\(\s*)?([\'"]?)([^\'")\s;]+)\1\s*\)?\s*([^;]*);/i',
            function (array $m) use ($review, $from, $depth) {
                $url = $this->resolve($m[2], $from);
                $css = $url !== null && $depth < 3 && $this->eagerRoom() ? $this->fetchCss($url) : null;

                if ($css === null) {
                    return $url !== null ? '@import url("'.$url.'") '.$m[3].';' : $m[0];
                }

                $css = $this->rewriteCss($review, $css, $url, $depth + 1);
                $media = trim($m[3]);

                return $media !== '' ? '@media '.$media.' {'.$css.'}' : $css;
            },
            $css,
        );

        return (string) preg_replace_callback(
            '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
            function (array $m) use ($review, $from) {
                $url = $this->resolve(trim($m[2]), $from);

                if ($url === null) {
                    return $m[0];
                }

                $key = $this->record($review, $url);

                if ($this->isFont($url) && $this->eagerRoom()) {
                    $this->store($review, $key, $url, eager: true);
                }

                return 'url("'.$this->proxyUrl($review, $key).'")';
            },
            $css,
        );
    }

    protected function rewriteMedia(Review $review, Element $element, ?string $base): void
    {
        $tag = strtolower($element->tagName);

        foreach (['src', 'poster', 'data-rm-src', 'data-rm-poster'] as $attribute) {
            // A video's own file stays where it is: too big to keep, and only
            // ever shown as a still.
            if ($tag === 'video' && in_array($attribute, ['src', 'data-rm-src'], true)) {
                continue;
            }

            $value = $element->getAttribute($attribute);
            $url = is_string($value) ? $this->resolve($value, $base) : null;

            if ($url !== null) {
                $element->setAttribute($attribute, $this->proxyUrl($review, $this->record($review, $url)));
            }
        }

        $srcset = $element->getAttribute('srcset');

        if (is_string($srcset) && trim($srcset) !== '') {
            $candidates = array_map(function (string $candidate) use ($review, $base) {
                $parts = preg_split('/\s+/', trim($candidate), 2);
                $url = $this->resolve($parts[0] ?? '', $base);

                return $url !== null
                    ? trim($this->proxyUrl($review, $this->record($review, $url)).' '.($parts[1] ?? ''))
                    : trim($candidate);
            }, preg_split('/,(?=\s*\S)(?![^(]*\))/', $srcset) ?: []);

            $element->setAttribute('srcset', implode(', ', array_filter($candidates)));
        }
    }

    protected function record(Review $review, string $url): string
    {
        $key = self::key($review, $url);
        $this->manifest[$key] = $url;

        return $key;
    }

    protected function proxyUrl(Review $review, string $key): string
    {
        return route('live-assets.show', ['review' => $review->public_id, 'key' => $key]);
    }

    /**
     * @return array{body: string, type: string}|null
     */
    protected function store(Review $review, string $key, string $url, bool $eager = false): ?array
    {
        $disk = Storage::disk(ScreenshotStorage::diskName());
        $path = 'reviews/'.$review->public_id.'/live-assets/'.$key;

        if ($disk->exists($path) && $disk->exists($path.'.type')) {
            return ['body' => (string) $disk->get($path), 'type' => (string) $disk->get($path.'.type')];
        }

        // A plain closure, not fn: an arrow function would capture $type by
        // value and the type the response gave would never reach us.
        $type = null;
        $body = rescue(function () use (&$type, $url) {
            return OutboundUrl::fetch($url, self::MAX_ASSET_BYTES, function (Response $response) use (&$type, $url) {
                $type = $this->allowedType((string) $response->header('Content-Type'), $url);

                return $type !== null;
            }, timeout: 10);
        }, null, report: false);

        if (! is_string($body) || $type === null) {
            return null;
        }

        if ($eager) {
            $this->eagerCount++;
            $this->eagerBytes += strlen($body);
        }

        $disk->put($path, $body);
        $disk->put($path.'.type', $type);

        return ['body' => $body, 'type' => $type];
    }

    protected function fetchCss(string $url): ?string
    {
        $body = rescue(fn () => OutboundUrl::fetch($url, self::MAX_CSS_BYTES, fn (Response $response) => str_contains(strtolower((string) $response->header('Content-Type')), 'css')
            || str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.css'), timeout: 10), null, report: false);

        if (! is_string($body)) {
            return null;
        }

        $this->eagerCount++;
        $this->eagerBytes += strlen($body);

        return $body;
    }

    /**
     * Images, fonts and stylesheets only. A server that says
     * application/octet-stream is believed only for a font file.
     */
    protected function allowedType(string $header, string $url): ?string
    {
        $type = strtolower(trim(explode(';', $header)[0]));

        if (str_starts_with($type, 'image/') || str_starts_with($type, 'font/') || $type === 'text/css'
            || in_array($type, ['application/font-woff', 'application/font-woff2', 'application/x-font-ttf', 'application/x-font-otf', 'application/vnd.ms-fontobject'], true)) {
            return $type;
        }

        if (in_array($type, ['application/octet-stream', 'binary/octet-stream', ''], true) && $this->isFont($url)) {
            return 'font/'.pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        }

        return null;
    }

    protected function isFont(string $url): bool
    {
        return in_array(strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)), self::FONT_EXTENSIONS, true);
    }

    protected function eagerRoom(): bool
    {
        return $this->eagerCount < self::MAX_EAGER && $this->eagerBytes < self::MAX_EAGER_BYTES;
    }

    /** An absolute http(s) URL, or null for data:, blob:, fragments and the like. */
    protected function resolve(string $value, ?string $base): ?string
    {
        $value = trim(html_entity_decode($value));

        if ($value === '' || preg_match('#^(data|blob|javascript|about|mailto|tel):#i', $value) || str_starts_with($value, '#')) {
            return null;
        }

        try {
            $uri = $base !== null ? UriResolver::resolve(new Uri($base), new Uri($value)) : new Uri($value);
        } catch (Throwable) {
            return null;
        }

        $url = (string) $uri->withFragment('');

        return preg_match('#^https?://#i', $url) ? $url : null;
    }
}
