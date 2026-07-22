<?php

namespace App\Support;

/**
 * Versioned brand icon paths for MCP #[Icon] attributes (must be compile-time
 * constants) and cache-busting. Keep CACHE_BUST in sync with seo.favicon_version.
 */
final class BrandAssets
{
    public const CACHE_BUST = '8';

    public const APP_ICON = 'images/app-icon.png?v='.self::CACHE_BUST;

    public const FAVICON_32 = 'images/favicon-32x32-v8.png?v='.self::CACHE_BUST;

    public const APPLE_TOUCH = 'images/apple-touch-icon-v8.png?v='.self::CACHE_BUST;
}
