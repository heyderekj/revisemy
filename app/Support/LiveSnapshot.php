<?php

namespace App\Support;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * The page's code as it was captured (reviews.dom_path), made safe to frame
 * on the review: nothing of the page's own runs, nothing submits or
 * navigates, and its assets load from where the page loaded them. One
 * script of ours goes in, the element picker (resources/js/live-pick.js),
 * under the response's nonce.
 *
 * The response also carries `sandbox allow-scripts` in its CSP, so even
 * opened on its own the document gets an opaque origin: no cookies, no
 * storage, no way back to the app.
 */
class LiveSnapshot
{
    /** Elements removed whole: anything that runs, frames, or redirects. */
    protected const REMOVE = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'portal'];

    public static function prepare(string $html, ?string $baseUrl, string $nonce, string $script): string
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        foreach (self::REMOVE as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                $node->remove();
            }
        }

        foreach (iterator_to_array($document->querySelectorAll('meta[http-equiv]')) as $meta) {
            $meta->remove();
        }

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            self::defuse($element);
        }

        self::restoreMedia($document);

        $head = $document->head;

        // The same rules as the response's CSP, inside the page, for when the
        // review hands it to the frame as srcdoc and no header comes with it.
        $policy = $document->createElement('meta');
        $policy->setAttribute('http-equiv', 'Content-Security-Policy');
        $policy->setAttribute('content', self::policy($nonce));

        $referrer = $document->createElement('meta');
        $referrer->setAttribute('name', 'referrer');
        $referrer->setAttribute('content', 'no-referrer');
        $head?->prepend($referrer);

        $head?->prepend($policy);

        if ($baseUrl !== null && preg_match('#^https?://#i', $baseUrl)) {
            $base = $document->createElement('base');
            $base->setAttribute('href', $baseUrl);
            $head?->prepend($base);
        }

        $picker = $document->createElement('script');
        $picker->setAttribute('nonce', $nonce);
        $picker->textContent = $script;
        ($document->body ?? $document->documentElement)?->append($picker);

        return $document->saveHtml();
    }

    /**
     * Only our picker runs; the page can show its styles, images and fonts
     * but can't fetch, post or frame anything. `sandbox` can't go in a meta
     * tag, so the route sends it as a header and the frame sets it too.
     */
    public static function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'none'",
            "script-src 'nonce-{$nonce}'",
            "style-src * data: 'unsafe-inline'",
            'img-src * data: blob:',
            'font-src * data:',
            'media-src * data: blob:',
            "connect-src 'none'",
            "form-action 'none'",
            "frame-src 'none'",
        ]);
    }

    /**
     * Media the page's scripts loaded in code, noted by the capture as
     * data-rm-src (CaptureSettleScript). With no scripts here, they'd stay
     * empty: a video with no source shows black, a lazy image nothing. A
     * video gets its source back and shows a still frame; it never plays.
     */
    protected static function restoreMedia(HTMLDocument $document): void
    {
        $url = fn (?string $value) => $value !== null && preg_match('#^https?://#i', $value) ? $value : null;

        foreach (iterator_to_array($document->querySelectorAll('video[data-rm-src], video[data-rm-poster]')) as $video) {
            $hasSource = $video->hasAttribute('src') || $video->querySelector('source[src]') !== null;

            if (! $hasSource && ($src = $url($video->getAttribute('data-rm-src')))) {
                $video->setAttribute('src', $src.(str_contains($src, '#') ? '' : '#t=0.1'));
            }

            if (! $video->hasAttribute('poster') && ($poster = $url($video->getAttribute('data-rm-poster')))) {
                $video->setAttribute('poster', $poster);
            }

            foreach (['autoplay', 'loop'] as $attribute) {
                $video->removeAttribute($attribute);
            }
            $video->setAttribute('muted', '');
            $video->setAttribute('playsinline', '');
            $video->setAttribute('preload', 'metadata');
        }

        foreach (iterator_to_array($document->querySelectorAll('img[data-rm-src]')) as $image) {
            $src = $url($image->getAttribute('data-rm-src'));

            if ($src && (! $image->hasAttribute('src') || $image->getAttribute('src') === '' || str_starts_with((string) $image->getAttribute('src'), 'data:'))) {
                $image->setAttribute('src', $src);
                $image->removeAttribute('srcset');
            }
            $image->removeAttribute('loading');
        }
    }

    /** Event handlers off, script URLs gone, forms going nowhere. */
    protected static function defuse(Element $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim((string) $attribute->value);

            if (str_starts_with($name, 'on')
                || in_array($name, ['formaction', 'action', 'ping', 'srcdoc'], true)
                || (in_array($name, ['href', 'src', 'xlink:href', 'data'], true) && preg_match('/^\s*(javascript|vbscript|data:text\/html)/i', $value))) {
                $element->removeAttribute($attribute->name);
            }
        }
    }
}
