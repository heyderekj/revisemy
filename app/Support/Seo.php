<?php

namespace App\Support;

class Seo
{
    public static function ogImageUrl(?string $override = null): string
    {
        $url = $override ?? url(config('seo.og_image', '/images/og.png'));

        return self::withVersion($url, config('seo.og_image_version'));
    }

    /**
     * Cache-busted favicon / apple-touch URL (path like /favicon.ico).
     */
    public static function faviconUrl(string $path): string
    {
        $version = config('seo.favicon_version', BrandAssets::CACHE_BUST);

        return self::withVersion(url($path), $version);
    }

    protected static function withVersion(string $url, mixed $version): string
    {
        if ($version === null || $version === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.rawurlencode((string) $version);
    }
}
