<?php

namespace App\Support;

class Seo
{
    public static function ogImageUrl(?string $override = null): string
    {
        $url = $override ?? url(config('seo.og_image', '/images/og.png'));
        $version = config('seo.og_image_version');

        if ($version === null || $version === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.rawurlencode((string) $version);
    }
}
