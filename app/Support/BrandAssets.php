<?php

namespace App\Support;

/**
 * Versioned brand icon paths for MCP #[Icon] attributes (must be compile-time
 * constants) and cache-busting. Keep CACHE_BUST in sync with seo.favicon_version.
 */
final class BrandAssets
{
    public const CACHE_BUST = '9';

    /** Prefer the inline mark in MCP so hosts never hit a stale domain-favicon CDN. */
    public const APP_ICON = BrandIconData::APP_ICON_64;

    public const APP_ICON_HTTPS = 'images/app-icon-v9.png?v='.self::CACHE_BUST;

    public const FAVICON_32 = 'images/favicon-32x32-v9.png?v='.self::CACHE_BUST;

    public const APPLE_TOUCH = 'images/apple-touch-icon-v9.png?v='.self::CACHE_BUST;

    /**
     * Absolute URL for the yellow app mark (chat markdown, OG, etc.).
     */
    public static function appIconUrl(): string
    {
        return Seo::faviconUrl('/images/app-icon-v9.png');
    }

    /**
     * Clickable chat share block: yellow mark markdown link + backtick URL.
     * Agents must paste this into the human-visible message (never “open in browser” alone).
     */
    public static function markdownShareLink(string $url, string $alt = 'ReviseMy'): string
    {
        $mark = self::appIconUrl();

        return "[![{$alt}]({$mark})]({$url})\n`{$url}`";
    }
}
